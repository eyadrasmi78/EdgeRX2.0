import React, { useEffect, useState } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { useModalA11y } from '../hooks/useModalA11y';
import { ConfirmRequest, registerConfirmHandler } from '../services/notify';

/**
 * FE-11: Branded replacement for window.confirm(). Mounted once by App.tsx;
 * any component can call `confirmAction({...})` from notify.ts and get a
 * Promise<boolean> back. Destructive variant uses rose-600 button to signal
 * the action is irreversible.
 */
export const ConfirmDialog: React.FC = () => {
  const { t, dir } = useLanguage();
  const [open, setOpen] = useState(false);
  const [req, setReq] = useState<ConfirmRequest | null>(null);
  const [resolver, setResolver] = useState<((v: boolean) => void) | null>(null);

  useEffect(() => {
    registerConfirmHandler((r) => {
      return new Promise<boolean>((resolve) => {
        setReq(r);
        setResolver(() => resolve);
        setOpen(true);
      });
    });
    return () => registerConfirmHandler(null);
  }, []);

  const close = (result: boolean) => {
    if (resolver) resolver(result);
    setOpen(false);
    setReq(null);
    setResolver(null);
  };

  const ref = useModalA11y(open, () => close(false));

  if (!open || !req) return null;

  return (
    <div
      className="fixed inset-0 z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 animate-in fade-in duration-150"
      dir={dir}
      onClick={() => close(false)}
    >
      <div
        ref={ref}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        onClick={(e) => e.stopPropagation()}
        className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-150"
      >
        <header className="flex items-center justify-between px-5 py-3 border-b border-slate-100">
          <div className="flex items-center gap-2">
            {req.destructive && (
              <div className="w-7 h-7 rounded-full bg-rose-100 flex items-center justify-center">
                <AlertTriangle size={15} className="text-rose-600" />
              </div>
            )}
            <h3 id="confirm-dialog-title" className="font-bold text-slate-900 text-base">{req.title}</h3>
          </div>
          <button onClick={() => close(false)} className="text-slate-400 hover:text-slate-600 p-1">
            <X size={18} />
          </button>
        </header>
        <div className="px-5 py-5 text-sm text-slate-700 leading-relaxed">
          {req.message}
        </div>
        <footer className="flex justify-end gap-2 px-5 py-3 bg-slate-50 border-t border-slate-100">
          <button
            onClick={() => close(false)}
            className="px-4 py-2 text-sm font-semibold rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 transition"
          >
            {req.cancelLabel || t('cancel') || 'Cancel'}
          </button>
          <button
            onClick={() => close(true)}
            className={`px-4 py-2 text-sm font-bold rounded-lg text-white shadow transition ${
              req.destructive
                ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-200'
                : 'bg-teal-600 hover:bg-teal-700 shadow-teal-200'
            }`}
          >
            {req.confirmLabel || t('confirm') || 'Confirm'}
          </button>
        </footer>
      </div>
    </div>
  );
};
