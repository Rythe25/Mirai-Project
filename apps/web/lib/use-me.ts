"use client";

import { useEffect, useState } from "react";
import { api } from "./api";
import type { User } from "./auth";

/** The signed-in customer: null for guests, undefined while loading. */
export function useMe(): User | null | undefined {
  const [me, setMe] = useState<User | null>();
  useEffect(() => {
    api<User>("/me").then(setMe, () => setMe(null));
  }, []);
  return me;
}

export function signOut() {
  return api<void>("/logout", { method: "POST" });
}
