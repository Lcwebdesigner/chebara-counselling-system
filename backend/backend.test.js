"use strict";
// Run with:  npm test   (Node 18+, no extra packages). Uses a fake broker connection.
const test = require("node:test");
const assert = require("node:assert/strict");
const { signal, pipSize } = require("./engine");
const { createTrading } = require("./trading");
const { createBot } = require("./bot");

const PIP = 0.0001;
const T0 = Date.UTC(2026, 9, 21, 10, 0, 0);

function baseCfg(over = {}) {
  return {
    tradingEnabled: true, symbols: ["EURUSD"], maxLots: 0.01, maxOpen: 2, maxTradesDay: 5, maxDailyLoss: 30,
    botStrategies: ["trend"], botLots: 0.01, botSlPips: 15, botTpPips: 30, botCooldownMin: 30,
    botIntervalSec: 10, botMaxSpreadPips: 1.5, ...over
  };
}

function fakeConn() {
  const s = {
    info: { type: "ACCOUNT_TRADE_MODE_DEMO", server: "MetaQuotes-Demo", balance: 3000, equity: 3000 },
    price: { bid: 1.08, ask: 1.0801, time: new Date(T0) },
    positions: [], deals: [], orders: []
  };
  const open = (side, sym, lots, sl, tp, opts) => {
    const id = String(1000 + s.orders.length + 1);
    s.orders.push({ side, sym, lots, sl, tp, opts });
    s.positions.push({ id, profit: 0 });
    return { orderId: id, positionId: id, stringCode: "TRADE_RETCODE_DONE" };
  };
  return {
    s,
    getAccountInformation: async () => s.info,
    getSymbolPrice: async () => ({ ...s.price }),
    getPositions: async () => s.positions,
    getDealsByTimeRange: async () => ({ deals: s.deals }),
    createMarketBuyOrder: async (...a) => open("buy", ...a),
    createMarketSellOrder: async (...a) => open("sell", ...a),
    closePosition: async id => { s.positions = s.positions.filter(p => p.id !== id); return { stringCode: "OK" }; }
  };
}

const good = (over = {}) => ({ symbol: "EURUSD", side: "buy", lots: 0.01, sl: 1.0780, tp: 1.0850, clientId: "abc123", ...over });

/* ------------------------------------------------------------------ engine */
test("engine: needs 20 samples", () => {
  assert.equal(signal("trend", Array(19).fill(1.08), PIP), "WAIT");
});
test("engine: trend follows a steady ramp both ways", () => {
  const up = Array.from({ length: 25 }, (_, i) => 1.08 + i * 0.00005);
  const down = Array.from({ length: 25 }, (_, i) => 1.08 - i * 0.00005);
  assert.equal(signal("trend", up, PIP), "BUY");
  assert.equal(signal("trend", down, PIP), "SELL");
  assert.equal(signal("trend", Array(25).fill(1.08), PIP), "WAIT");
});
test("engine: mean reversion fades a 3 pip stretch", () => {
  const a = Array(24).fill(1.08).concat([1.0803]);
  const b = Array(24).fill(1.08).concat([1.0797]);
  assert.equal(signal("meanrev", a, PIP), "SELL");
  assert.equal(signal("meanrev", b, PIP), "BUY");
});
test("engine: breakout needs a new 10-sample extreme", () => {
  const base = Array(24).fill(1.08);
  assert.equal(signal("breakout", base.concat([1.0801]), PIP), "BUY");
  assert.equal(signal("breakout", base.concat([1.0799]), PIP), "SELL");
  assert.equal(signal("breakout", base.concat([1.08]), PIP), "WAIT");
});
test("engine: zscore ignores noise in a dead-flat market but reacts to a spike", () => {
  const flat = Array(24).fill(1.08);
  assert.equal(signal("zscore", flat.concat([1.080005]), PIP), "WAIT");
  assert.equal(signal("zscore", flat.concat([1.0806]), PIP), "SELL");
});
test("engine: unknown strategy waits; JPY pip size", () => {
  assert.equal(signal("nope", Array(25).fill(1), PIP), "WAIT");
  assert.equal(pipSize("USDJPY"), 0.01);
  assert.equal(pipSize("EURUSD"), 0.0001);
});

