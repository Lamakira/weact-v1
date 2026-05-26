/* V13 — Système complet du badge WeAct (monogramme W) */

const W_TEAL = '#198496';
const W_DARK = '#0F1419';

function burst(cx, cy, outerR, innerR, points) {
  const step = (Math.PI * 2) / (points * 2);
  let d = '';
  for (let i = 0; i < points * 2; i++) {
    const angle = i * step - Math.PI / 2;
    const r = i % 2 === 0 ? outerR : innerR;
    const x = cx + r * Math.cos(angle);
    const y = cy + r * Math.sin(angle);
    d += i === 0 ? `M ${x.toFixed(2)} ${y.toFixed(2)}` : ` L ${x.toFixed(2)} ${y.toFixed(2)}`;
  }
  return d + ' Z';
}

// canonical paths
const BURST_8_PATH = burst(12, 12, 11.5, 10, 8);
const BURST_6_PATH = burst(12, 12, 11.5, 10.2, 6); // simplified for small sizes

// =============================================================
// The badge component — reusable
// =============================================================
function WBadge({ size = 24, tier = 'elite', title }) {
  const fill = tier === 'elite' ? W_DARK : W_TEAL;
  // Use simpler 6-point burst under 18px for legibility
  const path = size < 18 ? BURST_6_PATH : BURST_8_PATH;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-label={title} role={title ? 'img' : undefined}>
      {title && <title>{title}</title>}
      <path d={path} fill={fill} />
      <text
        x="12"
        y="16"
        textAnchor="middle"
        fontSize="11"
        fontWeight="800"
        fill="#fff"
        fontFamily="Inter, system-ui, sans-serif"
        style={{ letterSpacing: '-0.04em' }}
      >W</text>
    </svg>
  );
}

// =============================================================
// 1. Size scale (test legibility)
// =============================================================
function SizeScale() {
  const sizes = [14, 16, 20, 24, 32, 40, 56, 80];
  return (
    <div style={{ padding: '40px 48px', width: 880, background: '#fff' }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: '0.12em', marginBottom: 24 }}>Échelle Élite (dark)</div>
      <div style={{ display: 'flex', alignItems: 'flex-end', gap: 28, marginBottom: 40 }}>
        {sizes.map(s => (
          <div key={s} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
            <WBadge size={s} tier="elite" title="Élite" />
            <span style={{ fontSize: 11, color: '#9CA3AF', fontWeight: 500 }}>{s}px</span>
          </div>
        ))}
      </div>

      <div style={{ fontSize: 11, fontWeight: 600, color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: '0.12em', marginBottom: 24 }}>Échelle Pro (teal)</div>
      <div style={{ display: 'flex', alignItems: 'flex-end', gap: 28 }}>
        {sizes.map(s => (
          <div key={s} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
            <WBadge size={s} tier="pro" title="Pro" />
            <span style={{ fontSize: 11, color: '#9CA3AF', fontWeight: 500 }}>{s}px</span>
          </div>
        ))}
      </div>
    </div>
  );
}

// =============================================================
// 2. Profile header context (the original use case)
// =============================================================
function ProfileHeader() {
  return (
    <div style={{ padding: '48px 56px', width: 880, background: '#fff' }}>
      <div style={{ fontSize: 12, color: '#198496', fontWeight: 600, marginBottom: 8 }}>← Retour aux talents</div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 16, marginTop: 24 }}>
        <h1 style={{ fontSize: 40, fontWeight: 700, color: '#0F1419', margin: 0, letterSpacing: '-0.025em' }}>Amakira</h1>
        <WBadge size={26} tier="elite" title="Membre Élite" />
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, color: '#9CA3AF', fontSize: 14 }}>
        ★★★★★ <span style={{ marginLeft: 4 }}>0.0</span> <span>(0 avis)</span>
      </div>
    </div>
  );
}

