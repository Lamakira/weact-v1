/* WeAct — Facturation & Abonnement · page + shell */

// ============================================================
// SECTION 1 — Current subscription
// ============================================================
function CurrentSubscription({ tier, status, startDate, endDate, autoRenew, onChangePlan }) {
  const meta = window.TIER_META[tier];
  const Ic = window.Ic;
  const total = window.daysBetween(startDate, endDate);
  const elapsed = Math.max(0, Math.min(total, window.daysBetween(startDate, new Date().toISOString())));
  const remaining = Math.max(0, total - elapsed);
  const pct = Math.max(0, Math.min(100, Math.round((elapsed / total) * 100)));
  const isExpiring = status === 'expiring';
  const isCancelled = status === 'cancelled';

  const statusPill = isCancelled
    ? { label: 'Annulé', bg: '#FEF2F2', fg: '#B91C1C', dot: '#DC2626' }
    : isExpiring
      ? { label: 'Expire bientôt', bg: '#FFF7ED', fg: '#C2410C', dot: '#EA580C' }
      : { label: 'Actif', bg: '#ECFDF5', fg: '#047857', dot: '#10B981' };

  return (
    <section className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" data-testid="current-subscription">
      {/* Accent header strip */}
      <div style={{ background: 'linear-gradient(180deg, rgba(25,132,150,0.05), rgba(25,132,150,0))' }} className="px-6 sm:px-8 pt-6 sm:pt-7 pb-0">
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="flex flex-col gap-1">
            <span className="text-xs font-semibold text-[#198496] uppercase tracking-[0.12em]">Abonnement en cours</span>
            <div className="flex items-center gap-2.5 mt-1">
              <h2 className="text-2xl font-bold text-gray-900 tracking-tight">{meta.name}</h2>
              {meta.badge && <window.WBadge size={20} tier={meta.badge} title={`Membre ${meta.name}`} />}
              <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full" style={{ background: statusPill.bg, color: statusPill.fg }}>
                <span className="w-1.5 h-1.5 rounded-full" style={{ background: statusPill.dot }}></span>
                {statusPill.label}
              </span>
            </div>
          </div>
          <button
            onClick={onChangePlan}
            className="text-sm font-semibold text-white bg-[#198496] hover:bg-[#146c7a] px-5 py-2.5 rounded-md transition-colors shrink-0"
            data-testid="change-plan-button"
          >
            Changer de plan
          </button>
        </div>
      </div>

      <div className="px-6 sm:px-8 py-6 sm:py-7">
        {/* Price + dates row */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
          <div className="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
            <div className="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]"><Ic.CreditCard size={18} /></div>
            <div>
              <p className="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Montant</p>
              <p className="text-base font-bold text-gray-900 mt-0.5">{window.fmtFCFA(meta.price)}<span className="text-xs font-medium text-gray-400"> / an</span></p>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
            <div className="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]"><Ic.Calendar size={18} /></div>
            <div>
              <p className="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Souscrit le</p>
              <p className="text-base font-bold text-gray-900 mt-0.5">{window.fmtDate(startDate)}</p>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
            <div className="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]"><Ic.Calendar size={18} /></div>
            <div>
              <p className="text-[11px] font-medium text-gray-500 uppercase tracking-wider">{isCancelled ? 'Accès jusqu\'au' : 'Se termine le'}</p>
              <p className="text-base font-bold text-gray-900 mt-0.5">{window.fmtDate(endDate)}</p>
            </div>
          </div>
        </div>

        {/* Time remaining bar */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-gray-500">{isCancelled ? 'Période d\'accès restante' : 'Temps restant sur la période'}</span>
            <span className="text-xs font-semibold text-gray-900">{remaining} jours restants</span>
          </div>
          <div className="h-2 rounded-full bg-gray-100 overflow-hidden">
            <div className="h-full rounded-full transition-all" style={{ width: `${pct}%`, background: isExpiring || isCancelled ? '#EA580C' : '#198496' }}></div>
          </div>
          <div className="flex items-center justify-between mt-2">
            <span className="text-[11px] text-gray-400">{window.fmtDate(startDate)}</span>
            <span className="text-[11px] text-gray-400">{window.fmtDate(endDate)}</span>
          </div>
        </div>

        {/* Renewal note */}
        {!isCancelled && (
          <div className="mt-5 flex items-center gap-2 text-xs text-gray-500">
            <Ic.Check size={14} style={{ color: '#10B981' }} />
            {autoRenew
              ? <span>Renouvellement automatique activé — tu seras prélevé de {window.fmtFCFA(meta.price)} le {window.fmtDate(endDate)}.</span>
              : <span>Renouvellement automatique désactivé — ton abonnement prendra fin le {window.fmtDate(endDate)}.</span>}
          </div>
        )}
        {isCancelled && (
          <div className="mt-5 flex items-center gap-2 text-xs" style={{ color: '#C2410C' }}>
            <Ic.AlertTriangle size={14} />
            <span>Abonnement annulé. Tu gardes l'accès complet jusqu'au {window.fmtDate(endDate)}, puis ton profil basculera en offre Découverte.</span>
          </div>
        )}
      </div>
    </section>
  );
}

// ============================================================
// SECTION 2 — History (expand/collapse)
// ============================================================
function HistoryRow({ item, open, onToggle }) {
  const meta = window.TIER_META[item.tier];
  const Ic = window.Ic;
  const statusMap = {
    expired: { label: 'Expiré', bg: '#F3F4F6', fg: '#6B7280' },
    cancelled: { label: 'Annulé', bg: '#FEF2F2', fg: '#B91C1C' },
    completed: { label: 'Terminé', bg: '#F3F4F6', fg: '#6B7280' },
  };
  const st = statusMap[item.status] || statusMap.expired;

  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden transition-all" data-testid={`history-row-${item.id}`}>
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-4 px-4 sm:px-5 py-4 hover:bg-gray-50/60 transition-colors text-left"
        aria-expanded={open}
      >
        <div className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 shrink-0">
          <Ic.Receipt size={18} />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <span className="text-sm font-semibold text-gray-900">{meta.name}</span>
            {meta.badge && <window.WBadge size={14} tier={meta.badge} title={meta.name} />}
          </div>
          <p className="text-xs text-gray-500 mt-0.5">{window.fmtDate(item.startDate)} — {window.fmtDate(item.endDate)}</p>
        </div>
        <div className="hidden sm:block text-sm font-semibold text-gray-900 shrink-0">{window.fmtFCFA(meta.price)}</div>
        <span className="text-[11px] font-semibold px-2.5 py-1 rounded-full shrink-0" style={{ background: st.bg, color: st.fg }}>{st.label}</span>
        <span className="flex items-center gap-1 text-xs font-medium text-[#198496] shrink-0">
          <span className="hidden sm:inline">Voir détails</span>
          <Ic.Chevron size={16} style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform 200ms' }} />
        </span>
      </button>

      {open && (
        <div className="px-4 sm:px-5 pb-5 pt-1 border-t border-gray-100 bg-gray-50/30">
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mt-4">
            <Detail label="Plan" value={meta.name} />
            <Detail label="Montant payé" value={window.fmtFCFA(meta.price)} />
            <Detail label="Date de début" value={window.fmtDate(item.startDate)} />
            <Detail label="Date de fin" value={window.fmtDate(item.endDate)} />
            <Detail label="Moyen de paiement" value={item.method} />
            <Detail label="Référence transaction" value={item.ref} mono />
            <Detail label="Durée" value="12 mois" />
            <Detail label="Statut" value={st.label} />
          </dl>
          <div className="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
            <span className="text-xs text-gray-400">Reçu disponible au format PDF</span>
            <button className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#198496] hover:text-[#146c7a] transition-colors">
              <Ic.FileText size={14} /> Télécharger le reçu
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
function Detail({ label, value, mono }) {
  return (
    <div className="flex items-center justify-between sm:block">
      <dt className="text-[11px] font-medium text-gray-500 uppercase tracking-wider">{label}</dt>
      <dd className={"text-sm font-medium text-gray-900 sm:mt-0.5 " + (mono ? 'font-mono text-xs' : '')}>{value}</dd>
    </div>
  );
}

function History({ items }) {
  const [openId, setOpenId] = React.useState(null);
  return (
    <section className="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 sm:px-8 py-6 sm:py-7" data-testid="history-section">
      <div className="mb-5">
        <h2 className="text-lg font-bold text-gray-900">Historique des abonnements</h2>
        <p className="text-sm text-gray-500 mt-0.5">Tes abonnements passés et leurs détails de facturation.</p>
      </div>
      {items.length === 0 ? (
        <div className="text-center py-10 text-sm text-gray-400">Aucun abonnement passé pour le moment.</div>
      ) : (
        <div className="flex flex-col gap-2.5">
          {items.map((it) => (
            <HistoryRow key={it.id} item={it} open={openId === it.id} onToggle={() => setOpenId(openId === it.id ? null : it.id)} />
          ))}
        </div>
      )}
    </section>
  );
}

// ============================================================
// SECTION 3 — Cancel plan
// ============================================================
function CancelPlan({ cancelled, endDate, onCancel }) {
  if (cancelled) {
    return (
      <section className="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 sm:px-8 py-6" data-testid="cancel-section">
        <div className="flex items-center justify-between gap-6 flex-wrap">
          <div className="max-w-xl">
            <h2 className="text-base font-bold text-gray-900">Abonnement annulé</h2>
            <p className="text-sm text-gray-500 mt-1 leading-relaxed">
              Ton abonnement ne sera pas renouvelé. Tu conserves l'accès complet jusqu'au {window.fmtDate(endDate)}.
            </p>
          </div>
          <button className="text-sm font-semibold text-[#198496] border border-[#198496] hover:bg-[#198496]/5 px-5 py-2.5 rounded-md transition-colors shrink-0">
            Réactiver
          </button>
        </div>
      </section>
    );
  }
  return (
    <section className="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 sm:px-8 py-6" data-testid="cancel-section">
      <div className="flex items-center justify-between gap-6 flex-wrap">
        <div className="max-w-xl">
          <h2 className="text-base font-bold text-gray-900">Annuler l'abonnement</h2>
          <p className="text-sm text-gray-500 mt-1 leading-relaxed">
            Si tu annules, tu conserves l'accès complet à ton plan jusqu'à la fin de ta période de facturation.
          </p>
        </div>
        <button
          onClick={onCancel}
          className="text-sm font-semibold px-5 py-2.5 rounded-md border transition-colors shrink-0"
          style={{ color: window.C_RED, borderColor: window.C_RED }}
          onMouseEnter={(e) => { e.currentTarget.style.background = '#FEF2F2'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
          data-testid="cancel-button"
        >
          Annuler
        </button>
      </div>
    </section>
  );
}

window.CurrentSubscription = CurrentSubscription;
window.History = History;
window.CancelPlan = CancelPlan;