/* ----------------------------------------------------------------- trading */
function mk(over, conn = fakeConn()) {
  const cfg = baseCfg(over);
  return { cfg, conn, t: createTrading({ cfg, getConn: async () => conn, now: () => T0 }) };
}
async function expectFail(t, o, kind, status) {
  const r = await t.placeOrder(o);
  assert.equal(r.ok, false, JSON.stringify(r));
  assert.equal(r.kind, kind, r.error);
  if (status) assert.equal(r.status, status);
  return r;
}

test("trading: good order passes sl/tp/comment/clientId to the broker", async () => {
  const { t, conn } = mk();
  const r = await t.placeOrder(good({ comment: "5G:trend" }));
  assert.equal(r.ok, true);
  const o = conn.s.orders[0];
  assert.deepEqual([o.side, o.sym, o.lots, o.sl, o.tp], ["buy", "EURUSD", 0.01, 1.0780, 1.0850]);
  assert.deepEqual(o.opts, { comment: "5G:trend", clientId: "abc123" });
});
test("trading: order without take profit sends undefined tp", async () => {
  const { t, conn } = mk();
  assert.equal((await t.placeOrder(good({ tp: null }))).ok, true);
  assert.equal(conn.s.orders[0].tp, undefined);
});
test("trading: read-only mode refuses and never touches the broker", async () => {
  let called = false;
  const t = createTrading({ cfg: baseCfg({ tradingEnabled: false }), getConn: async () => { called = true; } });
  await expectFail(t, good(), "disabled", 403);
  assert.equal(called, false);
});
test("trading: kill switch blocks until resumed", async () => {
  const { t } = mk();
  t.setKilled(true);
  await expectFail(t, good(), "killed", 403);
  t.setKilled(false);
  assert.equal((await t.placeOrder(good())).ok, true);
});
test("trading: input validation", async () => {
  const { t } = mk();
  await expectFail(t, good({ symbol: "GBPUSD" }), "invalid", 400);
  await expectFail(t, good({ side: "hold" }), "invalid", 400);
  await expectFail(t, good({ lots: 0.02 }), "invalid", 400);
  await expectFail(t, good({ lots: 0 }), "invalid", 400);
  await expectFail(t, good({ lots: NaN }), "invalid", 400);
  await expectFail(t, good({ sl: NaN }), "invalid", 400);
  await expectFail(t, good({ sl: undefined }), "invalid", 400);
  await expectFail(t, good({ tp: -1 }), "invalid", 400);
  await expectFail(t, good({ clientId: "bad id!" }), "invalid", 400);
});
test("trading: refuses a non-demo account (type and server-name fallback)", async () => {
  const a = mk(); a.conn.s.info = { type: "ACCOUNT_TRADE_MODE_REAL", server: "Broker-Demo" };
  await expectFail(a.t, good(), "notdemo", 403);
  const b = mk(); b.conn.s.info = { server: "SomeBroker-Live" };
  await expectFail(b.t, good(), "notdemo", 403);
  const c = mk(); c.conn.s.info = { server: "MetaQuotes-Demo" };
  assert.equal((await c.t.placeOrder(good())).ok, true);
  const d = mk(); d.conn.s.info = {};
  await expectFail(d.t, good(), "notdemo", 403);
});
test("trading: stop loss / take profit sides and 2% rule", async () => {
  const { t } = mk();
  await expectFail(t, good({ sl: 1.0850 }), "invalid", 400);               // buy SL above price
  await expectFail(t, good({ tp: 1.0750 }), "invalid", 400);               // buy TP below price
  await expectFail(t, good({ sl: 1.0500 }), "invalid", 400);               // more than 2% away
  await expectFail(t, good({ side: "sell", sl: 1.0750, tp: null }), "invalid", 400);
  assert.equal((await t.placeOrder(good({ side: "sell", sl: 1.0830, tp: 1.0750 }))).ok, true);
});
test("trading: max open positions", async () => {
  const { t, conn } = mk();
  conn.s.positions = [{ id: "1", profit: 0 }, { id: "2", profit: 0 }];
  await expectFail(t, good(), "limit_open", 409);
});
test("trading: max trades per day counts entry deals", async () => {
  const { t, conn } = mk();
  conn.s.deals = Array.from({ length: 5 }, () => ({ type: "DEAL_TYPE_BUY", entryType: "DEAL_ENTRY_IN", profit: 0 }));
  await expectFail(t, good(), "limit_day", 409);
});
test("trading: daily loss counts realised and floating loss", async () => {
  const a = mk();
  a.conn.s.deals = [{ type: "DEAL_TYPE_SELL", entryType: "DEAL_ENTRY_OUT", profit: -25, commission: -2, swap: -3 }];
  await expectFail(a.t, good(), "limit_day", 409);
  const b = mk();
  b.conn.s.positions = [{ id: "9", profit: -31 }];
  await expectFail(b.t, good(), "limit_day", 409);
  const c = mk();
  c.conn.s.deals = [{ type: "DEAL_TYPE_SELL", entryType: "DEAL_ENTRY_OUT", profit: -10 }];
  assert.equal((await c.t.placeOrder(good())).ok, true);
});
test("trading: history groups by UTC day with wins, net and closing side", async () => {
  const { t, conn } = mk();
  conn.s.deals = [
    { type: "DEAL_TYPE_BUY", entryType: "DEAL_ENTRY_IN", time: new Date("2026-10-20T08:00:00Z"), profit: 0, commission: -0.1 },
    { type: "DEAL_TYPE_SELL", entryType: "DEAL_ENTRY_OUT", time: new Date("2026-10-20T09:00:00Z"), profit: 4.5, commission: -0.1, symbol: "EURUSD", volume: 0.01, price: 1.08, positionId: "7" },
    { type: "DEAL_TYPE_SELL", entryType: "DEAL_ENTRY_IN", time: new Date("2026-10-21T08:00:00Z"), profit: 0 },
    { type: "DEAL_TYPE_BUY", entryType: "DEAL_ENTRY_OUT", time: new Date("2026-10-21T09:00:00Z"), profit: -1.5, symbol: "EURUSD", volume: 0.01, price: 1.08, positionId: "8" },
    { type: "DEAL_TYPE_BALANCE", entryType: "DEAL_ENTRY_IN", time: new Date("2026-10-21T01:00:00Z"), profit: 3000 }
  ];
  const h = await t.history(5);
  assert.deepEqual(h.byDay.map(r => [r.day, r.closedTrades, r.wins, r.net]), [["2026-10-20", 1, 1, 4.3], ["2026-10-21", 1, 0, -1.5]]);
  assert.equal(h.total.net, 2.8);
  assert.equal(h.total.winRate, 0.5);
  assert.equal(h.recent[0].side, "SELL"); // closing BUY deal => the position was a SELL
});

