/**
 * Routes to the Laravel-hosted Gemini proxy at /api/ai/*
 * The Gemini API key never reaches the browser.
 *
 * FE-20 fix: previously these methods swallowed errors silently and returned a
 * generic fallback string. The customer had no idea why AI didn't work
 * (rate-limit, network blip, bad prompt). Now we surface the failure via the
 * onError callback so the SPA can show a toast (rate-limited, no internet, etc.)
 * while still returning a graceful fallback string for the panel.
 */

import { Product } from "../types";
import { api, ApiError } from "./api";

let _onError: ((message: string) => void) | null = null;

/** Caller (App.tsx) registers this on mount so AI errors land in the toast queue. */
export function setAiErrorHandler(fn: ((message: string) => void) | null) {
  _onError = fn;
}

function classify(err: unknown): string {
  if (err instanceof ApiError) {
    if (err.status === 429) return 'AI rate limit reached — please retry in a minute.';
    if (err.status === 422) return 'AI rejected the input. Try a simpler prompt.';
    if (err.status >= 500)  return 'AI service unavailable — try again shortly.';
    if (err.status === 401) return 'Session expired — please log in again.';
    return err.message || 'AI request failed.';
  }
  return 'AI service is unreachable. Check your connection.';
}

export const AIService = {
  analyzeProduct: async (product: Product): Promise<string> => {
    try {
      const r = await api.post<{ text: string }>('/ai/analyze-product', { product });
      return r.text || "AI analysis temporarily unavailable.";
    } catch (err) {
      const msg = classify(err);
      _onError?.(msg);
      return msg;
    }
  },

  translateToArabic: async (text: string): Promise<string> => {
    try {
      const r = await api.post<{ text: string }>('/ai/translate-arabic', { text });
      return r.text || "Translation temporarily unavailable.";
    } catch (err) {
      const msg = classify(err);
      _onError?.(msg);
      return msg;
    }
  },
};
