export const dynamic = "force-dynamic";

export default async function Ping() {
  const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/ping`, {
    cache: "no-store",
  });
  const data = await res.json();
  return <pre className="p-8 text-sm">{JSON.stringify(data, null, 2)}</pre>;
}
