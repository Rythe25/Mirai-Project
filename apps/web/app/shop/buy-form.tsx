"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api, type PaymentMethod, type Product } from "@/lib/api";
import { signInPage } from "@/lib/auth";
import { useMe } from "@/lib/use-me";

const field = "rounded border border-zinc-300 p-2 dark:border-zinc-700 dark:bg-zinc-900";

type Props = { products: Product[]; selected?: number; method: PaymentMethod };

export default function BuyForm({ products, selected, method }: Props) {
  const router = useRouter();
  const me = useMe();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const preselected = products.find((p) => p.id === selected && p.in_stock)?.id ?? products.find((p) => p.in_stock)?.id;

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = new FormData(e.currentTarget);

    // Guests browse and choose freely; checkout needs an account. Come back with the same choice.
    if (!me) {
      router.push(signInPage(`/shop?product=${form.get("product")}&method=${form.get("method")}`));
      return;
    }

    setBusy(true);
    setError("");
    try {
      const order = await api<{ public_id: string }>("/orders", {
        method: "POST",
        body: JSON.stringify({
          customer: { name: form.get("name"), phone: form.get("phone"), email: form.get("email") || null },
          items: [{ product_id: Number(form.get("product")), qty: 1 }],
        }),
      });
      router.push(`/pay/${order.public_id}?method=${form.get("method")}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong.");
      setBusy(false);
    }
  }

  // Keyed on the user so name and email are prefilled once /me answers.
  return (
    <form key={me?.id ?? "guest"} onSubmit={submit} className="mt-6 flex flex-col gap-4">
      <fieldset className="flex flex-col gap-2">
        {products.map((p) => (
          <label key={p.id} className="flex items-center gap-3 rounded border border-zinc-300 p-3 dark:border-zinc-700">
            <input type="radio" name="product" value={p.id} defaultChecked={p.id === preselected} disabled={!p.in_stock} required />
            <span className="flex-1">{p.name}</span>
            <span className="tabular-nums">{p.in_stock ? `$${p.price}` : "Sold out"}</span>
          </label>
        ))}
      </fieldset>
      <input name="name" placeholder="Name" required={!!me} maxLength={120} defaultValue={me?.name} className={field} />
      <input name="phone" type="tel" placeholder="Phone, e.g. 012345678" required={!!me} pattern="\+?[0-9 ]{8,20}" className={field} />
      <input name="email" type="email" placeholder="Email for your receipt (optional)" maxLength={190} defaultValue={me?.email} className={field} />
      <fieldset className="flex gap-4 text-sm">
        <label className="flex items-center gap-2">
          <input type="radio" name="method" value="khqr" defaultChecked={method === "khqr"} /> ABA KHQR
        </label>
        <label className="flex items-center gap-2">
          <input type="radio" name="method" value="card" defaultChecked={method === "card"} /> Credit / debit card
        </label>
      </fieldset>
      {error && <p className="text-sm text-red-600">{error}</p>}
      <button disabled={busy || me === undefined} className="rounded bg-zinc-900 p-3 text-white disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900">
        {busy ? "Creating order…" : "Check out"}
      </button>
    </form>
  );
}
