// shared.jsx — WeAct UGC primitives (icons, phone frame, chrono ring, chrome)
// Brand: teal #198496, dark #0F1419, Inter
// All exports attached to window for cross-script sharing.

const { useState, useEffect, useMemo, useRef } = React;

// ---------- Lucide icon wrapper ----------
// Uses the lucide UMD bundle (window.lucide.icons[name] = [tag, attrs, children]).
function Icon({ name, size = 16, stroke = 2, className = '', style }) {
  // Lucide UMD exposes icons in PascalCase, with the value being just the
  // children array: [[tag, attrs], ...]. We wrap in a standard svg.
  const key = (name || '').split('-').map(s => s ? s[0].toUpperCase() + s.slice(1) : '').join('');
  const children = window.lucide && window.lucide.icons && window.lucide.icons[key];
  if (!Array.isArray(children)) {
    return <span style={{ display:'inline-block', width:size, height:size, background:'#f00', ...style }} className={className} />;
  }
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size}
      viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round"
      className={className} style={style} aria-hidden="true">
      {children.map((c, i) => {
        const [tag, attrs] = c;
        return React.createElement(tag, { key: i, ...attrs });
      })}
    </svg>
  );
}

// ---------- WeAct logo ----------
function WeActLogo({ size = 'sm', dark = false }) {
  const px = size === 'lg' ? 'text-xl' : size === 'md' ? 'text-base' : 'text-sm';
  return (
    <div className={`${px} font-bold tracking-tight leading-none flex items-center select-none`}>
      <span style={{ color: dark ? '#fff' : '#0F1419' }}>WE</span>
      <span style={{ color: '#198496' }}>ACT</span>
    </div>
  );
}

// ---------- Striped imagery placeholder ----------
function StripePh({ label, w, h, dark = false, rounded = 'rounded-md', className = '', children }) {
  return (
    <div
      className={`${rounded} ${dark ? 'ph-stripe-dark' : 'ph-stripe'} border ${dark ? 'border-white/10' : 'border-gray-200'} flex items-center justify-center text-[10px] font-mono uppercase tracking-wider ${dark ? 'text-white/55' : 'text-gray-500'} ${className}`}
      style={{ width: w, height: h }}
    >
      {children ?? label}
    </div>
  );
}

// ---------- Phone frame (mobile artboards for Face flow) ----------
function PhoneFrame({ width = 360, height = 760, children, status = 'WeAct' }) {
  return (
    <div
      className="relative bg-black"
      style={{ width: width + 16, height: height + 16, borderRadius: 36, padding: 8 }}
    >
      <div
        className="relative bg-white overflow-hidden"
        style={{ width, height, borderRadius: 28 }}
      >
        {/* Status bar */}
        <div className="absolute inset-x-0 top-0 h-9 flex items-center justify-between px-6 text-[11px] font-medium text-gray-900 z-30">
          <span>9:41</span>
          <div className="flex items-center gap-1.5">
            <Icon name="signal" size={12} />
            <Icon name="wifi" size={12} />
            <Icon name="battery-full" size={14} />
          </div>
        </div>
        {/* Notch */}
        <div className="absolute top-2 left-1/2 -translate-x-1/2 w-24 h-5 bg-black rounded-full z-40" />
        <div className="absolute inset-0 pt-9">{children}</div>
      </div>
    </div>
  );
}

// ---------- Mobile app top bar (back + title + action) ----------
function MobileTopBar({ title, back = true, right }) {
  return (
    <div className="h-12 px-4 flex items-center justify-between border-b border-gray-100 bg-white">
      {back ? (
        <button className="w-8 h-8 -ml-2 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-700">
          <Icon name="chevron-left" size={20} />
        </button>
      ) : <div className="w-8" />}
      <div className="text-sm font-semibold text-gray-900 truncate">{title}</div>
      <div className="w-8 flex items-center justify-end">{right}</div>
    </div>
  );
}

