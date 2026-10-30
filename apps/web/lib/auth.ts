// Sign-in helpers safe for server and client components. The useMe hook lives in use-me.ts (client only).

export type User = { id: number; name: string; email: string; avatar_url: string | null };

/** Google sign-in starts on the API and comes back to `returnPath` on this storefront. */
export function signInUrl(returnPath: string) {
  return `${process.env.NEXT_PUBLIC_API_URL}/auth/google/redirect?return=${encodeURIComponent(returnPath)}`;
}

/** Where /signin sends the customer back to after Google. */
export function signInPage(returnPath: string) {
  return `/signin?return=${encodeURIComponent(returnPath)}`;
}

/** Notices for `?signin=` after Google sends the customer back. */
export const signInNotices: Record<string, string> = {
  cancelled: "Sign-in was cancelled.",
  failed: "Google sign-in didn't work. Please try again.",
  unverified: "That Google account's email isn't verified.",
};