/* --------------------------------------------------------------------- bot */
function mkBot(over = {}) {
  const clock = { t: T0 };
  const conn = fakeConn();
  const cfg = baseCfg(over);
  const trading = createTrading({ cfg, getConn: async () => conn, now: () => clock.t });
  const logs = [];
  const bot = createBot({ cfg, getConn: async () => conn, placeOrder: trading.placeOrder, now: () => clock.t, log: (...a) => logs.push(a.join(" ")) });
  // advance 10 s, set the market, run one tick
  const step = async (mid, spread = PIP) => {
    clock.t += 10000;
    conn.s.price = { bid: +(mid - spread / 2).toFixed(5), ask: +(mid + spread / 2).toFixed(5), time: new Date(clock.t) };
    await bot.tick();
  };
  return { bot, conn, clock, step, trading, logs, cfg };
}
const ramp = i => 1.08 + i * 0.00005;

test("bot: stays quiet while warming up, then buys a clear uptrend with correct SL/TP", async () => {
  const { bot, conn, step } = mkBot();
  for (let i = 0; i < 19; i++) await step(ramp(i));
  assert.equal(conn.s.orders.length, 0);
  assert.equal(bot.status().samples.EURUSD, 19);
  await step(ramp(19));
  assert.equal(conn.s.orders.length, 1);
  const o = conn.s.orders[0];
  const ask = conn.s.price.ask;
  assert.equal(o.side, "buy");
  assert.equal(o.sl, +(ask - 15 * PIP).toFixed(5));
  assert.equal(o.tp, +(ask + 30 * PIP).toFixed(5));
  assert.match(o.opts.clientId, /^5GN_trend_[a-z0-9]+$/);
  assert.equal(o.opts.comment, "5G:trend");
  assert.equal(bot.status().signals[0].action, "ordered");
});
test("bot: sells a downtrend", async () => {
  const { conn, step } = mkBot();
  for (let i = 0; i < 22; i++) await step(1.09 - i * 0.00005);
  assert.equal(conn.s.orders[0].side, "sell");
  assert.ok(conn.s.orders[0].sl > conn.s.price.ask - 1e-9 || conn.s.orders[0].sl > 1.09 - 0.0011);
});
test("bot: never stacks a second trade for the same strategy, and obeys the cooldown", async () => {
  const { conn, step, clock } = mkBot();
  for (let i = 0; i < 30; i++) await step(ramp(i));
  assert.equal(conn.s.orders.length, 1);                 // position still open
  conn.s.positions = [];                                  // it hit TP/SL
  for (let i = 30; i < 34; i++) await step(ramp(i));
  assert.equal(conn.s.orders.length, 1);                 // cooldown (30 min) still running
  clock.t += 31 * 60000;
  await step(ramp(35));
  assert.equal(conn.s.orders.length, 2);                 // cooldown over -> allowed again
});
test("bot: dry-run when trading is disabled logs signals but sends nothing", async () => {
  const { bot, conn, step } = mkBot({ tradingEnabled: false });
  for (let i = 0; i < 25; i++) await step(ramp(i));
  assert.equal(conn.s.orders.length, 0);
  assert.equal(bot.status().mode, "dry-run");
  assert.match(bot.status().signals[0].action, /^dry-run/);
});
test("bot: stale price feed (market closed) trades nothing and says so", async () => {
  const { bot, conn, clock } = mkBot();
  conn.s.price = { bid: 1.08, ask: 1.0801, time: new Date(T0 - 10 * 60000) };
  clock.t += 10000;
  await bot.tick();
  assert.equal(bot.status().quotes.EURUSD.stale, true);
  assert.match(bot.status().market, /stale/i);
  assert.equal(bot.status().samples.EURUSD, undefined);
});
test("bot: wide spread skips the trade", async () => {
  const { bot, conn, step } = mkBot();
  for (let i = 0; i < 19; i++) await step(ramp(i));
  await step(ramp(19), 3 * PIP);
  assert.equal(conn.s.orders.length, 0);
  assert.match(bot.status().signals[0].action, /spread/);
});
test("bot: daily limit pauses until the next UTC day", async () => {
  const { bot, conn, step, clock } = mkBot({ botCooldownMin: 0 });
  conn.s.deals = Array.from({ length: 5 }, () => ({ type: "DEAL_TYPE_BUY", entryType: "DEAL_ENTRY_IN" }));
  for (let i = 0; i < 22; i++) await step(ramp(i));
  assert.equal(conn.s.orders.length, 0);
  assert.match(bot.status().paused, /Max trades/);
  const ticks = bot.status().ticks;
  await step(ramp(23));                                   // paused: tick returns early
  assert.equal(conn.s.orders.length, 0);
  assert.equal(bot.status().ticks, ticks + 1);
  clock.t = Date.UTC(2026, 9, 22, 0, 5, 0);
  conn.s.deals = [];
  await step(ramp(24));
  assert.equal(bot.status().paused, null);
});
test("bot: kill switch stops the bot", async () => {
  const { bot, conn, step, trading } = mkBot();
  bot.start();
  trading.setKilled(true);
  for (let i = 0; i < 22; i++) await step(ramp(i));
  assert.equal(bot.status().running, false);
  assert.match(bot.status().stopReason, /Kill/);
  assert.equal(conn.s.orders.length, 0);
});
test("bot: non-demo account stops the bot", async () => {
  const { bot, conn, step } = mkBot();
  conn.s.info = { type: "ACCOUNT_TRADE_MODE_REAL" };
  bot.start();
  for (let i = 0; i < 22; i++) await step(ramp(i));
  assert.equal(bot.status().running, false);
  assert.equal(conn.s.orders.length, 0);
});
test("bot: a broker error is recorded, never crashes, and cannot double-send", async () => {
  const { bot, conn, step } = mkBot();
  conn.createMarketBuyOrder = async () => { throw new Error("timeout"); };
  for (let i = 0; i < 26; i++) await step(ramp(i));
  assert.match(bot.status().lastError, /timeout/);
  let calls = 0;
  conn.createMarketBuyOrder = async () => { calls++; return { orderId: "1", positionId: "1" }; };
  for (let i = 26; i < 30; i++) await step(ramp(i));
  assert.equal(calls, 0); // cooldown held after the ambiguous failure
});
test("bot: config validation", () => {
  const { bot } = mkBot();
  assert.equal(bot.setConfig({ strategies: ["trend", "zscore"] }).ok, true);
  for (const bad of [{ strategies: ["toString"] }, { strategies: "trend" }, { lots: 0.5 }, { lots: 0 }, { lots: "x" },
    { slPips: 1 }, { slPips: 500 }, { tpPips: 1 }, { cooldownMin: -1 }]) {
    assert.equal(bot.setConfig(bad).ok, false, JSON.stringify(bad));
  }
  assert.deepEqual(bot.status().config.strategies, ["trend", "zscore"]);
  assert.equal(bot.setConfig({ tpPips: 0 }).ok, true);
});
test("bot: start/stop run the loop once and stop cleanly", async () => {
  const { bot, conn } = mkBot({ botIntervalSec: 3 });
  bot.start(); bot.start();
  await new Promise(r => setTimeout(r, 50));
  assert.equal(bot.status().ticks, 1);
  assert.equal(bot.status().running, true);
  bot.stop("Stopped by you");
  assert.equal(bot.status().running, false);
  assert.equal(conn.s.orders.length, 0);
});

