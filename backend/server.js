"use strict";
/*
 * 5G Network backend: a thin, safety-first bridge between the web app and an
 * MT5 DEMO account via MetaApi, plus a 24/7 server-side auto-trader.
 * All secrets live in environment variables. Trading is OFF unless
 * TRADING_ENABLED=true, and non-demo accounts are always refused.
 */
const express = require("express");
const cors = require("cors");
const crypto = require("crypto");
const MetaApi = require("metaapi.cloud-sdk").default;
const { STRATEGIES } = require("./engine");
const { createTrading } = require("./trading");
const { createBot } = require("./bot");

const env = process.env;
const num = (v, d) => (v === undefined || v === "" || !isFinite(Number(v)) ? d : Number(v));
const CFG = {
  port: Number(env.PORT) || 3000,
  token: env.METAAPI_TOKEN,
  accountId: env.METAAPI_ACCOUNT_ID,
  apiKey: env.API_KEY,
  origin: env.ALLOWED_ORIGIN,
  tradingEnabled: env.TRADING_ENABLED === "true",
  symbols: (env.SYMBOLS || "EURUSD").split(",").map(s => s.trim().toUpperCase()).filter(Boolean),
  maxLots: num(env.MAX_LOTS, 0.01) || 0.01,
  maxOpen: num(env.MAX_OPEN_POSITIONS, 2) || 2,
  maxTradesDay: num(env.MAX_TRADES_PER_DAY, 5) || 5,
  maxDailyLoss: num(env.MAX_DAILY_LOSS, 30) || 30,
  // ---- auto-trader ----
  botStrategies: (env.BOT_STRATEGIES || "trend").split(",").map(s => s.trim()).filter(Boolean),
  botLots: num(env.BOT_LOTS, 0.01),
  botSlPips: num(env.BOT_SL_PIPS, 15),
  botTpPips: num(env.BOT_TP_PIPS, 30),
  botCooldownMin: num(env.BOT_COOLDOWN_MIN, 30),
  botIntervalSec: Math.max(3, num(env.BOT_INTERVAL_SEC, 10)),
  botMaxSpreadPips: num(env.BOT_MAX_SPREAD_PIPS, 1.5),
  botAutostart: env.BOT_AUTOSTART === "true",
  // Render's free tier sleeps after 15 min with no visitors, which would stop the bot.
  // KEEPALIVE=true makes the server request its own /health every 10 minutes while it is awake.
  keepaliveUrl: env.KEEPALIVE === "true" ? (env.KEEPALIVE_URL || env.RENDER_EXTERNAL_URL || "") : "",
  keepaliveWanted: env.KEEPALIVE === "true"
};
const STARTED_AT = new Date().toISOString();

const missing = ["METAAPI_TOKEN", "METAAPI_ACCOUNT_ID", "API_KEY", "ALLOWED_ORIGIN"].filter(k => !env[k]);
if (missing.length) { console.error("Missing environment variables: " + missing.join(", ")); process.exit(1); }
if (CFG.apiKey.length < 24) { console.error("API_KEY must be at least 24 characters."); process.exit(1); }
if (CFG.botStrategies.some(s => !Object.hasOwn(STRATEGIES, s))) { console.error("BOT_STRATEGIES must be from: " + Object.keys(STRATEGIES).join(", ")); process.exit(1); }
if (!(CFG.botLots > 0) || CFG.botLots > CFG.maxLots) { console.error("BOT_LOTS must be above 0 and not more than MAX_LOTS (" + CFG.maxLots + ")."); process.exit(1); }

/* ---------- MetaApi connection (lazy, self-healing) ---------- */
let connPromise = null;
function getConn() {
  if (!connPromise) {
    connPromise = (async () => {
      const api = new MetaApi(CFG.token);
      const account = await api.metatraderAccountApi.getAccount(CFG.accountId);
      if (account.state !== "DEPLOYED") await account.deploy();
      await account.waitConnected();
      const c = account.getRPCConnection();
      await c.connect();
      await c.waitSynchronized();
      return c;
    })().catch(e => { connPromise = null; throw e; });
  }
  return connPromise;
}

const trading = createTrading({ cfg: CFG, getConn });
const bot = createBot({ cfg: CFG, getConn, placeOrder: trading.placeOrder });

/* ---------- App, security middleware ---------- */
const app = express();
app.disable("x-powered-by");
app.set("trust proxy", 1); // hosts such as Render sit behind a proxy; this makes req.ip the real client
app.use(cors({ origin: CFG.origin, methods: ["GET", "POST"], allowedHeaders: ["Content-Type", "x-api-key"] }));
app.use(express.json({ limit: "10kb" }));

const hits = new Map(); // simple per-IP rate limit
setInterval(() => hits.clear(), 60000).unref();
app.use((req, res, next) => {
  const n = (hits.get(req.ip) || 0) + 1; hits.set(req.ip, n);
  if (n > 180) return res.status(429).json({ error: "Too many requests" });
  next();
});

function auth(req, res, next) {
  const given = Buffer.from(String(req.get("x-api-key") || ""));
  const want = Buffer.from(CFG.apiKey);
  if (given.length !== want.length || !crypto.timingSafeEqual(given, want)) return res.status(401).json({ error: "Unauthorized" });
  next();
}
const wrap = fn => (req, res) => fn(req, res).catch(e => {
  console.error(req.method, req.path, e && e.message);
  // MetaApi trade rejections (e.g. market closed) are safe and useful to show
  if (e && (e.name === "TradeError" || e.stringCode)) return res.status(422).json({ error: String(e.message || e.stringCode) });
  res.status(502).json({ error: "Broker bridge error. Try again shortly." });
});
const cleanSymbol = s => String(s || "").replace("/", "").toUpperCase();

