/* WeAct — Facturation & Abonnement (Face dashboard) */

const C_TEAL = '#198496';
const C_TEAL_HOVER = '#146c7a';
const C_DARK = '#0F1419';
const C_RED = '#DC2626';

// ----- icons (lucide-style, stroke 2) -----
const Ic = {
  Home: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 9.5L12 3l9 6.5"/><path d="M5 10v10h14V10"/></svg>,
  Briefcase: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>,
  FileText: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3v5h5"/><path d="M19 8v11a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h8z"/><path d="M9 13h6M9 17h4"/></svg>,
  Message: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-9 8.3 9 9 0 0 1-3.9-.8L3 21l1.9-4.1A8.38 8.38 0 0 1 3.7 11 8.5 8.5 0 1 1 21 11.5z"/></svg>,
  Wallet: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v10a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3"/><path d="M21 9v6h-5a3 3 0 0 1 0-6h5z"/></svg>,
  CreditCard: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>,
  User: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>,
  Chevron: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9l6 6 6-6"/></svg>,
  Check: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M4 12.5L9 17.5L20 6.5"/></svg>,
  Calendar: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/></svg>,
  ArrowUpRight: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 17L17 7M8 7h9v9"/></svg>,
  X: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>,
  Receipt: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 3v18l2.5-1.5L10 21l2-1.5L14 21l2.5-1.5L19 21V3l-2.5 1.5L14 3l-2 1.5L10 3 7.5 4.5z"/><path d="M9 8h6M9 12h6"/></svg>,
  AlertTriangle: (p) => <svg {...sz(p)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l9.5 16.5H2.5z"/><path d="M12 9v5M12 17.5v.5"/></svg>,
};
function sz(p) { const s = (p && p.size) || 20; return { width: s, height: s, style: { flexShrink: 0, ...(p && p.style) } }; }

// ----- WeAct burst badge (V13) -----
function burstPath(cx, cy, oR, iR, pts) {
  const step = (Math.PI * 2) / (pts * 2);
  let d = '';
  for (let i = 0; i < pts * 2; i++) {
    const a = i * step - Math.PI / 2;
    const r = i % 2 === 0 ? oR : iR;
    const x = cx + r * Math.cos(a), y = cy + r * Math.sin(a);
    d += i === 0 ? `M ${x.toFixed(2)} ${y.toFixed(2)}` : ` L ${x.toFixed(2)} ${y.toFixed(2)}`;
  }
  return d + ' Z';
}
const BURST8 = burstPath(12, 12, 11.5, 10, 8);
const BURST6 = burstPath(12, 12, 11.5, 10.2, 6);
function WBadge({ size = 18, tier = 'elite', title }) {
  const fill = tier === 'elite' ? C_DARK : C_TEAL;
  const path = size < 18 ? BURST6 : BURST8;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" role="img" aria-label={title} style={{ flexShrink: 0 }}>
      <title>{title}</title>
      <path d={path} fill={fill} />
      <text x="12" y="16" textAnchor="middle" fontSize="11" fontWeight="800" fill="#fff" fontFamily="Inter, sans-serif" style={{ letterSpacing: '-0.04em' }}>W</text>
    </svg>
  );
}

// ----- helpers -----
const TIER_META = {
  decouverte: { name: 'Découverte', price: 0, priceLabel: '0', badge: null },
  starter: { name: 'Starter', price: 12000, priceLabel: '12 000', badge: null },
  pro: { name: 'Pro', price: 25000, priceLabel: '25 000', badge: 'pro' },
  elite: { name: 'Élite', price: 40000, priceLabel: '40 000', badge: 'elite' },
};
const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
function fmtDate(iso) {
  const d = new Date(iso);
  return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}
function fmtFCFA(n) {
  return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA';
}
function daysBetween(a, b) {
  return Math.round((new Date(b) - new Date(a)) / 86400000);
}

window.C_TEAL = C_TEAL; window.C_TEAL_HOVER = C_TEAL_HOVER; window.C_DARK = C_DARK; window.C_RED = C_RED;
window.Ic = Ic; window.WBadge = WBadge;
window.TIER_META = TIER_META; window.fmtDate = fmtDate; window.fmtFCFA = fmtFCFA; window.daysBetween = daysBetween;