// =============================================================
// 3. Talent list cards (where most people will see the badge)
// =============================================================
function TalentCards() {
  const talents = [
    { name: 'Amakira', tier: 'elite', city: 'Cotonou', avail: true },
    { name: 'Kemi Adeyemi', tier: 'pro', city: 'Cotonou', avail: true },
    { name: 'Jules Dossou', tier: null, city: 'Porto-Novo', avail: false },
    { name: 'Inès Faty', tier: 'elite', city: 'Cotonou', avail: true },
  ];
  return (
    <div style={{ padding: '40px 48px', width: 880, background: '#F9FAFB' }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: '0.12em', marginBottom: 20 }}>Listing de talents</div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 16 }}>
        {talents.map((t, i) => (
          <div key={i} style={{ background: '#fff', borderRadius: 14, border: '1px solid #E5E7EB', overflow: 'hidden' }}>
            <div style={{ position: 'relative', height: 180, background: 'linear-gradient(135deg, #D1D5DB, #9CA3AF)' }}>
              {t.tier && (
                <div style={{ position: 'absolute', top: 12, right: 12 }}>
                  <WBadge size={22} tier={t.tier} title={t.tier === 'elite' ? 'Élite' : 'Pro'} />
                </div>
              )}
              {t.avail && (
                <div style={{ position: 'absolute', top: 12, left: 12, background: '#16A34A', color: '#fff', fontSize: 10, fontWeight: 600, padding: '3px 8px', borderRadius: 999, display: 'flex', alignItems: 'center', gap: 5 }}>
                  <span style={{ width: 5, height: 5, borderRadius: 999, background: '#fff' }}></span>
                  Disponible
                </div>
              )}
            </div>
            <div style={{ padding: '14px 16px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                <span style={{ fontSize: 15, fontWeight: 600, color: '#111827' }}>{t.name}</span>
                {t.tier && <WBadge size={14} tier={t.tier} title={t.tier === 'elite' ? 'Élite' : 'Pro'} />}
              </div>
              <div style={{ fontSize: 12, color: '#9CA3AF' }}>{t.city}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// =============================================================
// 4. Avatar attached (badge floating on the avatar bottom-right)
// =============================================================
function AvatarOverlay() {
  return (
    <div style={{ padding: '48px', width: 880, background: '#fff' }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: '0.12em', marginBottom: 28 }}>Avatar inline (commentaires, messages)</div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        {[
          { name: 'Amakira', tier: 'elite', text: 'Disponible pour la mission. Quand est la prochaine session de tournage ?' },
          { name: 'Kemi Adeyemi', tier: 'pro', text: "J'ai mes vidéos UGC prêtes pour validation." },
          { name: 'Jules Dossou', tier: null, text: 'Bonjour, je suis intéressé par votre annonce.' },
        ].map((m, i) => (
          <div key={i} style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
            <div style={{ position: 'relative', flexShrink: 0 }}>
              <div style={{ width: 40, height: 40, borderRadius: 999, background: 'linear-gradient(135deg, #D1D5DB, #9CA3AF)' }}></div>
              {m.tier && (
                <div style={{ position: 'absolute', bottom: -2, right: -2 }}>
                  <div style={{ background: '#fff', borderRadius: 999, padding: 1.5 }}>
                    <WBadge size={14} tier={m.tier} title={m.tier === 'elite' ? 'Élite' : 'Pro'} />
                  </div>
                </div>
              )}
            </div>
            <div style={{ flex: 1, paddingTop: 2 }}>
              <div style={{ fontSize: 13, fontWeight: 600, color: '#111827', marginBottom: 4 }}>{m.name}</div>
              <div style={{ fontSize: 13, color: '#374151', lineHeight: 1.55 }}>{m.text}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// =============================================================
// 5. Search results — badge in row (table-like)
// =============================================================
function SearchResults() {
  const rows = [
    { name: 'Amakira', tier: 'elite', specialty: 'Acting + UGC', rate: '50 000 / jour' },
    { name: 'Inès Faty', tier: 'elite', specialty: 'UGC + Modèle', rate: '35 000 / jour' },
    { name: 'Kemi Adeyemi', tier: 'pro', specialty: 'UGC', rate: '25 000 / jour' },
    { name: 'Jules Dossou', tier: null, specialty: 'Acting', rate: '—' },
  ];
  return (
    <div style={{ padding: '40px 48px', width: 880, background: '#fff' }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: '0.12em', marginBottom: 20 }}>Résultats de recherche (ordre Élite → Pro → libre)</div>
      <div style={{ border: '1px solid #E5E7EB', borderRadius: 10, overflow: 'hidden' }}>
        {rows.map((r, i) => (
          <div key={i} style={{
            display: 'grid',
            gridTemplateColumns: '40px 1fr 1fr 120px',
            gap: 16,
            padding: '14px 16px',
            alignItems: 'center',
            borderTop: i > 0 ? '1px solid #F3F4F6' : 'none',
          }}>
            <div style={{ width: 36, height: 36, borderRadius: 999, background: 'linear-gradient(135deg, #D1D5DB, #9CA3AF)' }}></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span style={{ fontSize: 14, fontWeight: 600, color: '#111827' }}>{r.name}</span>
              {r.tier && <WBadge size={14} tier={r.tier} title={r.tier === 'elite' ? 'Élite' : 'Pro'} />}
            </div>
            <div style={{ fontSize: 13, color: '#6B7280' }}>{r.specialty}</div>
            <div style={{ fontSize: 13, color: '#111827', fontWeight: 500, textAlign: 'right' }}>{r.rate}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

window.WBadge = WBadge;
window.SizeScale = SizeScale;
window.ProfileHeader = ProfileHeader;
window.TalentCards = TalentCards;
window.AvatarOverlay = AvatarOverlay;
window.SearchResults = SearchResults;
