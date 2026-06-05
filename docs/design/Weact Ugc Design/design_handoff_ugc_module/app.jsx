// app.jsx — Canvas assembly + tweaks
// Mounts the DesignCanvas with all 11 screens × 2 variations as artboards.

const {
  DesignCanvas, DCSection, DCArtboard,
  TweaksPanel, useTweaks, TweakSection, TweakRadio, TweakColor, TweakToggle, TweakSelect,
  BookingFormSafe, BookingFormBold,
  MissionCreateSafe, MissionCreateBold,
  PaymentSafe, PaymentBold,
  ShippingSafe, ShippingBold,
  ValidationSafe, ValidationBold,
  FaceDiscoverSafe, FaceDiscoverBold,
  FaceMissionDetailSafe, FaceMissionDetailBold,
  FaceTrackingSafe, FaceTrackingBold,
  FaceNotifSafe, FaceNotifBold,
  FaceSuspendedSafe, FaceSuspendedBold,
  WorkflowSafe, WorkflowBold,
} = window;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "primaryColor": "#198496",
  "showAnnotations": true,
  "compactDash": false
}/*EDITMODE-END*/;

// Producer dashboards display at desktop res
const DASH_W = 1280, DASH_H = 800;
// Phone artboards
const PH_W = 376, PH_H = 776; // PhoneFrame outer = inner + 16px

// Workflow diagram artboard
const WF_W = 1100, WF_H = 760;

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);

  // Apply primary color override globally via CSS var (used by inline styles
  // where we hard-coded #198496 — the design system specifies teal so most
  // values stay constants, but we honour the tweak by patching computed style).
  React.useEffect(() => {
    document.documentElement.style.setProperty('--teal', t.primaryColor);
  }, [t.primaryColor]);

  return (
    <>
      <DesignCanvas>
        <Cover />

        <DCSection id="workflow" title="Workflow système — 6 étapes"
          subtitle="Vue d'ensemble du tunnel UGC : qui fait quoi, à quel moment, et quand les chronos s'activent.">
          <DCArtboard id="wf-a" label="A · Horizontal swim · Light" width={WF_W} height={WF_H}>
            <WorkflowSafe />
          </DCArtboard>
          <DCArtboard id="wf-b" label="B · Lanes verticales · Dark" width={WF_W} height={WF_H}>
            <WorkflowBold />
          </DCArtboard>
        </DCSection>

        <DCSection id="producer" title="Producteur"
          subtitle="Bookings directs, missions, paiement et validation. Desktop ≥ 1280px.">

          <DCArtboard id="p1-a" label="1A · Booking UGC · Sheet single-col" width={DASH_W} height={DASH_H}>
            <BookingFormSafe />
          </DCArtboard>
          <DCArtboard id="p1-b" label="1B · Booking UGC · Split avec aperçu live" width={DASH_W} height={DASH_H}>
            <BookingFormBold />
          </DCArtboard>

          <DCArtboard id="p2-a" label="2A · Création mission · Single-page" width={DASH_W} height={DASH_H}>
            <MissionCreateSafe />
          </DCArtboard>
          <DCArtboard id="p2-b" label="2B · Création mission · Stepper + rail récap" width={DASH_W} height={DASH_H}>
            <MissionCreateBold />
          </DCArtboard>

          <DCArtboard id="p3-a" label="3A · Paiement commission · Modal centré" width={DASH_W} height={DASH_H}>
            <PaymentSafe />
          </DCArtboard>
          <DCArtboard id="p3-b" label="3B · Paiement · Checkout split (PIN MTN)" width={DASH_W} height={DASH_H}>
            <PaymentBold />
          </DCArtboard>

          <DCArtboard id="p4-a" label="4A · Expédition · Form panel inline" width={DASH_W} height={DASH_H}>
            <ShippingSafe />
          </DCArtboard>
          <DCArtboard id="p4-b" label="4B · Expédition · Split + carte d'adresse" width={DASH_W} height={DASH_H}>
            <ShippingBold />
          </DCArtboard>

          <DCArtboard id="p5-a" label="5A · Validation livrables · Liste + preview" width={DASH_W} height={DASH_H}>
            <ValidationSafe />
          </DCArtboard>
          <DCArtboard id="p5-b" label="5B · Validation · Viewer immersif dark" width={DASH_W} height={DASH_H}>
            <ValidationBold />
          </DCArtboard>
        </DCSection>

        <DCSection id="face" title="Face / Talent"
          subtitle="Tous les écrans Face sont conçus mobile-first. Les chronos sont omniprésents (badge en A, ring/hero countdown en B).">

          <DCArtboard id="f6-a" label="6A · Découverte · Liste + paywall subtil" width={PH_W} height={PH_H}>
            <FaceDiscoverSafe />
          </DCArtboard>
          <DCArtboard id="f6-b" label="6B · Découverte · Paywall hero" width={PH_W} height={PH_H}>
            <FaceDiscoverBold />
          </DCArtboard>

          <DCArtboard id="f7-a" label="7A · Détail mission · Scroll classique" width={PH_W} height={PH_H}>
            <FaceMissionDetailSafe />
          </DCArtboard>
          <DCArtboard id="f7-b" label="7B · Détail mission · Hero + bottom sheet" width={PH_W} height={PH_H}>
            <FaceMissionDetailBold />
          </DCArtboard>

          <DCArtboard id="f8-a" label="8A · Suivi · Timeline + chrono badge" width={PH_W} height={PH_H}>
            <FaceTrackingSafe />
          </DCArtboard>
          <DCArtboard id="f8-b" label="8B · Suivi · Hero countdown ring" width={PH_W} height={PH_H}>
            <FaceTrackingBold />
          </DCArtboard>

          <DCArtboard id="f9-a" label="9A · Notifications · Inbox" width={PH_W} height={PH_H}>
            <FaceNotifSafe />
          </DCArtboard>
          <DCArtboard id="f9-b" label="9B · Notifications · Hero critique" width={PH_W} height={PH_H}>
            <FaceNotifBold />
          </DCArtboard>

          <DCArtboard id="f10-a" label="10A · Suspension · Bandeau + plan d'action" width={PH_W} height={PH_H}>
            <FaceSuspendedSafe />
          </DCArtboard>
          <DCArtboard id="f10-b" label="10B · Suspension · Takeover dramatique" width={PH_W} height={PH_H}>
            <FaceSuspendedBold />
          </DCArtboard>
        </DCSection>
      </DesignCanvas>

      <TweaksPanel>
        <TweakSection label="Direction" />
        <TweakColor label="Brand teal" value={t.primaryColor}
          options={['#198496','#0F766E','#1D4ED8','#0F1419']}
          onChange={v => setTweak('primaryColor', v)} />
        <TweakToggle label="Annotations canvas" value={t.showAnnotations}
          onChange={v => setTweak('showAnnotations', v)} />
        <TweakToggle label="Dashboard compact" value={t.compactDash}
          onChange={v => setTweak('compactDash', v)} />
      </TweaksPanel>
    </>
  );
}

