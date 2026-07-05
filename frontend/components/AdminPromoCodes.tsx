import React, { useEffect, useState } from 'react';
import { DataService } from '../services/mockData';
import { notify } from '../services/notify';
import { Ticket, Copy, Loader2, Check } from 'lucide-react';

// Curated module keys an admin can waive, grouped for readability.
const MODULE_OPTIONS: { group: string; items: { key: string; label: string }[] }[] = [
  { group: 'Customer', items: [
    { key: 'buying_groups', label: 'Buying Groups' },
    { key: 'pricing_agreements', label: 'Pricing Agreements' },
    { key: 'transfers', label: 'Transfers' },
    { key: 'ai_analytics', label: 'AI & Analytics' },
    { key: 'order_chat', label: 'Order Chat' },
    { key: 'market_feed', label: 'Market Feed' },
  ]},
  { group: 'Local Supplier', items: [
    { key: 'supplier_core', label: 'Supplier Core (Base)' },
    { key: 'supplier_agreements', label: 'Pricing Agreements' },
    { key: 'supplier_buying_groups', label: 'Buying Groups' },
    { key: 'transfer_qc', label: 'Transfer QC' },
    { key: 'foreign_partnerships', label: 'Foreign Partnerships' },
    { key: 'supplier_ai_analytics', label: 'AI & Analytics' },
  ]},
  { group: 'Pharmacy Master / Foreign', items: [
    { key: 'chain_management', label: 'Chain Management (Base)' },
    { key: 'foreign_plan', label: 'Foreign Supplier Plan' },
  ]},
];

export const AdminPromoCodes: React.FC = () => {
  const [customerId, setCustomerId] = useState('');
  const [selected, setSelected] = useState<string[]>([]);
  const [waiverDays, setWaiverDays] = useState('');
  const [maxRedemptions, setMaxRedemptions] = useState('1');
  const [busy, setBusy] = useState(false);
  const [lastCode, setLastCode] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [codes, setCodes] = useState<any[]>([]);

  const loadCodes = async () => setCodes(await DataService.listPromoCodes());
  useEffect(() => { loadCodes(); }, []);

  const toggle = (key: string) => setSelected(s =>
    s.includes(key) ? s.filter(k => k !== key) : (s.length >= 3 ? s : [...s, key]));

  const generate = async () => {
    if (selected.length === 0) { notify('Pick 1–3 modules to waive.', 'warning'); return; }
    setBusy(true);
    const r = await DataService.generatePromoCode({
      customer_id: customerId.trim() || undefined,
      module_keys: selected,
      waiver_days: waiverDays ? Number(waiverDays) : undefined,
      max_redemptions: Number(maxRedemptions) || 1,
    });
    setBusy(false);
    if (r.success && r.code) { setLastCode(r.code); setCopied(false); notify('Code generated.', 'success'); loadCodes(); }
    else notify(r.message || 'Could not generate code.', 'warning');
  };

  const copy = () => { if (lastCode) { navigator.clipboard?.writeText(lastCode); setCopied(true); } };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* Generator */}
      <div className="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 className="font-black text-gray-900 flex items-center gap-2 mb-4"><Ticket className="text-teal-600" size={18} /> Generate a fee-waiver code</h3>

        <label className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Customer account ID (optional)</label>
        <input value={customerId} onChange={e => setCustomerId(e.target.value)} placeholder="Leave empty for an open code"
          className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-teal-500" />

        <label className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Modules to waive (1–3) — {selected.length} selected</label>
        <div className="space-y-3 mb-4 max-h-56 overflow-y-auto pr-1">
          {MODULE_OPTIONS.map(g => (
            <div key={g.group}>
              <p className="text-[11px] font-bold text-gray-400 mb-1">{g.group}</p>
              <div className="flex flex-wrap gap-2">
                {g.items.map(it => (
                  <button key={it.key} onClick={() => toggle(it.key)}
                    className={`text-xs px-3 py-1.5 rounded-full border transition-colors ${selected.includes(it.key) ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600 border-gray-300 hover:border-teal-400'}`}>
                    {it.label}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Waiver days (optional)</label>
            <input value={waiverDays} onChange={e => setWaiverDays(e.target.value.replace(/\D/g,''))} placeholder="Permanent"
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          <div>
            <label className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Max redemptions</label>
            <input value={maxRedemptions} onChange={e => setMaxRedemptions(e.target.value.replace(/\D/g,''))}
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
        </div>

        <button onClick={generate} disabled={busy}
          className="w-full px-4 py-2.5 bg-teal-600 text-white text-sm font-bold rounded-xl hover:bg-teal-700 disabled:opacity-50 flex items-center justify-center gap-2">
          {busy ? <Loader2 size={16} className="animate-spin" /> : null} Generate code
        </button>

        {lastCode && (
          <div className="mt-4 bg-teal-50 border border-teal-200 rounded-xl p-4 flex items-center justify-between">
            <span className="font-mono font-black text-teal-800 tracking-wider">{lastCode}</span>
            <button onClick={copy} className="flex items-center gap-1 text-teal-700 text-xs font-bold hover:text-teal-900">
              {copied ? <Check size={14} /> : <Copy size={14} />} {copied ? 'Copied' : 'Copy'}
            </button>
          </div>
        )}
      </div>

      {/* Existing codes */}
      <div className="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 className="font-black text-gray-900 mb-4">Existing codes</h3>
        {codes.length === 0 ? (
          <p className="text-sm text-gray-400">No codes yet.</p>
        ) : (
          <div className="space-y-2 max-h-96 overflow-y-auto">
            {codes.map(c => (
              <div key={c.id} className="border border-gray-100 rounded-xl p-3 flex items-center justify-between">
                <div>
                  <span className="font-mono font-bold text-gray-900 text-sm">{c.code}</span>
                  <p className="text-xs text-gray-500 mt-0.5">{(c.moduleKeys || []).join(', ')}</p>
                </div>
                <span className="text-xs text-gray-400 shrink-0">{c.redeemedCount}/{c.maxRedemptions} used</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
