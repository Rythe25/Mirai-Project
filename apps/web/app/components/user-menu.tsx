"use client";

import { useEffect, useState } from "react";
import { signInNotices, signInUrl } from "@/lib/auth";
import { signOut, useMe } from "@/lib/use-me";

const pill = "rounded-full border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm";

/** Top-right Sign in / account menu, on every page. */
export default function UserMenu() {
  const me = useMe();
  const [notice, setNotice] = useState("");

  useEffect(() => {
    // Read after mount (no useSearchParams, so static pages stay static).
    const status = new URLSearchParams(window.location.search).get("signin") ?? "";
    const timer = setTimeout(() => setNotice(signInNotices[status] ?? ""));
    return () => clearTimeout(timer);
  }, []);

  function signIn() {
    window.location.href = signInUrl(window.location.pathname + window.location.search);
  }

  async function leave() {
    await signOut().catch(() => {});
    window.location.reload();
  }

  return (
    <div className="fixed right-4 top-3 z-10 flex flex-col items-end gap-1">
      {me === null && (
        <button onClick={signIn} className={pill}>
          Sign in
        </button>
      )}
      {me && (
        <div className={`${pill} flex items-center gap-2`}>
          {me.avatar_url && (
            // eslint-disable-next-line @next/next/no-img-element -- Google avatar, no next/image remote config needed
            <img src={me.avatar_url} alt="" referrerPolicy="no-referrer" className="h-6 w-6 rounded-full" />
          )}
          <span>{me.name.split(" ")[0]}</span>
          <button onClick={leave} className="text-zinc-500 hover:text-zinc-900">
            Sign out
          </button>
        </div>
      )}
      {notice && <p className="rounded bg-white px-2 py-1 text-xs text-red-600 shadow-sm">{notice}</p>}
    </div>
  );
}