// Cover post-it / intro
function Cover() {
  return (
    <div style={{
      padding: '24px 28px',
      margin: '0 12px 8px',
      background: '#fffbe6',
      border: '1px solid rgba(0,0,0,0.05)',
      borderRadius: 10,
      fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif',
      maxWidth: 760,
      color: '#3a2f1a',
    }}>
      <div style={{fontSize:10, fontWeight:700, letterSpacing:'.15em', textTransform:'uppercase', color:'#198496'}}>WeAct · Module UGC · v0.1</div>
      <div style={{fontSize:20, fontWeight:700, marginTop:6, color:'#0F1419'}}>11 écrans clés × 2 variations</div>
      <div style={{fontSize:13, lineHeight:1.55, marginTop:8, color:'rgba(40,30,20,0.85)'}}>
        Chaque écran est exploré sur 2 axes : <strong>layout</strong> (sheet / stepper / single-page / split) et
        <strong> visualisation du chrono</strong> (badge → ring → hero countdown). Les variations « A » restent
        proches de l'existant <code style={{fontFamily:'JetBrains Mono, monospace', background:'rgba(0,0,0,0.06)', padding:'1px 5px', borderRadius:3, fontSize:11}}>BookingFormSheet</code> /
        <code style={{fontFamily:'JetBrains Mono, monospace', background:'rgba(0,0,0,0.06)', padding:'1px 5px', borderRadius:3, fontSize:11}}>MissionForm</code> ;
        les « B » poussent plus loin (split, dark viewer, hero countdown, takeover).
      </div>
      <ul style={{fontSize:12, lineHeight:1.6, marginTop:10, paddingLeft:16, color:'rgba(40,30,20,0.75)'}}>
        <li><strong>Toggle compensation</strong> dynamique : Produit seul (2 vidéos) ⇄ Produit + Argent (libre)</li>
        <li><strong>Commission</strong> visible en temps réel : 10 % ou plancher 2 500 FCFA</li>
        <li><strong>Anti-arnaque</strong> : pédagogie pré-paiement + stepper persistant + reassurance card</li>
        <li><strong>Chronos</strong> teal → orange → rouge selon proximité de la deadline</li>
      </ul>
      <div style={{fontSize:11, marginTop:10, color:'rgba(40,30,20,0.55)'}}>
        ↓ Commence par le <strong>Workflow système</strong>, puis Producteur, puis Face.
      </div>
    </div>
  );
}

// Wait for lucide UMD to be ready, then mount
function mount() {
  if (!window.lucide || !window.lucide.icons) {
    return setTimeout(mount, 30);
  }
  ReactDOM.createRoot(document.getElementById('root')).render(<App />);
}
mount();