/* ---------- Routes ---------- */
app.get("/health", (req, res) => res.json({ ok: true }));

app.get("/api/status", auth, wrap(async (req, res) => {
  const info = await (await getConn()).getAccountInformation();
  res.json({
    demoAccount: trading.isDemo(info), tradingEnabled: CFG.tradingEnabled, killed: trading.isKilled(),
    botRunning: bot.status().running, startedAt: STARTED_AT,
    limits: { symbols: CFG.symbols, maxLots: CFG.maxLots, maxOpen: CFG.maxOpen, maxTradesPerDay: CFG.maxTradesDay, maxDailyLoss: CFG.maxDailyLoss }
  });
}));

app.get("/api/account", auth, wrap(async (req, res) => {
  const c = await getConn(), i = await c.getAccountInformation();
  res.json({ balance: i.balance, equity: i.equity, margin: i.margin, freeMargin: i.freeMargin, currency: i.currency, server: i.server, leverage: i.leverage, demo: trading.isDemo(i), today: await trading.dayStats(c) });
}));

app.get("/api/price/:symbol", auth, wrap(async (req, res) => {
  const symbol = cleanSymbol(req.params.symbol);
  if (!CFG.symbols.includes(symbol)) return res.status(400).json({ error: "Symbol not allowed" });
  const p = await (await getConn()).getSymbolPrice(symbol);
  res.json({ symbol, bid: p.bid, ask: p.ask, time: p.time });
}));

app.get("/api/positions", auth, wrap(async (req, res) => {
  const list = await (await getConn()).getPositions();
  res.json(list.map(p => ({ id: p.id, symbol: p.symbol, type: p.type, volume: p.volume, openPrice: p.openPrice, currentPrice: p.currentPrice, stopLoss: p.stopLoss, takeProfit: p.takeProfit, profit: p.profit, time: p.time })));
}));

app.get("/api/history", auth, wrap(async (req, res) => {
  const days = Math.min(30, Math.max(1, parseInt(req.query.days, 10) || 5));
  res.json(await trading.history(days));
}));

app.post("/api/order", auth, wrap(async (req, res) => {
  const b = req.body || {};
  const r = await trading.placeOrder({
    symbol: cleanSymbol(b.symbol), side: String(b.side || "").toLowerCase(),
    lots: Number(b.lots), sl: Number(b.sl), tp: b.tp == null ? null : Number(b.tp),
    clientId: String(b.clientId || "5GN_" + crypto.randomBytes(6).toString("hex")), comment: "5G Network"
  });
  if (!r.ok) return res.status(r.status).json({ error: r.error });
  res.json({ ok: true, orderId: r.orderId, positionId: r.positionId, message: r.message });
}));

app.post("/api/close", auth, wrap(async (req, res) => {
  const id = String((req.body || {}).positionId || "");
  if (!/^\d{1,20}$/.test(id)) return res.status(400).json({ error: "Invalid positionId" });
  const r = await (await getConn()).closePosition(id);
  res.json({ ok: true, message: r.stringCode });
}));

/* ---- auto-trader ---- */
app.get("/api/bot", auth, (req, res) =>
  res.json({ ...bot.status(), killed: trading.isKilled(), limits: { maxLots: CFG.maxLots, maxOpen: CFG.maxOpen, maxTradesPerDay: CFG.maxTradesDay, maxDailyLoss: CFG.maxDailyLoss } }));

app.post("/api/bot/start", auth, (req, res) => {
  if (trading.isKilled()) return res.status(403).json({ error: "Kill switch is active. Call /api/resume first." });
  bot.start();
  res.json(bot.status());
});

app.post("/api/bot/stop", auth, (req, res) => { bot.stop("Stopped by you"); res.json(bot.status()); });

app.post("/api/bot/config", auth, (req, res) => {
  const r = bot.setConfig(req.body);
  if (!r.ok) return res.status(400).json({ error: r.error });
  res.json(bot.status());
});

// Emergency stop: block new orders, stop the bot; optionally close everything.
app.post("/api/kill", auth, wrap(async (req, res) => {
  trading.setKilled(true);
  bot.stop("Kill switch");
  let closed = 0;
  const errors = [];
  if ((req.body || {}).closeAll === true) {
    const c = await getConn();
    for (const p of await c.getPositions()) {
      try { await c.closePosition(String(p.id)); closed++; } catch (e) { errors.push(String(p.id)); }
    }
  }
  res.json({ ok: errors.length === 0, killed: true, closed, failedToClose: errors });
}));

app.post("/api/resume", auth, (req, res) => { trading.setKilled(false); res.json({ ok: true, killed: false }); });

app.use((req, res) => res.status(404).json({ error: "Not found" }));
app.listen(CFG.port, () => {
  console.log("5G Network backend on :" + CFG.port + " | trading " + (CFG.tradingEnabled ? "ENABLED (demo only)" : "disabled (read-only / dry-run)"));
  if (CFG.botAutostart) { bot.start(); console.log("Auto-trader started (BOT_AUTOSTART=true): " + CFG.botStrategies.join(", ")); }
  if (CFG.keepaliveUrl) {
    const url = CFG.keepaliveUrl.replace(/\/+$/, "") + "/health";
    setInterval(() => { fetch(url).catch(e => console.error("keepalive failed:", e && e.message)); }, 10 * 60 * 1000).unref();
    console.log("Keepalive on: pinging " + url + " every 10 minutes");
  } else if (CFG.keepaliveWanted) {
    console.warn("KEEPALIVE=true but no URL found. Set KEEPALIVE_URL to your server's public https address.");
  }
});
