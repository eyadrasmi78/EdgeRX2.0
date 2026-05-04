/**
 * FE-11 fix: tiny global notification bridge so any component can pop a
 * branded toast without prop-drilling addNotification through 8 levels.
 * Replaces the native alert() calls scattered across AdminPortal /
 * SupplierPortal — those are admin-destructive paths where the native
 * dialog looked unprofessional and was jarring on mobile.
 *
 * Wiring:
 *   - App.tsx calls registerNotifier(addNotification) on mount.
 *   - Anywhere else: `import { notify } from '../services/notify';`
 *     then `notify('Could not release group', 'warning');`
 *   - For destructive confirmation: use the confirmAction() helper which
 *     resolves to true/false based on the user clicking Confirm vs Cancel
 *     in a branded ConfirmDialog (rendered by App.tsx via subscribeConfirm).
 */

type NotifyType = 'success' | 'info' | 'warning';
type Notifier = (message: string, type?: NotifyType) => void;

let _notifier: Notifier | null = null;
export function registerNotifier(fn: Notifier | null) { _notifier = fn; }

export function notify(message: string, type: NotifyType = 'info') {
  if (_notifier) {
    _notifier(message, type);
  } else {
    // Fallback only fires before App.tsx mounts (very rare)
    // eslint-disable-next-line no-console
    console.warn('[notify] no notifier registered:', message);
  }
}

/* ── Branded confirmation dialog ── */
export interface ConfirmRequest {
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  destructive?: boolean;
}
type ConfirmHandler = (req: ConfirmRequest) => Promise<boolean>;
let _confirmHandler: ConfirmHandler | null = null;
export function registerConfirmHandler(fn: ConfirmHandler | null) { _confirmHandler = fn; }

export async function confirmAction(req: ConfirmRequest): Promise<boolean> {
  if (_confirmHandler) return _confirmHandler(req);
  // Fallback to native confirm only if handler isn't registered yet
  // (e.g. very early in boot). Never reached at steady state.
  return Promise.resolve(window.confirm(req.message));
}