// ---------- Mobile bottom tab bar (Face) ----------
function MobileTabBar({ active = 'missions' }) {
  const tabs = [
    { id: 'home', label: 'Accueil', icon: 'home' },
    { id: 'missions', label: 'Missions', icon: 'briefcase' },
    { id: 'bookings', label: 'Bookings', icon: 'calendar-check' },
    { id: 'profile', label: 'Profil', icon: 'user' },
  ];
  return (
    <div className="absolute inset-x-0 bottom-0 h-16 bg-white border-t border-gray-100 flex items-stretch">
      {tabs.map(t => (
        <button key={t.id} className="flex-1 flex flex-col items-center justify-center gap-0.5">
          <Icon name={t.icon} size={20} stroke={active === t.id ? 2.2 : 1.6}
            style={{ color: active === t.id ? '#198496' : '#9CA3AF' }} />
          <span className="text-[10px] font-medium" style={{ color: active === t.id ? '#198496' : '#9CA3AF' }}>{t.label}</span>
        </button>
      ))}
      <div className="absolute inset-x-0 bottom-0 h-1 flex justify-center pb-1">
        <div className="w-28 h-1 rounded-full bg-black/80" />
      </div>
    </div>
  );
}

// ---------- Chrono ring (countdown SVG, teal → orange) ----------
// progress 0..1 where 0 = just started, 1 = deadline reached
// We color-shift after 0.6 toward orange/red.
function ChronoRing({ progress = 0.3, size = 96, stroke = 8, label, sublabel, danger }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const off = c * (1 - Math.min(1, Math.max(0, progress)));
  // Color interpolation
  let color = '#198496';
  if (danger !== undefined ? danger : progress >= 0.85) color = '#DC2626';
  else if (progress >= 0.6) color = '#EA580C';
  else if (progress >= 0.4) color = '#F59E0B';
  return (
    <div className="relative inline-flex items-center justify-center" style={{ width: size, height: size }}>
      <svg className="chrono-svg" width={size} height={size}>
        <circle cx={size/2} cy={size/2} r={r} stroke="#E5E7EB" strokeWidth={stroke} fill="none" />
        <circle
          cx={size/2} cy={size/2} r={r}
          stroke={color} strokeWidth={stroke} fill="none"
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={off}
          style={{ transition: 'stroke-dashoffset .6s ease, stroke .4s ease' }}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
        <div className="text-base font-bold leading-none" style={{ color: '#0F1419' }}>{label}</div>
        {sublabel && <div className="text-[10px] font-medium uppercase tracking-wider text-gray-500 mt-1">{sublabel}</div>}
      </div>
    </div>
  );
}

// ---------- Linear chrono pill / badge ----------
function ChronoBadge({ progress = 0.3, label, size = 'sm' }) {
  let color = '#198496', bg = 'rgba(25,132,150,0.10)';
  if (progress >= 0.85) { color = '#DC2626'; bg = 'rgba(220,38,38,0.10)'; }
  else if (progress >= 0.6) { color = '#EA580C'; bg = 'rgba(234,88,12,0.10)'; }
  else if (progress >= 0.4) { color = '#F59E0B'; bg = 'rgba(245,158,11,0.12)'; }
  const px = size === 'lg' ? 'px-3 py-1.5 text-xs' : 'px-2.5 py-1 text-[11px]';
  return (
    <span className={`inline-flex items-center gap-1.5 ${px} rounded-full font-medium`}
      style={{ color, backgroundColor: bg }}>
      <Icon name="timer" size={size === 'lg' ? 14 : 12} stroke={2.2} />
      {label}
    </span>
  );
}

