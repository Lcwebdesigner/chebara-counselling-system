"use strict";
/*
 * Server-side auto-trader. Runs on the server so it keeps working when your phone is off.
 * It samples the price every botIntervalSec, evaluates the enabled strategies, and sends
 * orders through placeOrder() (which enforces every safety limit). With
 * TRADING_ENABLED=false it runs in dry-run mode: signals are logged, no orders are sent.
 */
const { STRATEGIES, signal, pipSize } = require("./engine");

const nextUtcMidnight = ms => { const d = new Date(ms); return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate() + 1); };

function createBot({ cfg, getConn, placeOrder, now = () => Date.now(), log = console.log }) {
  const st = {
    running: false, timer: null, stopReason: "", pausedUntil: 0, pausedReason: "",
    prices: {}, lastQuoteTime: {}, quotes: {}, signals: [], lastLogged: {},
    lastBlock: "", lastError: "", errKind: "", ticks: 0, lastTickAt: 0, lastTradeAt: 0, open: {}, market: "",
    conf: { strategies: cfg.botStrategies.slice(), lots: cfg.botLots, slPips: cfg.botSlPips, tpPips: cfg.botTpPips, cooldownMin: cfg.botCooldownMin }
  };
  let gen = 0;
  const msg = e => String((e && e.message) || e);

  function record(rec) {
    const key = rec.symbol + ":" + rec.strategy, tag = rec.signal + "|" + rec.action;
    if (st.lastLogged[key] === tag) return; // do not repeat the same line while a signal persists
    st.lastLogged[key] = tag;
    st.signals.unshift({ time: new Date(now()).toISOString(), ...rec });
    if (st.signals.length > 40) st.signals.length = 40;
  }

  async function evaluate(symbol, id, arr, q, pip) {
    const key = symbol + ":" + id;
    const sig = signal(id, arr, pip);
    if (sig === "WAIT") { delete st.lastLogged[key]; return; }
    const digits = pip === 0.01 ? 3 : 5;
    const base = { symbol, strategy: id, signal: sig, price: +((q.bid + q.ask) / 2).toFixed(digits) };

    if (st.open[key]) return; // one position per strategy at a time
    if (st.lastTradeAt && now() - st.lastTradeAt < st.conf.cooldownMin * 60000) { record({ ...base, action: "waiting: cooldown" }); return; }
    if ((q.ask - q.bid) / pip > cfg.botMaxSpreadPips) { record({ ...base, action: "skipped: spread too wide" }); return; }

    const buy = sig === "BUY", dir = buy ? 1 : -1, ref = buy ? q.ask : q.bid;
    const sl = +(ref - dir * st.conf.slPips * pip).toFixed(digits);
    const tp = st.conf.tpPips > 0 ? +(ref + dir * st.conf.tpPips * pip).toFixed(digits) : null;
    const clientId = "5GN_" + id + "_" + now().toString(36);

    const prevTrade = st.lastTradeAt;
    st.lastTradeAt = now(); // set first: if the call times out we cannot know whether it filled
    let res;
    try {
      res = await placeOrder({ symbol, side: buy ? "buy" : "sell", lots: st.conf.lots, sl, tp, clientId, comment: "5G:" + id });
    } catch (e) {
      st.lastError = msg(e); st.errKind = "order"; // stays visible until the next successful order
      log("bot order error:", st.lastError);
      record({ ...base, action: "error: " + st.lastError }); // cooldown stays so we never double-send
      return;
    }
    if (res.ok) {
      st.open[key] = String(res.positionId || res.orderId || "pending");
      if (st.errKind === "order") { st.lastError = ""; st.errKind = ""; }
      record({ ...base, action: "ordered", sl, tp });
      return;
    }
    st.lastTradeAt = prevTrade; // clean rejection: nothing was sent
    switch (res.kind) {
      case "disabled": record({ ...base, action: "dry-run: trading disabled on server" }); break;
      case "killed": stop("Kill switch is active"); break;
      case "notdemo": st.lastError = res.error; st.errKind = "order"; stop("Not a demo account"); break;
      case "limit_day":
        st.pausedUntil = nextUtcMidnight(now()); st.pausedReason = res.error;
        record({ ...base, action: "paused: " + res.error });
        break;
      default:
        st.lastBlock = res.error;
        record({ ...base, action: "blocked: " + res.error });
    }
  }

  async function tick() {
    st.ticks++; st.lastTickAt = now();
    if (st.errKind === "tick") { st.lastError = ""; st.errKind = ""; } // a connection error clears once ticks work again
    if (st.pausedUntil && now() < st.pausedUntil) return;
    if (st.pausedUntil) { st.pausedUntil = 0; st.pausedReason = ""; }
    const c = await getConn();
    const openIds = new Set((await c.getPositions()).map(p => String(p.id)));
    for (const k of Object.keys(st.open)) if (!openIds.has(String(st.open[k]))) delete st.open[k];

    for (const symbol of cfg.symbols) {
      const q = await c.getSymbolPrice(symbol);
      const pip = pipSize(symbol);
      const qt = q.time ? new Date(q.time).getTime() : now();
      const stale = now() - qt > 120000;
      st.quotes[symbol] = { bid: q.bid, ask: q.ask, spreadPips: +((q.ask - q.bid) / pip).toFixed(2), time: new Date(qt).toISOString(), stale };
      if (stale) { st.market = "Price feed is stale (market closed?)"; continue; }
      st.market = "";
      const arr = st.prices[symbol] || (st.prices[symbol] = []);
      if (st.lastQuoteTime[symbol] !== qt) {
        arr.push((q.bid + q.ask) / 2);
        if (arr.length > 60) arr.shift();
        st.lastQuoteTime[symbol] = qt;
      }
      if (arr.length < 20) continue; // warming up
      for (const id of st.conf.strategies) await evaluate(symbol, id, arr, q, pip);
    }
  }

  function schedule(g, ms) {
    if (st.timer) clearTimeout(st.timer);
    st.timer = setTimeout(() => loop(g), ms);
    if (st.timer.unref) st.timer.unref();
  }
  async function loop(g) {
    st.timer = null;
    if (!st.running || g !== gen) return;
    try { await tick(); } catch (e) { st.lastError = msg(e); st.errKind = "tick"; log("bot tick error:", st.lastError); }
    if (st.running && g === gen) schedule(g, cfg.botIntervalSec * 1000);
  }

  function start() {
    if (st.running) return;
    st.running = true; st.stopReason = ""; st.lastError = ""; st.errKind = "";
    gen++; schedule(gen, 0);
  }
  function stop(reason) {
    st.running = false; st.stopReason = reason || "Stopped";
    gen++;
    if (st.timer) { clearTimeout(st.timer); st.timer = null; }
  }

  function setConfig(p) {
    p = p || {};
    const n = { ...st.conf };
    const bad = error => ({ ok: false, error });
    if (p.strategies !== undefined) {
      if (!Array.isArray(p.strategies) || p.strategies.some(s => typeof s !== "string" || !Object.hasOwn(STRATEGIES, s))) return bad("Unknown strategy");
      n.strategies = [...new Set(p.strategies)];
    }
    if (p.lots !== undefined) {
      const v = Number(p.lots);
      if (!(v > 0) || v > cfg.maxLots) return bad("Lots must be above 0 and at most " + cfg.maxLots + " (the server limit)");
      n.lots = v;
    }
    if (p.slPips !== undefined) {
      const v = Number(p.slPips);
      if (!(v >= 3 && v <= 100)) return bad("Stop loss must be 3 to 100 pips");
      n.slPips = v;
    }
    if (p.tpPips !== undefined) {
      const v = Number(p.tpPips);
      if (!(v === 0 || (v >= 3 && v <= 300))) return bad("Take profit must be 0 (none) or 3 to 300 pips");
      n.tpPips = v;
    }
    if (p.cooldownMin !== undefined) {
      const v = Number(p.cooldownMin);
      if (!(v >= 0 && v <= 1440)) return bad("Cooldown must be 0 to 1440 minutes");
      n.cooldownMin = v;
    }
    st.conf = n;
    return { ok: true, config: n };
  }

  function status() {
    const left = st.lastTradeAt ? Math.max(0, Math.round((st.lastTradeAt + st.conf.cooldownMin * 60000 - now()) / 1000)) : 0;
    return {
      running: st.running,
      mode: cfg.tradingEnabled ? "demo-trading" : "dry-run",
      paused: st.pausedUntil && now() < st.pausedUntil ? st.pausedReason : null,
      stopReason: st.running ? "" : st.stopReason,
      config: st.conf, available: STRATEGIES,
      samples: Object.fromEntries(Object.entries(st.prices).map(([k, v]) => [k, v.length])), warmupNeeded: 20,
      quotes: st.quotes, market: st.market, ticks: st.ticks,
      lastTickAt: st.lastTickAt ? new Date(st.lastTickAt).toISOString() : null,
      intervalSec: cfg.botIntervalSec, cooldownLeftSec: left, open: st.open,
      lastBlock: st.lastBlock, lastError: st.lastError, signals: st.signals.slice(0, 20)
    };
  }

  return { start, stop, setConfig, status, tick };
}

module.exports = { createBot };
