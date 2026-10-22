"use strict";
/*
 * Guarded trading actions shared by the HTTP routes and the bot.
 * Every order goes through placeOrder(), so every safety rule applies equally
 * to manual orders from the app and automatic orders from the bot.
 */
function createTrading({ cfg, getConn, now = () => Date.now() }) {
  let killed = false;

  // Fail closed: only accounts that report themselves as demo may trade.
  function isDemo(info) {
    if (info && info.type) return String(info.type).toUpperCase().includes("DEMO");
    return /demo/i.test((info && info.server) || "");
  }

  async function dayStats(c) {
    const start = new Date(now()); start.setUTCHours(0, 0, 0, 0);
    const r = await c.getDealsByTimeRange(start, new Date(now() + 60000));
    const deals = (r && r.deals) || [];
    const trades = deals.filter(d => /BUY|SELL/.test(String(d.type)));
    const opened = trades.filter(d => d.entryType === "DEAL_ENTRY_IN").length;
    const realized = trades.reduce((a, d) => a + (d.profit || 0) + (d.commission || 0) + (d.swap || 0), 0);
    const positions = await c.getPositions();
    const floating = positions.reduce((a, p) => a + (p.profit || 0), 0);
    return { opened, realized, floating, pnl: realized + floating, openPositions: positions.length };
  }

  // Returns { ok:true, orderId, positionId, message } or { ok:false, status, error, kind }.
  // kind: disabled | killed | invalid | notdemo | limit_open | limit_day
  // Orders run one at a time. Without this, a manual order and a bot order arriving together
  // could both pass the open-position / daily-limit checks before either one is on the account.
  let queue = Promise.resolve();
  function placeOrder(o) {
    const run = queue.then(() => placeOrderNow(o));
    queue = run.catch(() => {});
    return run;
  }

  async function placeOrderNow(o) {
    const fail = (status, error, kind) => ({ ok: false, status, error, kind });
    const { symbol, side, lots, sl, tp, clientId } = o;
    const comment = /^[A-Za-z0-9:_-]{1,31}$/.test(o.comment || "") ? o.comment : "5G Network";

    if (!cfg.tradingEnabled) return fail(403, "Trading is disabled on the server (read-only mode).", "disabled");
    if (killed) return fail(403, "Kill switch is active. New orders are blocked.", "killed");
    if (!cfg.symbols.includes(symbol)) return fail(400, "Symbol not allowed", "invalid");
    if (side !== "buy" && side !== "sell") return fail(400, "side must be buy or sell", "invalid");
    if (!isFinite(lots) || lots <= 0 || lots > cfg.maxLots) return fail(400, "lots must be above 0 and at most " + cfg.maxLots, "invalid");
    if (!isFinite(sl) || sl <= 0) return fail(400, "A stop loss price is required", "invalid");
    if (tp !== null && tp !== undefined && (!isFinite(tp) || tp <= 0)) return fail(400, "Invalid take profit", "invalid");
    if (!/^[A-Za-z0-9_]{1,40}$/.test(String(clientId))) return fail(400, "Invalid clientId", "invalid");
    const takeProfit = tp === null || tp === undefined ? null : tp;

    const c = await getConn();
    const info = await c.getAccountInformation();
    if (!isDemo(info)) return fail(403, "Refused: this is not a demo account.", "notdemo");

    const price = await c.getSymbolPrice(symbol);
    const ref = side === "buy" ? price.ask : price.bid;
    if (side === "buy" ? !(sl < price.bid) : !(sl > price.ask)) return fail(400, "Stop loss is on the wrong side of the price", "invalid");
    if (takeProfit !== null && (side === "buy" ? !(takeProfit > price.ask) : !(takeProfit < price.bid))) return fail(400, "Take profit is on the wrong side of the price", "invalid");
    if (Math.abs(sl - ref) / ref > 0.02) return fail(400, "Stop loss is more than 2% from price", "invalid");

    const st = await dayStats(c);
    if (st.openPositions >= cfg.maxOpen) return fail(409, "Max open positions reached", "limit_open");
    if (st.opened >= cfg.maxTradesDay) return fail(409, "Max trades per day reached", "limit_day");
    if (st.pnl <= -cfg.maxDailyLoss) return fail(409, "Daily loss limit reached", "limit_day");

    const opts = { comment, clientId: String(clientId) };
    const tpArg = takeProfit === null ? undefined : takeProfit;
    const r = side === "buy"
      ? await c.createMarketBuyOrder(symbol, lots, sl, tpArg, opts)
      : await c.createMarketSellOrder(symbol, lots, sl, tpArg, opts);
    return { ok: true, orderId: r.orderId, positionId: r.positionId, message: r.stringCode };
  }

  // Results by UTC day for the last `days` days (today included).
  async function history(days) {
    const c = await getConn();
    const start = new Date(now()); start.setUTCHours(0, 0, 0, 0);
    start.setUTCDate(start.getUTCDate() - (days - 1));
    const r = await c.getDealsByTimeRange(start, new Date(now() + 60000));
    const deals = ((r && r.deals) || []).filter(d => /BUY|SELL/.test(String(d.type)));
    const byDay = {};
    const closed = [];
    for (const d of deals) {
      const t = new Date(d.time);
      const key = t.toISOString().slice(0, 10);
      const net = (d.profit || 0) + (d.commission || 0) + (d.swap || 0);
      const row = byDay[key] || (byDay[key] = { day: key, closedTrades: 0, wins: 0, net: 0 });
      row.net += net;
      if (d.entryType === "DEAL_ENTRY_OUT" || d.entryType === "DEAL_ENTRY_OUT_BY") {
        row.closedTrades++;
        if (net > 0) row.wins++;
        closed.push({
          time: t.toISOString(), symbol: d.symbol,
          side: /BUY/.test(String(d.type)) ? "SELL" : "BUY", // a closing deal is opposite to the position
          volume: d.volume, price: d.price, net: +net.toFixed(2), positionId: d.positionId
        });
      }
    }
    const rows = Object.values(byDay).sort((a, b) => a.day.localeCompare(b.day)).map(r2 => ({ ...r2, net: +r2.net.toFixed(2) }));
    const totalClosed = rows.reduce((a, r2) => a + r2.closedTrades, 0);
    const wins = rows.reduce((a, r2) => a + r2.wins, 0);
    closed.sort((a, b) => b.time.localeCompare(a.time));
    return {
      days, byDay: rows,
      total: { closedTrades: totalClosed, wins, winRate: totalClosed ? +(wins / totalClosed).toFixed(3) : null, net: +rows.reduce((a, r2) => a + r2.net, 0).toFixed(2) },
      recent: closed.slice(0, 50),
      note: "All trades on this account (UTC days). Net includes commission and swap. Open trades are not included."
    };
  }

  return { isDemo, dayStats, placeOrder, history, setKilled: v => { killed = !!v; }, isKilled: () => killed };
}

module.exports = { createTrading };