// ---------- Status pill (booking states) ----------
function StatusPill({ kind = 'pending', children }) {
  const map = {
    pending:   { c: '#0F1419', bg: '#F3F4F6' },
    paid:      { c: '#198496', bg: 'rgba(25,132,150,0.10)' },
    accepted:  { c: '#198496', bg: 'rgba(25,132,150,0.10)' },
    shipped:   { c: '#1D4ED8', bg: 'rgba(29,78,216,0.08)' },
    received:  { c: '#7C3AED', bg: 'rgba(124,58,237,0.08)' },
    delivered: { c: '#059669', bg: 'rgba(5,150,105,0.10)' },
    completed: { c: '#059669', bg: 'rgba(5,150,105,0.10)' },
    overdue:   { c: '#DC2626', bg: 'rgba(220,38,38,0.10)' },
    suspended: { c: '#DC2626', bg: 'rgba(220,38,38,0.10)' },
  };
  const s = map[kind] || map.pending;
  return (
    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium"
      style={{ color: s.c, backgroundColor: s.bg }}>
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: s.c }} />
      {children}
    </span>
  );
}

// ---------- Buttons ----------
function BtnPrimary({ children, full, sm, onClick, disabled }) {
  return (
    <button onClick={onClick} disabled={disabled}
      className={`${full ? 'w-full' : ''} ${sm ? 'px-3 py-1.5 text-xs' : 'px-5 py-2.5 text-sm'} font-medium rounded-md text-white transition-colors disabled:opacity-50`}
      style={{ background: '#198496' }}>
      {children}
    </button>
  );
}
function BtnOutline({ children, full, sm, onClick }) {
  return (
    <button onClick={onClick}
      className={`${full ? 'w-full' : ''} ${sm ? 'px-3 py-1.5 text-xs' : 'px-5 py-2.5 text-sm'} font-medium rounded-md transition-colors`}
      style={{ color: '#198496', border: '1px solid #198496' }}>
      {children}
    </button>
  );
}
function BtnGhost({ children, full, sm, onClick }) {
  return (
    <button onClick={onClick}
      className={`${full ? 'w-full' : ''} ${sm ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm'} font-medium rounded-md text-gray-700 hover:bg-gray-100 transition-colors`}>
      {children}
    </button>
  );
}

