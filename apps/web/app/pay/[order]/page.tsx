"use client";

import { useParams } from "next/navigation";
import { useCallback, useEffect, useState, type ReactNode } from "react";
import { api, ApiError, type Payment, type PaymentMethod } from "@/lib/api";

/** Posts the signed form to PayWay, like a normal checkout button; PayWay's card page takes over the tab. */
function submitCheckout({ action, fields }: NonNullable<Payment["checkout"]>) {
  const form = document.createElement("form");
  form.method = "POST";
  form.action = action;
  form.enctype = "multipart/form-data";
  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();
}

export default function PayPage() {
  const { order } = useParams<{ order: string }>();
  const [payment, setPayment] = useState<Payment | null>(null);
  const [error, setError] = useState("");
  const [redirecting, setRedirecting] = useState(false);
  const [now, setNow] = useState(() => Date.now());

  // KHQR: create the QR, or reuse the open one. Card: get a signed form and go to PayWay.
  // 409 = order already paid, so just show it.
  const startPayment = useCallback(
    async (method: PaymentMethod) => {
      try {
        const result = await api<Payment>(`/orders/${order}/pay`, {
          method: "POST",
          body: JSON.stringify({ method }),
        });
        if (result.checkout) {
          setRedirecting(true);
          submitCheckout(result.checkout);
        } else {
          setPayment(result);
        }
      } catch (e) {
        if (e instanceof ApiError && e.status === 409) {
          setPayment(await api<Payment>(`/orders/${order}/payment`));
        } else {
          setError(e instanceof Error ? e.message : "Could not start the payment.");
        }
      }
    },
    [order],
  );

  const restart = (method: PaymentMethod) => {
    setPayment(null);
    setError("");
    void startPayment(method);
  };

  // ?returned=1: back from PayWay's card page, so only check the status.
  // Otherwise start the method chosen on /shop (?method=card), KHQR by default.
  // Deferred so React's dev-mode remount cancels the first run instead of starting twice.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const timer = setTimeout(() => {
      if (params.has("returned")) {
        api<Payment>(`/orders/${order}/payment`)
          .then(setPayment)
          .catch((e) => setError(e instanceof Error ? e.message : "Could not load the payment."));
      } else {
        void startPayment(params.get("method") === "card" ? "card" : "khqr");
      }
    });
    return () => clearTimeout(timer);
  }, [order, startPayment]);

  // While waiting: ask the API every 3 s (also after expiry, in case staff approve it); tick the countdown.
  const pending = payment?.status === "pending";
  const waiting = pending || payment?.status === "expired";
  useEffect(() => {
    if (!waiting) return;
    const poll = setInterval(() => {
      api<Payment>(`/orders/${order}/payment`).then(setPayment).catch(() => {});
    }, 3000);
    const tick = setInterval(() => setNow(Date.now()), 1000);
    return () => {
      clearInterval(poll);
      clearInterval(tick);
    };
  }, [order, waiting]);

  if (error) return <Shell><p className="text-red-600">{error}</p></Shell>;
  if (redirecting) return <Shell><p>Taking you to ABA PayWay&apos;s secure card page…</p></Shell>;
  if (!payment) return <Shell><p>Starting your payment…</p></Shell>;

  const left = Math.max(0, Math.floor((Date.parse(payment.expires_at) - now) / 1000));
  const countdown = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, "0")}`;
  const isCard = payment.method === "card";

  return (
    <Shell>
      <p className="text-sm text-zinc-500">Order {payment.order.order_no}</p>
      <p className="text-3xl font-semibold tabular-nums">
        {payment.amount} {payment.currency}
      </p>

      {payment.status === "approved" && (
        <>
          <p className="text-lg text-green-600">Paid. Thank you!</p>
          <a href={`${process.env.NEXT_PUBLIC_API_URL}/api/orders/${order}/invoice`} className="underline">
            Download invoice (PDF)
          </a>
          <p className="text-sm text-zinc-500">If you gave an email, a receipt is on its way.</p>
        </>
      )}

      {pending && !isCard && (
        <>
          {payment.qr_image && (
            // eslint-disable-next-line @next/next/no-img-element -- data: URI from PayWay
            <img src={payment.qr_image} alt="ABA KHQR" className="w-64" />
          )}
          <p>Scan with ABA Mobile or any KHQR bank app.</p>
          <p className="tabular-nums text-sm">{left > 0 ? `Expires in ${countdown}` : "Expired, checking…"}</p>
          {payment.deeplink && <a href={payment.deeplink} className="underline">Open in ABA Mobile</a>}
        </>
      )}

      {pending && isCard && (
        <>
          <p>Checking your card payment with ABA…</p>
          <p className="tabular-nums text-sm">
            {left > 0 ? `Payment window closes in ${countdown}` : "Payment window closed, checking…"}
          </p>
          <p className="text-sm text-zinc-500">Not finished on the card page?</p>
          <div className="flex gap-2">
            <Button onClick={() => restart("card")}>Continue to card payment</Button>
            <Button onClick={() => restart("khqr")} secondary>
              Pay with KHQR instead
            </Button>
          </div>
        </>
      )}

      {(payment.status === "expired" || payment.status === "failed") && (
        <>
          <p>
            {payment.status === "failed"
              ? "Payment failed."
              : isCard
                ? "The card payment wasn't completed in time."
                : "This QR expired."}
          </p>
          <div className="flex gap-2">
            <Button onClick={() => restart("khqr")}>New QR</Button>
            <Button onClick={() => restart("card")} secondary>
              Pay by card
            </Button>
          </div>
        </>
      )}

      {payment.status !== "approved" && (
        <p className="text-xs text-zinc-500">
          Test tran_id: <code>{payment.tran_id}</code>
        </p>
      )}
    </Shell>
  );
}

function Button({ onClick, secondary, children }: { onClick: () => void; secondary?: boolean; children: ReactNode }) {
  return (
    <button
      onClick={onClick}
      className={
        secondary
          ? "rounded border border-zinc-400 px-4 py-2 dark:border-zinc-600"
          : "rounded bg-zinc-900 px-4 py-2 text-white dark:bg-zinc-100 dark:text-zinc-900"
      }
    >
      {children}
    </button>
  );
}

function Shell({ children }: { children: ReactNode }) {
  return (
    <main className="mx-auto flex w-full max-w-md flex-1 flex-col items-center gap-3 px-4 py-10 text-center">
      {children}
    </main>
  );
}
