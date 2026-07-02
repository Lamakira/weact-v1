/* WeAct — Facturation page: shell, modals, tweaks, mount */

const SIDEBAR_ITEMS = [
  { key: 'dashboard', label: 'Dashboard', icon: 'Home' },
  { key: 'candidatures', label: 'Mes candidatures', icon: 'Briefcase' },
  { key: 'messages', label: 'Messages', icon: 'Message' },
  { key: 'wallet', label: 'Mon portefeuille', icon: 'Wallet' },
  { key: 'billing', label: 'Facturation', icon: 'CreditCard' },
  { key: 'profile', label: 'Mon profil', icon: 'User' },
];

function Sidebar() {
  const Ic = window.Ic;
  return (
    <aside className="flex-none bg-white border-r border-gray-100 flex flex-col w-64 h-full" data-testid="dashboard-sidebar">
      <div className="flex items-center py-6 px-6 border-b border-gray-100">
        <div style={{ display: 'flex', alignItems: 'center' }}>
          <span style={{ fontSize: 22, fontWeight: 800, color: '#0F1419', letterSpacing: '-0.02em' }}>WE</span>
          <span style={{ fontSize: 22, fontWeight: 800, color: window.C_TEAL, letterSpacing: '-0.02em' }}>ACT</span>
        </div>
      </div>
      <nav className="flex-1 px-3 py-4 overflow-y-auto">
        <ul className="space-y-1">
          {SIDEBAR_ITEMS.map((it) => {
            const Icon = Ic[it.icon];
            const active = it.key === 'billing';
            return (
              <li key={it.key}>
                <a className={"flex items-center rounded-xl px-4 py-2.5 gap-3 cursor-pointer transition-all " + (active ? 'font-medium' : 'text-slate-600 hover:bg-gray-50')}
                   style={active ? { background: 'rgba(25,132,150,0.10)', color: window.C_TEAL } : {}}>
                  <Icon size={20} />
                  <span className="text-sm">{it.label}</span>
                </a>
              </li>
            );
          })}
        </ul>
      </nav>
      <div className="p-4 border-t border-gray-100">
        <a className="flex items-center rounded-xl px-4 py-2.5 gap-3 text-slate-500 hover:bg-gray-50 cursor-pointer transition-all">
          <Ic.Home size={20} />
          <span className="text-sm font-medium">Retour au site</span>
        </a>
      </div>
    </aside>
  );
}

// ----- Change plan modal -----
function ChangePlanModal({ currentTier, onClose, onSelect }) {
  const order = ['decouverte', 'starter', 'pro', 'elite'];
  return (
    <Modal onClose={onClose} title="Changer de plan" subtitle="Choisis le palier qui correspond à tes besoins. Le changement prend effet immédiatement.">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {order.map((k) => {
          const meta = window.TIER_META[k];
          const isCurrent = k === currentTier;
          return (
            <button key={k} disabled={isCurrent} onClick={() => onSelect(k)}
              className={"text-left p-4 rounded-xl border transition-all " + (isCurrent ? 'border-[#198496] bg-[#198496]/5 cursor-default' : 'border-gray-200 hover:border-[#198496] hover:shadow-sm')}>
              <div className="flex items-center justify-between mb-1">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-bold text-gray-900">{meta.name}</span>
                  {meta.badge && <window.WBadge size={14} tier={meta.badge} title={meta.name} />}
                </div>
                {isCurrent && <span className="text-[10px] font-bold text-[#198496] uppercase tracking-wider">Actuel</span>}
              </div>
              <div className="text-lg font-bold text-gray-900">{meta.price === 0 ? 'Gratuit' : window.fmtFCFA(meta.price)}<span className="text-xs font-medium text-gray-400">{meta.price === 0 ? '' : ' / an'}</span></div>
            </button>
          );
        })}
      </div>
    </Modal>
  );
}

// ----- Cancel confirm modal -----
function CancelModal({ endDate, onClose, onConfirm }) {
  const Ic = window.Ic;
  return (
    <Modal onClose={onClose} title="Annuler ton abonnement ?" subtitle={null}>
      <div className="flex items-start gap-3 p-4 rounded-xl mb-5" style={{ background: '#FFF7ED' }}>
        <span style={{ color: '#EA580C', marginTop: 1 }}><Ic.AlertTriangle size={18} /></span>
        <p className="text-sm leading-relaxed" style={{ color: '#9A3412' }}>
          Tu conserveras l'accès complet à toutes les fonctionnalités de ton plan jusqu'au <strong>{window.fmtDate(endDate)}</strong>. Après cette date, ton profil basculera automatiquement en offre Découverte gratuite et tu perdras l'accès aux missions UGC.
        </p>
      </div>
      <div className="flex items-center justify-end gap-3">
        <button onClick={onClose} className="text-sm font-semibold text-gray-700 hover:bg-gray-100 px-5 py-2.5 rounded-md transition-colors">Garder mon plan</button>
        <button onClick={onConfirm} className="text-sm font-semibold text-white px-5 py-2.5 rounded-md transition-colors" style={{ background: window.C_RED }}>
          Confirmer l'annulation
        </button>
      </div>
    </Modal>
  );
}