// ---------- Form field shells ----------
function Field({ label, hint, error, children, required }) {
  return (
    <label className="block">
      <div className="flex items-baseline justify-between mb-1.5">
        <span className="text-xs font-medium text-gray-700">
          {label} {required && <span className="text-[#198496]">*</span>}
        </span>
        {hint && <span className="text-[11px] text-gray-400">{hint}</span>}
      </div>
      {children}
      {error && <div className="mt-1 text-[11px] text-red-600">{error}</div>}
    </label>
  );
}
function TextInput({ placeholder, value, onChange, suffix, type = 'text', size = 'md' }) {
  const pad = size === 'lg' ? 'px-4 py-3 text-sm' : 'px-3 py-2 text-sm';
  return (
    <div className="relative">
      <input type={type} value={value || ''} onChange={e => onChange && onChange(e.target.value)}
        placeholder={placeholder}
        className={`w-full ${pad} bg-white border border-gray-200 rounded-md text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#198496] focus:ring-2 focus:ring-[#198496]/20 transition`} />
      {suffix && <div className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-500 font-medium pointer-events-none">{suffix}</div>}
    </div>
  );
}
function SegmentedToggle({ options, value, onChange }) {
  return (
    <div className="inline-flex p-1 bg-gray-100 rounded-md">
      {options.map(o => (
        <button key={o.value} onClick={() => onChange && onChange(o.value)}
          className={`px-3 py-1.5 text-xs font-medium rounded transition-all ${value === o.value
            ? 'bg-white text-gray-900 shadow-sm'
            : 'text-gray-500 hover:text-gray-700'}`}>
          {o.label}
        </button>
      ))}
    </div>
  );
}
function CompensationToggle({ value, onChange }) {
  // Big pill toggle: Produit seul ⇄ Produit + Argent
  return (
    <div className="inline-flex p-1 bg-gray-100 rounded-lg w-full">
      {[
        { v: 'product', label: 'Produit seul', sub: '2 vidéos fixes' },
        { v: 'hybrid',  label: 'Produit + Argent', sub: 'Nb vidéos libre' },
      ].map(o => (
        <button key={o.v} onClick={() => onChange && onChange(o.v)}
          className={`flex-1 flex flex-col items-start gap-0.5 px-4 py-2.5 rounded-md text-left transition-all ${
            value === o.v ? 'bg-white shadow-sm' : 'hover:bg-white/40'
          }`}>
          <span className={`text-sm font-semibold ${value === o.v ? 'text-gray-900' : 'text-gray-600'}`}>{o.label}</span>
          <span className="text-[10px] font-medium uppercase tracking-wider text-gray-400">{o.sub}</span>
        </button>
      ))}
    </div>
  );
}

// ---------- Dashboard chrome (Producteur desktop) ----------
function DashChrome({ children, page = 'bookings', density = 'airy' }) {
  const nav = [
    { id: 'home', label: 'Vue d\u2019ensemble', icon: 'layout-dashboard' },
    { id: 'bookings', label: 'Bookings', icon: 'calendar-check', count: 4 },
    { id: 'missions', label: 'Missions', icon: 'briefcase', count: 2 },
    { id: 'ugc', label: 'UGC', icon: 'video', count: 3, accent: true },
    { id: 'messages', label: 'Messages', icon: 'message-circle', count: 7 },
    { id: 'wallet', label: 'Portefeuille', icon: 'wallet' },
    { id: 'profile', label: 'Profil', icon: 'building-2' },
  ];
  return (
    <div className="flex h-full bg-[#FAFAF7]">
      {/* Sidebar */}
      <aside className="w-56 shrink-0 bg-white border-r border-gray-200 flex flex-col">
        <div className="h-14 px-5 flex items-center border-b border-gray-100">
          <WeActLogo size="md" />
        </div>
        <nav className="flex-1 px-3 py-4 space-y-0.5">
          {nav.map(n => {
            const active = n.id === page;
            return (
              <div key={n.id}
                className={`flex items-center gap-2.5 px-2.5 py-2 rounded-md text-sm transition-colors cursor-pointer ${
                  active
                    ? 'bg-[rgba(25,132,150,0.08)] text-[#198496] font-medium'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}>
                <Icon name={n.icon} size={16} stroke={active ? 2.2 : 1.7}
                  style={{ color: active ? '#198496' : '#6B7280' }} />
                <span className="flex-1">{n.label}</span>
                {n.count !== undefined && (
                  <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium ${
                    active ? 'bg-[#198496] text-white' : n.accent ? 'bg-[#198496] text-white' : 'bg-gray-100 text-gray-500'
                  }`}>{n.count}</span>
                )}
              </div>
            );
          })}
        </nav>
        <div className="px-3 py-3 border-t border-gray-100">
          <div className="px-2.5 py-2 rounded-md bg-gradient-to-br from-[rgba(25,132,150,0.06)] to-transparent border border-[rgba(25,132,150,0.15)]">
            <div className="flex items-center gap-1.5 text-[10px] font-bold text-[#198496] uppercase tracking-wider">
              <Icon name="sparkles" size={11} stroke={2.4} /> Plan Pro
            </div>
            <div className="text-[11px] text-gray-600 mt-1">Bookings UGC illimités</div>
          </div>
        </div>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Topbar */}
        <header className="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-6 shrink-0">
          <div className="flex items-center gap-2 text-xs text-gray-500">
            <span>Producteur</span>
            <Icon name="chevron-right" size={12} />
            <span className="text-gray-900 font-medium capitalize">{page}</span>
          </div>
          <div className="flex items-center gap-3">
            <button className="w-8 h-8 rounded-md hover:bg-gray-100 flex items-center justify-center text-gray-600 relative">
              <Icon name="bell" size={16} />
              <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-[#198496]" />
            </button>
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#198496] to-[#0F1419] flex items-center justify-center text-white text-xs font-semibold">SD</div>
          </div>
        </header>
        <main className={`flex-1 overflow-hidden ${density === 'compact' ? 'p-4' : 'p-8'}`}>
          {children}
        </main>
      </div>
    </div>
  );
}

