"use client";

import { useParams } from "next/navigation";
import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import { api, ApiError, type Payment } from "@/lib/api";

export default function PayPage() {
  const { order } = useParams<{ order: string }>();
  const [payment, setPayment] = useState<Payment | null>(null);
  const [error, setError] = useState("");
  const [now, setNow] = useState(() => Date.now());
  const started = useRef(false);

  // Create the QR, or reuse the open one. 409 = order already paid, so just show it.
  const startPayment = useCallback(async () => {
    try {
      setPayment(await api<Payment>(`/orders/${order}/pay`, { method: "POST" }));
    } catch (e) {
      if (e instanceof ApiError && e.status === 409) {
        setPayment(await api<Payment>(`/orders/${order}/payment`));
      } else {
        setError(e instanceof Error ? e.message : "Could not create the QR.");
      }
    }
  }, [order]);

  useEffect(() => {
    if (started.current) return;
    started.current = true;
    void startPayment();
  }, [startPayment]);

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
  if (!payment) return <Shell><p>Creating your KHQR…</p></Shell>;

  const left = Math.max(0, Math.floor((Date.parse(payment.expires_at) - now) / 1000));

  return (
    <Shell>
      <p className="text-sm text-zinc-500">Order {payment.order.order_no}</p>
      <p className="text-3xl font-semibold tabular-nums">
        {payment.amount} {payment.currency}
      </p>

      {payment.status === "approved" && <p className="text-lg text-green-600">Paid. Thank you!</p>}

      {pending && (
        <>
          {payment.qr_image && (
            // eslint-disable-next-line @next/next/no-img-element -- data: URI from PayWay
            <img src={payment.qr_image} alt="ABA KHQR" className="w-64" />
          )}
          <p>Scan with ABA Mobile or any KHQR bank app.</p>
          <p className="tabular-nums text-sm">
            {left > 0 ? `Expires in ${Math.floor(left / 60)}:${String(left % 60).padStart(2, "0")}` : "Expired, checking…"}
          </p>
          {payment.deeplink && <a href={payment.deeplink} className="underline">Open in ABA Mobile</a>}
          <p className="text-xs text-zinc-500">
            Test tran_id: <code>{payment.tran_id}</code>
          </p>
        </>
      )}

      {(payment.status === "expired" || payment.status === "failed") && (
        <>
          <p>{payment.status === "expired" ? "This QR expired." : "Payment failed."}</p>
          <button
            onClick={() => {
              setPayment(null);
              setError("");
              void startPayment();
            }}
            className="rounded bg-zinc-900 px-4 py-2 text-white dark:bg-zinc-100 dark:text-zinc-900"
          >
            New QR
          </button>
        </>
      )}
    </Shell>
  );
}

function Shell({ children }: { children: ReactNode }) {
  return (
    <main className="mx-auto flex w-full max-w-md flex-1 flex-col items-center gap-3 px-4 py-10 text-center">
      {children}
    </main>
  );
}
