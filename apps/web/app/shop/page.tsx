import type { Metadata } from "next";
import { api, type Product } from "@/lib/api";
import BuyForm from "./buy-form";

export const dynamic = "force-dynamic";

export const metadata: Metadata = { title: "Mirai — Test shop" };

export default async function Shop() {
  const products = await api<Product[]>("/products");

  return (
    <main className="mx-auto w-full max-w-md flex-1 px-4 py-10">
      <h1 className="text-2xl font-semibold">Mirai test shop</h1>
      <p className="mt-1 text-sm text-zinc-500">ABA KHQR sandbox payments only. Nothing ships.</p>
      {products.length ? <BuyForm products={products} /> : <p className="mt-6">No products yet.</p>}
    </main>
  );
}