/* ------------------------------------------------------ added after review */
test("trading: simultaneous orders cannot slip past the open-position limit", async () => {
  const { t, conn } = mk({ maxOpen: 1 });
  const results = await Promise.all([t.placeOrder(good({ clientId: "a1" })), t.placeOrder(good({ clientId: "a2" })), t.placeOrder(good({ clientId: "a3" }))]);
  assert.deepEqual(results.map(r => r.ok), [true, false, false]);
  assert.equal(conn.s.orders.length, 1);
  assert.equal(results[1].kind, "limit_open");
});
test("trading: a failed order does not block the queue behind it", async () => {
  const { t, conn } = mk();
  conn.createMarketBuyOrder = async () => { throw new Error("boom"); };
  await assert.rejects(t.placeOrder(good()), /boom/);
  conn.createMarketBuyOrder = async () => ({ orderId: "5", positionId: "5", stringCode: "OK" });
  assert.equal((await t.placeOrder(good({ clientId: "b1" }))).ok, true);
});
test("bot: an order error stays visible and clears after the next successful order", async () => {
  const { bot, conn, step } = mkBot({ botCooldownMin: 0 });
  let fail = true;
  conn.createMarketBuyOrder = async () => { if (fail) throw new Error("requote"); return { orderId: "7", positionId: "7", stringCode: "OK" }; };
  for (let i = 0; i < 22; i++) await step(ramp(i));
  assert.match(bot.status().lastError, /requote/);
  fail = false;
  conn.s.positions = [];
  for (let i = 22; i < 30; i++) await step(ramp(i));
  assert.equal(bot.status().lastError, "");
});
