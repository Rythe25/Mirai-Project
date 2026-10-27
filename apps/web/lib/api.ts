export type Product = {
  id: number;
  name: string;
  price: string;
  currency: string;
  stock: number;
  in_stock: boolean;
};

export type PaymentMethod = "khqr" | "card";

export type Payment = {
  method: PaymentMethod;
  tran_id: string;
  status: "pending" | "approved" | "failed" | "expired";
  amount: string;
  currency: string;
  qr_image: string | null;
  deeplink: string | null;
  /** Signed PayWay card checkout form; only present right after a card payment is started. */
  checkout?: { action: string; fields: Record<string, string> };
  expires_at: string;
  paid_at: string | null;
  order: { public_id: string; order_no: string; status: string };
};

export class ApiError extends Error {
  status: number;

  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

/** Calls the Mirai API and returns the resource's `data`. */
export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api${path}`, {
    ...init,
    cache: "no-store",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(res.status, body.message ?? `Request failed (${res.status})`);
  return body.data as T;
}
