/* Final integrated pricing page — V2 cards + V1 table combo */

const F_TEAL = '#198496';
const F_TEAL_HOVER = '#146c7a';
const F_DARK = '#0F1419';

const FCheck = ({ size = 16, stroke = F_TEAL }) => (
  <svg width={size} height={size} viewBox="0 0 16 16" fill="none" style={{ flexShrink: 0, marginTop: 2 }} xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M3 8.5L6.5 12L13 5" stroke={stroke} strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);
const FDash = ({ size = 16 }) => (
  <svg width={size} height={size} viewBox="0 0 16 16" fill="none" style={{ flexShrink: 0, marginTop: 2 }} xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M4 8h8" stroke="#D1D5DB" strokeWidth="1.75" strokeLinecap="round" />
  </svg>
);
const FCrown = ({ size = 14 }) => (
  <svg width={size} height={size} viewBox="0 0 16 16" fill="none" style={{ flexShrink: 0 }} xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M2 5l2.5 2L8 3l3.5 4L14 5v6.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5V5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
  </svg>
);
const FChevron = ({ size = 16, open }) => (
  <svg width={size} height={size} viewBox="0 0 16 16" fill="none" style={{ flexShrink: 0, transition: 'transform 200ms', transform: open ? 'rotate(180deg)' : 'rotate(0)' }} xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M4 6l4 4 4-4" stroke="#9CA3AF" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);

// =============================================================
// Header (mirrors weact header layout)
// =============================================================
function Header() {
  return (
    <header style={{ borderBottom: '1px solid #E5E7EB', background: '#fff' }}>
      <div style={{ maxWidth: 1280, margin: '0 auto', padding: '16px 24px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 32 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <span style={{ fontSize: 20, fontWeight: 700, color: '#111827', letterSpacing: '-0.01em' }}>WE</span>
          <span style={{ fontSize: 20, fontWeight: 700, color: F_TEAL, letterSpacing: '-0.01em' }}>ACT</span>
        </div>
        <nav style={{ display: 'flex', gap: 32 }}>
          {['Trouver des faces', 'Missions', 'Ressources', 'Tarifs'].map((l, i) => (
            <a key={l} style={{ fontSize: 13.5, color: i === 3 ? F_TEAL : '#374151', fontWeight: i === 3 ? 600 : 400, textDecoration: 'none' }}>{l}</a>
          ))}
        </nav>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <button style={{ fontSize: 13, fontWeight: 500, background: F_TEAL, color: '#fff', padding: '8px 18px', borderRadius: 6, border: 'none', cursor: 'pointer' }}>Poster une mission</button>
          <button style={{ fontSize: 13, fontWeight: 500, background: '#fff', color: F_TEAL, padding: '8px 18px', borderRadius: 6, border: `1px solid ${F_TEAL}`, cursor: 'pointer' }}>Devenir une face</button>
          <a style={{ fontSize: 13, color: '#374151', cursor: 'pointer' }}>Se connecter</a>
        </div>
      </div>
    </header>
  );
}

// =============================================================
// Hero
// =============================================================
function Hero() {
  return (
    <div style={{ textAlign: 'center', padding: '72px 24px 56px', maxWidth: 720, margin: '0 auto' }}>
      <div style={{ fontSize: 12, fontWeight: 600, color: F_TEAL, letterSpacing: '0.12em', textTransform: 'uppercase', marginBottom: 14 }}>Abonnements Faces · Tarifs 2026</div>
      <h1 style={{ fontSize: 44, fontWeight: 700, color: '#0F1419', letterSpacing: '-0.025em', lineHeight: 1.1, marginBottom: 16 }}>Plus tu montes, plus tu décroches.</h1>
      <p style={{ fontSize: 16, color: '#6B7280', lineHeight: 1.6, maxWidth: 560, margin: '0 auto' }}>
        Quatre paliers pensés pour les talents béninois et ouest-africains. Du profil découverte gratuit au statut VIP, chaque palier débloque plus de portfolio, de visibilité et de missions UGC.
      </p>
    </div>
  );
}

// =============================================================
// Pricing cards — Ladder + Élite sombre
// =============================================================
function PricingCards() {
  const tiers = window.WEACT_TIERS;
  return (
    <div style={{ maxWidth: 1280, margin: '0 auto', padding: '0 24px 80px' }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 0, alignItems: 'stretch', borderRadius: 16, overflow: 'hidden', boxShadow: '0 4px 32px -16px rgba(15, 20, 25, 0.10)', border: '1px solid #E5E7EB' }}>
        {tiers.map((t, idx) => {
          const isElite = t.key === 'elite';
          const isPro = t.key === 'pro';
          const bg = isElite ? F_DARK : '#fff';
          const textColor = isElite ? '#fff' : '#111827';
          const subColor = isElite ? '#9CA3AF' : '#6B7280';
          const muted = isElite ? '#6B7280' : '#9CA3AF';

          return (
            <div key={t.key} style={{
              background: bg,
              padding: '36px 26px 32px',
              display: 'flex',
              flexDirection: 'column',
              position: 'relative',
              borderRight: idx < tiers.length - 1 && !isElite ? '1px solid #E5E7EB' : 'none',
            }}>
              {/* Tier ladder indicator */}
              <div style={{ display: 'flex', gap: 4, marginBottom: 20 }}>
                {[0,1,2,3].map(i => (
                  <div key={i} style={{
                    width: 20, height: 3, borderRadius: 2,
                    background: i <= idx ? (isElite ? '#fff' : F_TEAL) : (isElite ? '#2A3138' : '#E5E7EB'),
                  }} />
                ))}
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
                <h3 style={{ fontSize: 20, fontWeight: 700, color: textColor, letterSpacing: '-0.01em', margin: 0 }}>{t.name}</h3>
                {isElite && <FCrown />}
                {t.badge && !isElite && (
                  <span style={{ fontSize: 9, fontWeight: 700, color: '#fff', background: F_TEAL, padding: '3px 7px', borderRadius: 4, letterSpacing: '0.12em', textTransform: 'uppercase' }}>{t.badge}</span>
                )}
              </div>
              <p style={{ fontSize: 13, color: subColor, lineHeight: 1.5, marginBottom: 24, minHeight: 38, marginTop: 0 }}>{t.tagline}</p>

              <div style={{ marginBottom: 24 }}>
                <div style={{ display: 'flex', alignItems: 'baseline', gap: 4 }}>
                  <span style={{ fontSize: 38, fontWeight: 700, color: textColor, letterSpacing: '-0.025em', lineHeight: 1 }}>{t.priceLabel}</span>
                  {t.price !== 0 && <span style={{ fontSize: 13, color: subColor, fontWeight: 500 }}>FCFA</span>}
                </div>
                <div style={{ fontSize: 12, color: muted, marginTop: 6 }}>{t.period === 'Gratuit' ? 'Offre découverte' : 'par an · sans engagement'}</div>
              </div>

              <button style={{
                width: '100%',
                padding: '11px 16px',
                fontSize: 13,
                fontWeight: 600,
                borderRadius: 8,
                border: 'none',
                background: isElite ? '#fff' : isPro ? F_TEAL : 'transparent',
                color: isElite ? F_DARK : isPro ? '#fff' : F_TEAL,
                boxShadow: !isPro && !isElite ? `inset 0 0 0 1px ${F_TEAL}` : 'none',
                cursor: 'pointer',
                marginBottom: 28,
              }}>{t.cta}</button>

              <div style={{ fontSize: 11, fontWeight: 700, color: muted, textTransform: 'uppercase', letterSpacing: '0.10em', marginBottom: 14 }}>Inclus</div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 11, flexGrow: 1 }}>
                {t.features.filter(f => f.included).map((f, i) => (
                  <div key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 9 }}>
                    <FCheck stroke={isElite ? '#fff' : F_TEAL} />
                    <span style={{
                      fontSize: 13,
                      color: isElite ? (f.highlight ? '#fff' : '#D1D5DB') : '#374151',
                      lineHeight: 1.5,
                      fontWeight: f.highlight ? 600 : 400,
                    }}>{f.text}</span>
                  </div>
                ))}
              </div>

              {t.key === 'decouverte' && (
                <div style={{ marginTop: 16, paddingTop: 16, borderTop: '1px solid #F3F4F6', fontSize: 11, color: '#9CA3AF', lineHeight: 1.5 }}>
                  Pas d'accès aux missions UGC
                </div>
              )}
            </div>
          );
        })}
      </div>

      <p style={{ textAlign: 'center', fontSize: 12, color: '#9CA3AF', marginTop: 20 }}>
        Tous les abonnements sont annuels et payables une seule fois. Mobile Money, carte bancaire et virement acceptés.
      </p>
    </div>
  );
}

