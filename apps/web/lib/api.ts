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

const API = process.env.NEXT_PUBLIC_API_URL;
let csrfToken: string | null = null;

/** The API's CSRF token. Its cookie lives on the API host, so the storefront asks for it instead of reading it. */
async function csrf(): Promise<string> {
  if (!csrfToken) {
    const res = await fetch(`${API}/api/csrf`, { credentials: "include", cache: "no-store" });
    csrfToken = (await res.json()).token as string;
  }
  return csrfToken;
}

/** Calls the Mirai API (with the sign-in cookie) and returns the resource's `data`. */
export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const write = (init.method ?? "GET").toUpperCase() !== "GET";
  const send = async () =>
    fetch(`${API}/api${path}`, {
      ...init,
      cache: "no-store",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(write ? { "X-CSRF-TOKEN": await csrf() } : {}),
      },
    });

  let res = await send();
  if (res.status === 419) {
    // Session expired: fetch a fresh token and try once more.
    csrfToken = null;
    res = await send();
  }
  if (res.status === 204) return undefined as T;
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(res.status, body.message ?? `Request failed (${res.status})`);
  return body.data as T;
}
