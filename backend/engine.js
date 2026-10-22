"use strict";
/*
 * Strategy signals. Pure functions, no network. These are simple demo rules on
 * sampled prices: they are NOT evidence of profitability. Thresholds are in pips
 * so they scale with the symbol.
 */
const STRATEGIES = Object.freeze({
  trend: "Trend Following",
  meanrev: "Mean Reversion",
  breakout: "Breakout Trading",
  zscore: "Z-score Reversion"
});

const avg = (a, n) => { const s = a.slice(-n); return s.reduce((x, y) => x + y, 0) / s.length; };
const pipSize = symbol => (/JPY$/.test(symbol) ? 0.01 : 0.0001);

// prices: array of mid prices, oldest first. Returns "BUY" | "SELL" | "WAIT".
function signal(id, prices, pip) {
  if (prices.length < 20) return "WAIT";
  const p = prices[prices.length - 1];
  switch (id) {
    case "trend": {
      const d = avg(prices, 5) - avg(prices, 15);
      return d > 0.4 * pip ? "BUY" : d < -0.4 * pip ? "SELL" : "WAIT";
    }
    case "meanrev": {
      const d = p - avg(prices, 15);
      return d > 2.5 * pip ? "SELL" : d < -2.5 * pip ? "BUY" : "WAIT";
    }
    case "breakout": {
      const prev = prices.slice(-11, -1);
      return p > Math.max(...prev) ? "BUY" : p < Math.min(...prev) ? "SELL" : "WAIT";
    }
    case "zscore": {
      const w = prices.slice(-20), m = avg(w, 20);
      // floor the deviation so a flat market does not turn one-tick noise into a signal
      const sd = Math.max(Math.sqrt(w.reduce((a, x) => a + (x - m) ** 2, 0) / w.length), 0.2 * pip);
      const z = (p - m) / sd;
      return z > 1.5 ? "SELL" : z < -1.5 ? "BUY" : "WAIT";
    }
    default:
      return "WAIT";
  }
}

module.exports = { STRATEGIES, signal, pipSize, avg };