// =============================================================
// Comparison table — Classic grouped
// =============================================================
function ComparisonTable() {
  const data = window.WEACT_COMPARE;
  const TIERS = ['decouverte', 'starter', 'pro', 'elite'];
  const LABELS = { decouverte: 'Découverte', starter: 'Starter', pro: 'Pro', elite: 'Élite' };
  const PRICES = { decouverte: '0', starter: '12 000', pro: '25 000', elite: '40 000' };

  const renderCell = (v, isElite) => {
    if (v === true) return <FCheck stroke={isElite ? '#fff' : F_TEAL} />;
    if (v === false) return <FDash />;
    return <span style={{ fontSize: 13, color: isElite ? '#fff' : '#111827', fontWeight: 500 }}>{v}</span>;
  };

  return (
    <div style={{ background: '#FAFAFA', padding: '80px 24px' }}>
      <div style={{ maxWidth: 1280, margin: '0 auto' }}>
        <div style={{ textAlign: 'center', marginBottom: 48 }}>
          <div style={{ fontSize: 12, fontWeight: 600, color: F_TEAL, letterSpacing: '0.12em', textTransform: 'uppercase', marginBottom: 10 }}>Comparatif détaillé</div>
          <h2 style={{ fontSize: 32, fontWeight: 700, color: '#0F1419', letterSpacing: '-0.025em', marginBottom: 12 }}>Toutes les fonctionnalités, palier par palier</h2>
          <p style={{ fontSize: 14, color: '#6B7280' }}>Ce que tu débloques exactement à chaque niveau d'abonnement.</p>
        </div>

        <div style={{ border: '1px solid #E5E7EB', borderRadius: 14, overflow: 'hidden', background: '#fff' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', tableLayout: 'fixed' }}>
            <colgroup>
              <col style={{ width: '32%' }} />
              <col style={{ width: '17%' }} />
              <col style={{ width: '17%' }} />
              <col style={{ width: '17%' }} />
              <col style={{ width: '17%' }} />
            </colgroup>
            <thead>
              <tr style={{ background: '#FAFAFA' }}>
                <th style={{ padding: '24px 24px 20px', textAlign: 'left', verticalAlign: 'top', borderBottom: '1px solid #E5E7EB' }}>
                  <div style={{ fontSize: 11, fontWeight: 600, color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: '0.12em' }}>Comparatif</div>
                  <div style={{ fontSize: 14, color: '#374151', marginTop: 8, fontWeight: 500 }}>Tarifs annuels en FCFA</div>
                </th>
                {TIERS.map(k => {
                  const isElite = k === 'elite';
                  const isPro = k === 'pro';
                  return (
                    <th key={k} style={{
                      padding: '24px 16px 20px',
                      textAlign: 'left',
                      verticalAlign: 'top',
                      background: isElite ? F_DARK : '#FAFAFA',
                      color: isElite ? '#fff' : '#111827',
                      borderBottom: isElite ? `1px solid ${F_DARK}` : '1px solid #E5E7EB',
                      position: 'relative',
                    }}>
                      {isPro && (
                        <div style={{ position: 'absolute', top: 14, right: 14, fontSize: 9, fontWeight: 700, letterSpacing: '0.12em', textTransform: 'uppercase', color: '#fff', background: F_TEAL, padding: '2px 7px', borderRadius: 4 }}>Populaire</div>
                      )}
                      {isElite && (
                        <div style={{ position: 'absolute', top: 14, right: 14, fontSize: 9, fontWeight: 700, letterSpacing: '0.12em', textTransform: 'uppercase', color: F_DARK, background: '#fff', padding: '2px 7px', borderRadius: 4 }}>VIP</div>
                      )}
                      <div style={{ fontSize: 16, fontWeight: 700 }}>{LABELS[k]}</div>
                      <div style={{ display: 'flex', alignItems: 'baseline', gap: 4, marginTop: 6 }}>
                        <span style={{ fontSize: 22, fontWeight: 700, letterSpacing: '-0.02em' }}>{PRICES[k]}</span>
                        {k !== 'decouverte' && <span style={{ fontSize: 11, color: isElite ? '#9CA3AF' : '#6B7280' }}>FCFA / an</span>}
                        {k === 'decouverte' && <span style={{ fontSize: 11, color: '#6B7280' }}>· gratuit</span>}
                      </div>
                      <button style={{
                        marginTop: 14,
                        padding: '7px 14px',
                        fontSize: 12,
                        fontWeight: 600,
                        borderRadius: 6,
                        border: 'none',
                        background: isElite ? '#fff' : isPro ? F_TEAL : 'transparent',
                        color: isElite ? F_DARK : isPro ? '#fff' : F_TEAL,
                        boxShadow: !isElite && !isPro ? `inset 0 0 0 1px ${F_TEAL}` : 'none',
                        cursor: 'pointer',
                      }}>Choisir</button>
                    </th>
                  );
                })}
              </tr>
            </thead>
            <tbody>
              {data.groups.map((group) => (
                <React.Fragment key={group.label}>
                  <tr>
                    <td colSpan={4} style={{ padding: '14px 24px', fontSize: 11, fontWeight: 700, color: F_TEAL, textTransform: 'uppercase', letterSpacing: '0.14em', background: '#F9FAFB', borderTop: '1px solid #E5E7EB', borderBottom: '1px solid #E5E7EB' }}>
                      {group.label}
                    </td>
                    <td style={{ background: F_DARK, borderTop: '1px solid #E5E7EB', borderBottom: '1px solid #E5E7EB' }}></td>
                  </tr>
                  {group.rows.map((row, i) => (
                    <tr key={i}>
                      <td style={{ padding: '14px 24px', fontSize: 13, color: '#374151', fontWeight: 500, borderBottom: '1px solid #F3F4F6' }}>{row.name}</td>
                      {TIERS.map(k => {
                        const isElite = k === 'elite';
                        return (
                          <td key={k} style={{
                            padding: '14px 16px',
                            fontSize: 13,
                            background: isElite ? F_DARK : 'transparent',
                            color: isElite ? '#fff' : '#111827',
                            borderBottom: isElite ? '1px solid #2A3138' : '1px solid #F3F4F6',
                          }}>{renderCell(row[k], isElite)}</td>
                        );
                      })}
                    </tr>
                  ))}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// =============================================================
// FAQ
// =============================================================
function FAQ() {
  const [open, setOpen] = React.useState(0);
  const items = [
    { q: "Puis-je changer de palier à tout moment ?", a: "Oui. Tu peux passer à un autre palier quand tu veux. Le nouveau palier est facturé au prix annuel plein et démarre une nouvelle période de 12 mois — les jours restants de ton abonnement en cours ne sont pas reportés. Le nombre de jours perdus t'est indiqué avant la confirmation." },
    { q: "Comment fonctionne la commission réduite Élite ?", a: "Sur toutes les missions rémunérées en argent, la plateforme prélève 10 % de frais. Avec l'abonnement Élite, ce taux passe à 5 % — soit la moitié. Sur 100 000 FCFA de cachet, tu encaisses 95 000 FCFA au lieu de 90 000 FCFA." },
    { q: "Quels moyens de paiement acceptez-vous ?", a: "Mobile Money (MTN, Moov), carte bancaire internationale (Visa, Mastercard), et virement bancaire pour les paliers Pro et Élite." },
    { q: "Que se passe-t-il si mon abonnement expire ?", a: "Ton profil bascule automatiquement sur l'offre Découverte gratuite. Tes photos et vidéos restent stockées 90 jours, le temps de te réabonner si tu le souhaites." },
    { q: "Comment fonctionne la mise en avant ?", a: "Dans la recherche des producteurs, les profils sont triés par palier : Élite en premier, puis Pro, puis Starter, puis Découverte. À palier égal, c'est l'algorithme classique de pertinence qui s'applique." },
  ];
  return (
    <div style={{ maxWidth: 760, margin: '0 auto', padding: '80px 24px' }}>
      <div style={{ textAlign: 'center', marginBottom: 40 }}>
        <h2 style={{ fontSize: 28, fontWeight: 700, color: '#111827', letterSpacing: '-0.02em', marginBottom: 8 }}>Questions fréquentes</h2>
        <p style={{ fontSize: 14, color: '#6B7280' }}>Tout ce que tu dois savoir avant de souscrire.</p>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        {items.map((item, i) => (
          <div key={i} style={{ border: '1px solid #E5E7EB', borderRadius: 10, background: '#fff', overflow: 'hidden' }}>
            <button onClick={() => setOpen(open === i ? -1 : i)} style={{
              width: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              padding: '18px 22px',
              border: 'none',
              background: 'transparent',
              cursor: 'pointer',
              textAlign: 'left',
              fontFamily: 'inherit',
            }}>
              <span style={{ fontSize: 14, fontWeight: 600, color: '#111827' }}>{item.q}</span>
              <FChevron open={open === i} />
            </button>
            {open === i && (
              <div style={{ padding: '0 22px 18px', fontSize: 13.5, color: '#6B7280', lineHeight: 1.65 }}>
                {item.a}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

// =============================================================
// Footer CTA
// =============================================================
function FooterCTA() {
  return (
    <div style={{ background: '#FAFAFA', padding: '0 24px 80px' }}>
      <div style={{ maxWidth: 880, margin: '0 auto', background: '#fff', border: '1px solid #E5E7EB', borderRadius: 16, padding: '40px 48px', textAlign: 'center' }}>
        <h3 style={{ fontSize: 22, fontWeight: 700, color: '#111827', letterSpacing: '-0.02em', marginBottom: 10 }}>Tu hésites encore ?</h3>
        <p style={{ fontSize: 14, color: '#6B7280', lineHeight: 1.6, marginBottom: 24, maxWidth: 480, margin: '0 auto 24px' }}>
          Commence avec l'offre Découverte, crée ton profil, et passe à un palier supérieur dès que tu veux candidater à des missions UGC.
        </p>
        <div style={{ display: 'flex', gap: 12, justifyContent: 'center' }}>
          <button style={{ fontSize: 13, fontWeight: 600, background: F_TEAL, color: '#fff', padding: '11px 22px', borderRadius: 8, border: 'none', cursor: 'pointer' }}>Créer mon profil gratuit</button>
          <button style={{ fontSize: 13, fontWeight: 600, background: '#fff', color: F_TEAL, padding: '11px 22px', borderRadius: 8, border: `1px solid ${F_TEAL}`, cursor: 'pointer' }}>Nous contacter</button>
        </div>
      </div>
    </div>
  );
}

window.FinalHeader = Header;
window.FinalHero = Hero;
window.FinalPricingCards = PricingCards;
window.FinalComparisonTable = ComparisonTable;
window.FinalFAQ = FAQ;
window.FinalFooterCTA = FooterCTA;
