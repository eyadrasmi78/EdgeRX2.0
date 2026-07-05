import React from 'react';
import { useLanguage } from '../contexts/LanguageContext';
import { Lock, Sparkles } from 'lucide-react';

interface ModuleLockedProps {
  /** Human name of the feature that's locked, e.g. "Buying Groups". */
  featureName: string;
  onGoToStore: () => void;
}

/** Shown in place of a feature the account hasn't purchased (when enforcement is on). */
export const ModuleLocked: React.FC<ModuleLockedProps> = ({ featureName, onGoToStore }) => {
  const { t } = useLanguage();
  return (
    <div className="animate-in fade-in duration-500 max-w-xl mx-auto mt-16 text-center">
      <div className="mx-auto w-16 h-16 rounded-2xl bg-teal-50 border border-teal-100 flex items-center justify-center mb-6">
        <Lock className="text-teal-600" size={28} />
      </div>
      <h1 className="text-2xl font-black text-gray-900">{featureName}</h1>
      <p className="text-gray-500 mt-2">
        {t('module_locked_desc') || 'This is a paid module that isn’t active on your account yet. Activate it any time — monthly, quarterly or yearly.'}
      </p>
      <button
        onClick={onGoToStore}
        className="mt-6 inline-flex items-center gap-2 px-6 py-2.5 bg-teal-600 text-white text-sm font-bold rounded-xl hover:bg-teal-700 transition-colors"
      >
        <Sparkles size={16} /> {t('view_modules') || 'View Modules'}
      </button>
    </div>
  );
};