// ---------- Pricing breakdown card (commission WeAct) ----------
function CommissionBreakdown({ productValue = 0, paid = 0, total = null, compact = false }) {
  const commission = Math.max(2500, Math.round(productValue * 0.10));
  const totalDue = total ?? (commission + (paid || 0));
  return (
    <div className={`rounded-lg border border-gray-200 bg-white ${compact ? 'p-3' : 'p-4'}`}>
      <div className="flex items-center gap-2 mb-3">
        <Icon name="receipt" size={14} stroke={2} style={{ color: '#198496' }} />
        <div className="text-xs font-semibold text-gray-900 uppercase tracking-wider">Récapitulatif</div>
      </div>
      <div className="space-y-2">
        <Row label="Valeur déclarée du produit" value={`${(productValue || 0).toLocaleString('fr-FR')} FCFA`} muted />
        {paid > 0 && <Row label="Rémunération de la Face" value={`${paid.toLocaleString('fr-FR')} FCFA`} muted />}
        <Row label={
          <span className="flex items-center gap-1.5">Commission WeAct
            <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">10% min. 2 500</span>
          </span>
        } value={`${commission.toLocaleString('fr-FR')} FCFA`} accent />
        <div className="border-t border-gray-100 pt-2 mt-2">
          <Row label={<span className="font-semibold text-gray-900">À payer maintenant</span>}
               value={<span className="text-base font-bold text-gray-900">{commission.toLocaleString('fr-FR')} FCFA</span>} />
          <div className="text-[10px] text-gray-400 mt-1">Le produit et la rémunération sont gérés directement par vous. WeAct ne facture que sa commission.</div>
        </div>
      </div>
    </div>
  );
}
function Row({ label, value, muted, accent }) {
  return (
    <div className="flex items-center justify-between text-xs">
      <span className={muted ? 'text-gray-500' : accent ? 'text-[#198496] font-medium' : 'text-gray-700'}>{label}</span>
      <span className={muted ? 'text-gray-700' : accent ? 'text-[#198496] font-semibold' : 'text-gray-900 font-medium'}>{value}</span>
    </div>
  );
}

// ---------- Booking timeline (6 steps) ----------
const STEPS = [
  { id: 1, key: 'paid',     short: 'Paiement',    desc: 'Commission WeAct payée' },
  { id: 2, key: 'accepted', short: 'Acceptation', desc: 'La Face accepte le deal' },
  { id: 3, key: 'shipped',  short: 'Expédition',  desc: 'Produit envoyé + tracking' },
  { id: 4, key: 'received', short: 'Réception',   desc: 'Produit reçu — chrono démarre' },
  { id: 5, key: 'video1',   short: 'Unboxing',    desc: 'Vidéo 1 sous 7 jours' },
  { id: 6, key: 'video2',   short: 'Avis',        desc: 'Vidéo 2 sous 14 jours' },
];

