// Fake ABA PayWay for local payment tests. Serves generate-qr and check-transaction-2; the hash is not checked.
// Check result: APPROVED (default), PENDING, DECLINED, NOT_FOUND, WRONG_AMOUNT.
// Switch without restarting: POST http://localhost:4010/mock/scenario {"scenario":"PENDING"}
const http = require("http");

const BASE = "/api/payment-gateway/v1/payments";
const SCENARIOS = ["APPROVED", "PENDING", "DECLINED", "NOT_FOUND", "WRONG_AMOUNT"];
const STATUS_CODES = { APPROVED: 0, WRONG_AMOUNT: 0, PENDING: 2, DECLINED: 3 };

let scenario = SCENARIOS.includes(process.env.PAYWAY_MOCK_SCENARIO) ? process.env.PAYWAY_MOCK_SCENARIO : "APPROVED";
const qrs = new Map(); // tran_id -> { amount, currency }; cleared on restart

const qrImage = (tranId) =>
  "data:image/svg+xml;base64," +
  Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240"><rect width="240" height="240" fill="#eee"/>` +
      `<text x="120" y="115" font-family="sans-serif" font-size="22" text-anchor="middle">MOCK KHQR</text>` +
      `<text x="120" y="145" font-family="monospace" font-size="12" text-anchor="middle">${tranId}</text></svg>`,
  ).toString("base64");

function generateQr(body) {
  const qr = { amount: Number(body.amount), currency: body.currency || "USD" };
  qrs.set(body.tran_id, qr);
  return {
    status: { code: "0", message: "Success.", tran_id: body.tran_id },
    ...qr,
    qrString: `MOCK-KHQR-${body.tran_id}`,
    qrImage: qrImage(body.tran_id),
    abapay_deeplink: null,
  };
}

function checkTransaction(body) {
  const qr = qrs.get(body.tran_id);
  if (!qr || scenario === "NOT_FOUND") {
    return { status: { code: 6, message: "Transaction not found", tran_id: body.tran_id } };
  }
  const paid = scenario === "APPROVED" || scenario === "WRONG_AMOUNT";
  return {
    data: {
      payment_status_code: STATUS_CODES[scenario],
      payment_status: paid ? "APPROVED" : scenario,
      total_amount: qr.amount,
      original_amount: qr.amount,
      payment_amount: !paid ? 0 : scenario === "WRONG_AMOUNT" ? qr.amount + 1 : qr.amount,
      payment_currency: paid ? qr.currency : "",
      apv: paid ? String(100000 + Math.floor(Math.random() * 900000)) : "",
      transaction_date: new Date().toISOString().slice(0, 19).replace("T", " "),
    },
    status: { code: "00", message: "Success!", tran_id: body.tran_id },
  };
}

http
  .createServer((req, res) => {
    let raw = "";
    req.on("data", (chunk) => (raw += chunk));
    req.on("end", () => {
      const send = (code, json) => {
        res.writeHead(code, { "Content-Type": "application/json" });
        res.end(JSON.stringify(json));
      };
      let body = {};
      try {
        body = JSON.parse(raw || "{}");
      } catch {}
      const path = new URL(req.url, "http://mock").pathname;

      if (path === "/mock/scenario") {
        if (req.method === "GET") return send(200, { scenario });
        if (!SCENARIOS.includes(body.scenario)) return send(422, { error: `scenario must be one of ${SCENARIOS.join(", ")}` });
        scenario = body.scenario;
        console.log(`scenario -> ${scenario}`);
        return send(200, { scenario });
      }
      if (req.method === "POST" && path === `${BASE}/generate-qr`) return send(200, generateQr(body));
      if (req.method === "POST" && path === `${BASE}/check-transaction-2`) return send(200, checkTransaction(body));
      send(404, { error: "Endpoint not defined" });
    });
  })
  .listen(4010, () => console.log(`PayWay mock on :4010, scenario ${scenario}`));