function Modal({ title, subtitle, children, onClose }) {
  const Ic = window.Ic;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: 'rgba(15,20,25,0.45)' }} onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 sm:p-7" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-start justify-between gap-4 mb-5">
          <div>
            <h3 className="text-lg font-bold text-gray-900">{title}</h3>
            {subtitle && <p className="text-sm text-gray-500 mt-1 leading-relaxed">{subtitle}</p>}
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 transition-colors shrink-0"><Ic.X size={20} /></button>
        </div>
        {children}
      </div>
    </div>
  );
}

// ----- Toast -----
function Toast({ msg }) {
  if (!msg) return null;
  const Ic = window.Ic;
  return (
    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2.5 bg-[#0F1419] text-white text-sm font-medium px-4 py-3 rounded-xl shadow-lg" style={{ animation: 'toastIn 200ms ease-out' }}>
      <Ic.Check size={16} style={{ color: '#34D399' }} /> {msg}
    </div>
  );
}

// ============================================================
// PAGE
// ============================================================
const HISTORY = [
  { id: 'h1', tier: 'starter', startDate: '2024-01-12', endDate: '2025-01-12', status: 'expired', method: 'Mobile Money · MTN', ref: 'WACT-2024-0X8F31' },
  { id: 'h2', tier: 'starter', startDate: '2025-01-12', endDate: '2026-01-12', status: 'completed', method: 'Mobile Money · Moov', ref: 'WACT-2025-1B7K92' },
  { id: 'h3', tier: 'decouverte', startDate: '2023-09-03', endDate: '2024-01-12', status: 'expired', method: 'Gratuit', ref: '—' },
];

function BillingPage() {
  const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
    "currentTier": "pro",
    "status": "active",
    "autoRenew": true
  }/*EDITMODE-END*/;
  const [t, setTweak] = window.useTweaks(TWEAK_DEFAULTS);

  const [changeOpen, setChangeOpen] = React.useState(false);
  const [cancelOpen, setCancelOpen] = React.useState(false);
  const [toast, setToast] = React.useState('');

  const startDate = '2026-01-15';
  const endDate = '2027-01-15';
  const cancelled = t.status === 'cancelled';

  const showToast = (m) => { setToast(m); setTimeout(() => setToast(''), 2600); };

  return (
    <div className="flex h-screen bg-[#F7F8F9]" style={{ fontFamily: 'Inter, sans-serif' }}>
      <Sidebar />
      <main className="flex-1 overflow-y-auto">
        <div className="max-w-4xl mx-auto px-6 sm:px-8 py-8 sm:py-10">
          {/* Page header */}
          <div className="mb-8">
            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Facturation &amp; Abonnement</h1>
            <p className="text-sm text-gray-500 mt-1.5">Gère ton abonnement, consulte ton historique et tes reçus.</p>
          </div>

          <div className="flex flex-col gap-6">
            <window.CurrentSubscription
              tier={t.currentTier}
              status={t.status}
              startDate={startDate}
              endDate={endDate}
              autoRenew={t.autoRenew}
              onChangePlan={() => setChangeOpen(true)}
            />
            <window.History items={HISTORY} />
            <window.CancelPlan
              cancelled={cancelled}
              endDate={endDate}
              onCancel={() => setCancelOpen(true)}
            />
          </div>
        </div>
      </main>

      {/* Tweaks */}
      <window.TweaksPanel>
        <window.TweakSection label="Aperçu des états" />
        <window.TweakSelect label="Plan actuel" value={t.currentTier}
          options={[{ value: 'decouverte', label: 'Découverte' }, { value: 'starter', label: 'Starter' }, { value: 'pro', label: 'Pro' }, { value: 'elite', label: 'Élite' }]}
          onChange={(v) => setTweak('currentTier', v)} />
        <window.TweakRadio label="Statut" value={t.status}
          options={[{ value: 'active', label: 'Actif' }, { value: 'expiring', label: 'Expire' }, { value: 'cancelled', label: 'Annulé' }]}
          onChange={(v) => setTweak('status', v)} />
        <window.TweakToggle label="Renouvellement auto" value={t.autoRenew} onChange={(v) => setTweak('autoRenew', v)} />
      </window.TweaksPanel>

      {/* Modals */}
      {changeOpen && (
        <ChangePlanModal currentTier={t.currentTier} onClose={() => setChangeOpen(false)}
          onSelect={(k) => { setTweak('currentTier', k); setTweak('status', 'active'); setChangeOpen(false); showToast(`Plan changé pour ${window.TIER_META[k].name}`); }} />
      )}
      {cancelOpen && (
        <CancelModal endDate={endDate} onClose={() => setCancelOpen(false)}
          onConfirm={() => { setTweak('status', 'cancelled'); setCancelOpen(false); showToast('Abonnement annulé'); }} />
      )}
      <Toast msg={toast} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<BillingPage />);