function BookingTimelineV({ current = 4, overdue = false, compact = false }) {
  return (
    <ol className={`space-y-${compact ? 3 : 4}`}>
      {STEPS.map(s => {
        const done = s.id < current;
        const active = s.id === current;
        const isOverdue = active && overdue;
        return (
          <li key={s.id} className="flex gap-3">
            <div className="flex flex-col items-center">
              <div className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold border-2 ${
                done ? 'bg-[#198496] border-[#198496] text-white' :
                active ? (isOverdue ? 'bg-white border-red-500 text-red-600' : 'bg-white border-[#198496] text-[#198496]') :
                'bg-white border-gray-200 text-gray-400'
              }`}>
                {done ? <Icon name="check" size={12} stroke={3} /> : s.id}
              </div>
              {s.id < STEPS.length && (
                <div className={`w-0.5 flex-1 mt-1 mb-1 ${done ? 'bg-[#198496]' : 'bg-gray-200'}`} style={{ minHeight: compact ? 18 : 26 }} />
              )}
            </div>
            <div className="flex-1 pb-1">
              <div className={`text-xs font-semibold ${active ? (isOverdue ? 'text-red-600' : 'text-gray-900') : done ? 'text-gray-900' : 'text-gray-400'}`}>
                {s.short}
              </div>
              <div className={`text-[11px] mt-0.5 ${active || done ? 'text-gray-500' : 'text-gray-400'}`}>{s.desc}</div>
            </div>
          </li>
        );
      })}
    </ol>
  );
}

function BookingTimelineH({ current = 4, overdue = false }) {
  return (
    <div className="flex items-center w-full">
      {STEPS.map((s, i) => {
        const done = s.id < current;
        const active = s.id === current;
        const isOverdue = active && overdue;
        return (
          <React.Fragment key={s.id}>
            <div className="flex flex-col items-center gap-1.5 flex-shrink-0" style={{ width: 84 }}>
              <div className={`w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold border-2 ${
                done ? 'bg-[#198496] border-[#198496] text-white' :
                active ? (isOverdue ? 'bg-white border-red-500 text-red-600' : 'bg-white border-[#198496] text-[#198496]') :
                'bg-white border-gray-200 text-gray-400'
              }`}>
                {done ? <Icon name="check" size={13} stroke={3} /> : s.id}
              </div>
              <div className="text-center">
                <div className={`text-[10px] font-semibold ${active ? (isOverdue ? 'text-red-600' : 'text-[#198496]') : done ? 'text-gray-900' : 'text-gray-400'}`}>
                  {s.short}
                </div>
              </div>
            </div>
            {i < STEPS.length - 1 && (
              <div className={`flex-1 h-0.5 ${done ? 'bg-[#198496]' : 'bg-gray-200'}`} style={{ marginTop: -22 }} />
            )}
          </React.Fragment>
        );
      })}
    </div>
  );
}

// ---------- Payment provider tile ----------
function PayTile({ provider, selected, onSelect }) {
  const cfg = {
    mtn:      { name: 'MTN MoMo', sub: '+229 6X XX XX XX', color: '#FFCC00', dark: '#0F1419', initials: 'MTN' },
    moov:     { name: 'Moov Money', sub: '+229 9X XX XX XX', color: '#0066B3', dark: '#fff', initials: 'M' },
    fedapay:  { name: 'Carte bancaire', sub: 'Visa / Mastercard via FedaPay', color: '#0F1419', dark: '#fff', initials: 'FP' },
  }[provider];
  return (
    <button onClick={onSelect}
      className={`w-full flex items-center gap-3 p-3 rounded-md border text-left transition-all ${
        selected ? 'border-[#198496] bg-[rgba(25,132,150,0.04)] ring-2 ring-[#198496]/20'
                 : 'border-gray-200 bg-white hover:border-gray-300'
      }`}>
      <div className="w-10 h-10 rounded-md flex items-center justify-center text-xs font-bold flex-shrink-0"
        style={{ background: cfg.color, color: cfg.dark }}>{cfg.initials}</div>
      <div className="flex-1 min-w-0">
        <div className="text-sm font-semibold text-gray-900">{cfg.name}</div>
        <div className="text-[11px] text-gray-500 truncate">{cfg.sub}</div>
      </div>
      <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${selected ? 'border-[#198496] bg-[#198496]' : 'border-gray-300'}`}>
        {selected && <div className="w-2 h-2 rounded-full bg-white" />}
      </div>
    </button>
  );
}

// ---------- Section header for artboards ----------
function SectionHead({ kicker, title, sub }) {
  return (
    <div className="mb-3">
      {kicker && <div className="text-[10px] font-bold text-[#198496] uppercase tracking-widest mb-1">{kicker}</div>}
      <div className="text-base font-semibold text-gray-900">{title}</div>
      {sub && <div className="text-xs text-gray-500 mt-0.5">{sub}</div>}
    </div>
  );
}

// ---------- Anti-scam reassurance card ----------
function ReassuranceCard({ compact }) {
  const items = [
    { ic: 'shield-check', t: 'Votre commission n\u2019est encaissée qu\u2019après acceptation', d: 'Si la Face refuse, vous êtes remboursé sous 24h.' },
    { ic: 'package-check', t: 'L\u2019envoi se déclenche après acceptation', d: 'Vous n\u2019envoyez le produit qu\u2019une fois la Face engagée.' },
    { ic: 'timer', t: 'Chronomètres de livraison opposables', d: 'Toute Face en retard est suspendue automatiquement.' },
    { ic: 'replace', t: 'Remplacement gratuit en cas d\u2019abandon', d: 'WeAct vous propose un autre talent sans frais.' },
  ];
  return (
    <div className={`rounded-lg border border-[rgba(25,132,150,0.2)] bg-[rgba(25,132,150,0.03)] ${compact ? 'p-3' : 'p-4'}`}>
      <div className="flex items-center gap-2 mb-3">
        <div className="w-7 h-7 rounded-md bg-[#198496] flex items-center justify-center">
          <Icon name="shield-check" size={14} stroke={2.2} style={{ color: '#fff' }} />
        </div>
        <div className="text-sm font-semibold text-gray-900">Comment WeAct protège votre envoi</div>
      </div>
      <ul className="space-y-2.5">
        {items.map((it, i) => (
          <li key={i} className="flex gap-2.5">
            <Icon name={it.ic} size={14} stroke={2} style={{ color: '#198496', marginTop: 2 }} />
            <div className="flex-1">
              <div className="text-xs font-medium text-gray-900">{it.t}</div>
              <div className="text-[11px] text-gray-500 leading-snug">{it.d}</div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

// ---------- Face profile preview card (for mission lists / detail) ----------
function FaceMini({ name, location, rating, followers, size = 'sm' }) {
  return (
    <div className="flex items-center gap-2.5">
      <div className={`${size === 'lg' ? 'w-10 h-10' : 'w-8 h-8'} rounded-full ph-stripe border border-gray-200 flex-shrink-0`} />
      <div className="min-w-0">
        <div className="text-xs font-semibold text-gray-900 truncate">{name}</div>
        <div className="text-[10px] text-gray-500 flex items-center gap-1.5">
          <Icon name="map-pin" size={9} /> {location}
          {rating && <><span>·</span><Icon name="star" size={9} style={{ color: '#198496' }} /> {rating}</>}
        </div>
      </div>
    </div>
  );
}

// ---------- Empty / locked overlay ----------
function LockedOverlay({ children, label = 'Réservé aux abonnés Starter+' }) {
  return (
    <div className="relative">
      <div className="filter blur-[3px] pointer-events-none select-none opacity-80">{children}</div>
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-white/40 to-white/90 pointer-events-none" />
      <div className="absolute inset-x-0 bottom-0 p-4 text-center">
        <div className="inline-flex items-center gap-1.5 text-[10px] font-bold text-[#198496] uppercase tracking-widest mb-2">
          <Icon name="lock" size={11} stroke={2.4} /> {label}
        </div>
      </div>
    </div>
  );
}

// ---------- Expose to window ----------
Object.assign(window, {
  Icon, WeActLogo, StripePh, PhoneFrame, MobileTopBar, MobileTabBar,
  ChronoRing, ChronoBadge, StatusPill,
  BtnPrimary, BtnOutline, BtnGhost,
  Field, TextInput, SegmentedToggle, CompensationToggle,
  DashChrome, CommissionBreakdown, Row,
  BookingTimelineV, BookingTimelineH, STEPS,
  PayTile, SectionHead, ReassuranceCard, FaceMini, LockedOverlay,
});
