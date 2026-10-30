import type { Metadata } from "next";
import { signInNotices, signInUrl } from "@/lib/auth";

export const metadata: Metadata = { title: "Mirai — Sign in" };

export default async function SignIn({ searchParams }: PageProps<"/signin">) {
  const params = await searchParams;
  const back = typeof params.return === "string" && params.return.startsWith("/") ? params.return : "/";
  const notice = typeof params.signin === "string" ? signInNotices[params.signin] : undefined;

  return (
    <main className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-4 px-4 py-10">
      <h1 className="text-2xl font-semibold">Sign in to Mirai</h1>
      <p className="text-sm text-zinc-500">Sign in to check out. New here? Continuing with Google creates your account.</p>
      {notice && <p className="text-sm text-red-600">{notice}</p>}
      <a
        href={signInUrl(back)}
        className="rounded bg-zinc-900 p-3 text-center text-white dark:bg-zinc-100 dark:text-zinc-900"
      >
        Continue with Google
      </a>
    </main>
  );
}
