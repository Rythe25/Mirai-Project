"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api, type Product } from "@/lib/api";

const field = "rounded border border-zinc-300 p-2 dark:border-zinc-700 dark:bg-zinc-900";

export default function BuyForm({ products }: { products: Product[] }) {
  const router = useRouter();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = new FormData(e.currentTarget);
    setBusy(true);
    setError("");
    try {
      const order = await api<{ public_id: string }>("/orders", {
        method: "POST",
        body: JSON.stringify({
          customer: { name: form.get("name"), phone: form.get("phone") },
          items: [{ product_id: Number(form.get("product")), qty: 1 }],
        }),
      });
      router.push(`/pay/${order.public_id}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong.");
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
      <fieldset className="flex flex-col gap-2">
        {products.map((p, i) => (
          <label key={p.id} className="flex items-center gap-3 rounded border border-zinc-300 p-3 dark:border-zinc-700">
            <input type="radio" name="product" value={p.id} defaultChecked={i === 0} disabled={!p.in_stock} required />
            <span className="flex-1">{p.name}</span>
            <span className="tabular-nums">{p.in_stock ? `$${p.price}` : "Sold out"}</span>
          </label>
        ))}
      </fieldset>
      <input name="name" placeholder="Name" required maxLength={120} className={field} />
      <input name="phone" type="tel" placeholder="Phone, e.g. 012345678" required pattern="\+?[0-9 ]{8,20}" className={field} />
      {error && <p className="text-sm text-red-600">{error}</p>}
      <button disabled={busy} className="rounded bg-zinc-900 p-3 text-white disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900">
        {busy ? "Creating order…" : "Pay with ABA KHQR"}
      </button>
    </form>
  );
}
