import React, { useMemo, useState } from 'react';
import { ModuleInfo, User } from '../types';
import { DataService } from '../services/mockData';
import { useLanguage } from '../contexts/LanguageContext';
import { notify } from '../services/notify';
import { Check, Sparkles, Ticket, Loader2, Lock, Gift } from 'lucide-react';

interface ModuleStoreProps {
  currentUser: User;
}

type Period = 'MONTHLY' | 'QUARTERLY' | 'YEARLY';

// Per-purchase multiplier vs. the monthly price (mirrors the billing model).
const periodMultiplier = (roleScope: string, period: Period): number => {
  if (period === 'MONTHLY') return 1;
  if (period === 'QUARTERLY') return 2.5;
  return roleScope === 'FOREIGN' ? 10 : 8.4; // YEARLY
};
const periodLabel: Record<Period, string> = { MONTHLY: 'Monthly', QUARTERLY: 'Quarterly', YEARLY: 'Yearly' };

export const ModuleStore: React.FC<ModuleStoreProps> = ({ currentUser }) => {
  const { t } = useLanguage();
  const [, force] = useState(0);
  const rerender = () => force(n => n + 1);

  const modules = DataService.getModules();
  const [periods, setPeriods] = useState<Record<string, Period>>({});
  const [busy, setBusy] = useState<string | null>(null);
  const [code, setCode] = useState('');
  const [redeeming, setRedeeming] = useState(false);

  const paid = useMemo(() => modules.filter(m => !m.isCore), [modules]);
  const core = useMemo(() => modules.filter(m => m.isCore), [modules]);

  const priceFor = (m: ModuleInfo, p: Period) => (m.monthlyPriceKd * periodMultiplier(m.roleScope, p)).toFixed(m.monthlyPriceKd % 1 === 0 ? 0 : 2);

  const activate = async (m: ModuleInfo) => {
    const p = periods[m.key] || 'MONTHLY';
    setBusy(m.key);
    const r = await DataService.buyModule(m.key, p);
    if (r.success && r.redirectUrl) { window.location.href = r.redirectUrl; return; } // to checkout.com
    setBusy(null);
    if (r.success) { notify(`${m.name} activated.`, 'success'); rerender(); }
    else notify(r.message || 'Could not activate module.', 'warning');
  };

  const redeem = async () => {
    if (!code.trim()) return;
    setRedeeming(true);
    const r = await DataService.redeemPromoCode(code.trim());
    setRedeeming(false);
    if (r.success) { notify(`Code redeemed — ${(r.activated || []).length} module(s) activated.`, 'success'); setCode(''); rerender(); }
    else notify(r.message || 'Invalid code.', 'warning');
  };

  return (
    <div className="animate-in fade-in duration-500 max-w-6xl mx-auto">
      <div className="mb-8">
        <h1 className="text-3xl font-black text-gray-900 flex items-center gap-3"><Sparkles className="text-teal-600" /> {t('modules_title') || 'Modules & Plan'}</h1>
        <p className="text-gray-500 mt-1">{t('modules_subtitle') || 'Your core is free forever. Add modules any time — monthly, quarterly or yearly.'}</p>
      </div>

      {/* Launch grace banner */}
      {DataService.modulesGraceUntil() && (
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start gap-3">
          <Gift size={18} className="text-amber-600 mt-0.5 shrink-0" />
          <p className="text-sm text-amber-900">
            <span className="font-bold">Launch offer:</span> all your modules are free during the launch period —
            through <span className="font-bold">{DataService.modulesGraceUntil()}</span>. After that, keep the ones
            you want by activating a plan below.
          </p>
        </div>
      )}

      {/* Redeem code */}
      <div className="bg-white rounded-2xl border border-gray-200 p-5 mb-8 flex flex-col sm:flex-row sm:items-center gap-3">
        <div className="flex items-center gap-2 text-gray-700 font-semibold shrink-0"><Ticket size={18} className="text-teal-600" /> {t('have_a_code') || 'Have a code?'}</div>
        <input
          value={code} onChange={e => setCode(e.target.value.toUpperCase())}
          placeholder="EDGE-XXXXXXXX"
          className="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 font-mono tracking-wider"
        />
        <button onClick={redeem} disabled={redeeming || !code.trim()}
          className="px-5 py-2 bg-teal-600 text-white text-sm font-bold rounded-xl hover:bg-teal-700 disabled:opacity-50 flex items-center justify-center gap-2">
          {redeeming ? <Loader2 size={16} className="animate-spin" /> : null} {t('redeem') || 'Redeem'}
        </button>
      </div>

      {/* Core (free) */}
      {core.length > 0 && (
        <div className="mb-8">
          <h2 className="text-xs font-black uppercase tracking-widest text-gray-400 mb-3">{t('included_free') || 'Included — free forever'}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {core.map(m => (
              <div key={m.key} className="bg-teal-50 border border-teal-100 rounded-2xl p-5 flex items-center justify-between">
                <div><h3 className="font-bold text-gray-900">{m.name}</h3><p className="text-xs text-teal-700 font-semibold mt-1">Free</p></div>
                <span className="flex items-center gap-1 text-teal-700 text-xs font-bold bg-white px-3 py-1.5 rounded-full border border-teal-200"><Check size={14} /> Included</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Paid modules */}
      <div>
        <h2 className="text-xs font-black uppercase tracking-widest text-gray-400 mb-3">{t('add_modules') || 'Add modules'}</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {paid.map(m => {
            const p = periods[m.key] || 'MONTHLY';
            const active = m.active; // real entitlement (purchased/promo), independent of gate enforcement
            return (
              <div key={m.key} className={`bg-white border rounded-2xl p-5 flex flex-col ${active ? 'border-teal-300 ring-1 ring-teal-200' : 'border-gray-200'}`}>
                <div className="flex items-start justify-between">
                  <h3 className="font-bold text-gray-900">{m.name}</h3>
                  {active
                    ? <span className="flex items-center gap-1 text-teal-700 text-[11px] font-bold bg-teal-50 px-2.5 py-1 rounded-full border border-teal-200 shrink-0"><Check size={12} /> Active</span>
                    : <span className="flex items-center gap-1 text-gray-400 text-[11px] font-bold shrink-0"><Lock size={12} /></span>}
                </div>
                <p className="text-2xl font-black text-gray-900 mt-3">{priceFor(m, p)} <span className="text-sm font-medium text-gray-400">KD / {periodLabel[p].toLowerCase()}</span></p>

                {active ? (
                  <p className="text-xs text-gray-500 mt-4">{m.billingPeriod ? `${periodLabel[m.billingPeriod as Period]} · renews ${m.renewsOn ?? ''}` : 'Active on your account.'}</p>
                ) : (
                  <div className="mt-4">
                    <div className="flex gap-1 bg-gray-100 rounded-xl p-1 mb-3">
                      {(['MONTHLY', 'QUARTERLY', 'YEARLY'] as Period[]).map(op => (
                        <button key={op} onClick={() => setPeriods(s => ({ ...s, [m.key]: op }))}
                          className={`flex-1 text-[11px] font-bold py-1.5 rounded-lg transition-colors ${p === op ? 'bg-white text-teal-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
                          {periodLabel[op]}
                        </button>
                      ))}
                    </div>
                    <button onClick={() => activate(m)} disabled={busy === m.key}
                      className="w-full px-4 py-2 bg-teal-600 text-white text-sm font-bold rounded-xl hover:bg-teal-700 disabled:opacity-50 flex items-center justify-center gap-2">
                      {busy === m.key ? <Loader2 size={16} className="animate-spin" /> : null} {t('activate') || 'Activate'}
                    </button>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
