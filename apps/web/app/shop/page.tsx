import type { Metadata } from "next";
import { api, type Product } from "@/lib/api";
import BuyForm from "./buy-form";

export const dynamic = "force-dynamic";

export const metadata: Metadata = { title: "Mirai — Test shop" };

export default async function Shop({ searchParams }: PageProps<"/shop">) {
  const products = await api<Product[]>("/products");
  // Coming back from sign-in: keep the customer's choice.
  const { product, method } = await searchParams;

  return (
    <main className="mx-auto w-full max-w-md flex-1 px-4 py-10">
      <h1 className="text-2xl font-semibold">Mirai test shop</h1>
      <p className="mt-1 text-sm text-zinc-500">ABA KHQR sandbox payments only. Nothing ships. Sign in to check out.</p>
      {products.length ? (
        <BuyForm
          products={products}
          selected={Number(product) || undefined}
          method={method === "card" ? "card" : "khqr"}
        />
      ) : (
        <p className="mt-6">No products yet.</p>
      )}
    </main>
  );
}
