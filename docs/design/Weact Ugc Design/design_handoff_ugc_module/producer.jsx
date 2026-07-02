// producer.jsx — Producer side screens (5 screens × 2 variations)

const {
  Icon, WeActLogo, StripePh,
  BtnPrimary, BtnOutline, BtnGhost,
  Field, TextInput, SegmentedToggle, CompensationToggle,
  DashChrome, CommissionBreakdown, Row,
  BookingTimelineV, BookingTimelineH, STEPS,
  PayTile, SectionHead, ReassuranceCard, FaceMini, StatusPill, ChronoBadge,
} = window;

// ═══════════════════════════════════════════════════════════════════
// 1. FORMULAIRE BOOKING UGC DYNAMIQUE
// ═══════════════════════════════════════════════════════════════════

// 1A · SAFE — Single-column sheet, BookingFormSheet style
function BookingFormSafe() {
  const [comp, setComp] = React.useState('product');
  const [productValue, setProductValue] = React.useState(45000);
  const [paid, setPaid] = React.useState(15000);
  const [videos, setVideos] = React.useState(2);
  return (
    <DashChrome page="bookings">
      <div className="h-full overflow-auto">
        <div className="max-w-2xl mx-auto">
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-4">
            <span>Bookings</span>
            <Icon name="chevron-right" size={12} />
            <span>Nouveau</span>
            <Icon name="chevron-right" size={12} />
            <span className="text-gray-900 font-medium">Aïcha B. — UGC</span>
          </div>

          <div className="bg-white border border-gray-200 rounded-xl shadow-sm">
            {/* Sheet header */}
            <div className="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <FaceMini name="Aïcha B." location="Cotonou" rating="4.9" size="lg" />
              </div>
              <button className="w-7 h-7 rounded-md hover:bg-gray-100 flex items-center justify-center text-gray-500">
                <Icon name="x" size={16} />
              </button>
            </div>

            {/* Type contenu segmented */}
            <div className="px-6 pt-5">
              <Field label="Type de contenu" required>
                <div className="flex flex-wrap gap-1.5">
                  {['Shooting photo', 'Casting vidéo', 'Événement', 'UGC'].map((t,i) => (
                    <button key={t}
                      className={`px-3 py-2 text-xs rounded-md border transition-colors ${
                        i === 3 ? 'bg-[#198496] border-[#198496] text-white font-medium'
                                : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300'
                      }`}>
                      {i === 3 && <Icon name="video" size={11} className="inline mr-1" style={{verticalAlign:-1}}/>}
                      {t}
                    </button>
                  ))}
                </div>
              </Field>
            </div>

            {/* UGC dynamic fields */}
            <div className="px-6 py-5 mt-3 space-y-4" style={{background:'rgba(25,132,150,0.025)'}}>
              <div className="flex items-center gap-2 -mt-1">
                <Icon name="video" size={14} stroke={2.2} style={{color:'#198496'}} />
                <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496]">Détails UGC</div>
                <div className="flex-1 h-px bg-[rgba(25,132,150,0.15)]" />
              </div>

              <Field label="Type de compensation" required>
                <CompensationToggle value={comp} onChange={setComp} />
              </Field>

              <Field label="Nom du produit à offrir" required hint="ex: Tenue Shade Fit M">
                <TextInput placeholder="Tenue Shade Fit M / Coffret découverte" value="Tenue Shade Fit M" onChange={()=>{}} />
              </Field>

              <div className="grid grid-cols-2 gap-3">
                <Field label="Valeur marchande" required hint="Sert au calcul de la commission">
                  <TextInput type="number" suffix="FCFA" value={productValue} onChange={v=>setProductValue(+v||0)} />
                </Field>
                <Field label="Nombre de vidéos" required hint={comp === 'product' ? 'Fixé à 2 (Unboxing + Avis)' : 'Modifiable'}>
                  {comp === 'product' ? (
                    <div className="px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-900 flex items-center justify-between">
                      <span className="font-semibold">2 vidéos</span>
                      <span className="text-[10px] font-medium text-gray-500">1 Unboxing + 1 Avis</span>
                    </div>
                  ) : (
                    <TextInput type="number" suffix="vidéos" value={videos} onChange={v=>setVideos(+v||1)} />
                  )}
                </Field>
              </div>

              {comp === 'hybrid' && (
                <Field label="Montant de la rémunération Face" required>
                  <TextInput type="number" suffix="FCFA" value={paid} onChange={v=>setPaid(+v||0)} />
                </Field>
              )}
            </div>

            {/* Commission breakdown */}
            <div className="px-6 py-5 border-t border-gray-100">
              <CommissionBreakdown productValue={productValue} paid={comp === 'hybrid' ? paid : 0} />
            </div>

            {/* Footer actions */}
            <div className="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-b-xl">
              <div className="text-[11px] text-gray-500 flex items-center gap-1.5">
                <Icon name="shield-check" size={12} style={{color:'#198496'}} /> Paiement WeAct sécurisé · Remboursé si refus
              </div>
              <div className="flex gap-2">
                <BtnGhost sm>Annuler</BtnGhost>
                <BtnPrimary sm>Payer la commission · {Math.max(2500, Math.round(productValue*0.1)).toLocaleString('fr-FR')} FCFA</BtnPrimary>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DashChrome>
  );
}

// 1B · BOLD — Two-column with live preview + reassurance rail
function BookingFormBold() {
  const [comp, setComp] = React.useState('hybrid');
  const [productValue, setProductValue] = React.useState(35000);
  const [paid, setPaid] = React.useState(20000);
  const [videos, setVideos] = React.useState(3);
  return (
    <DashChrome page="bookings" density="compact">
      <div className="h-full grid grid-cols-[1fr_380px] gap-5">
        {/* Left — Form */}
        <div className="overflow-auto pr-2">
          <div className="flex items-baseline justify-between mb-5">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-1">Booking UGC direct</div>
              <h1 className="text-xl font-bold text-gray-900">Configurer la dotation pour Aïcha B.</h1>
            </div>
            <div className="text-[11px] text-gray-500">Brouillon enregistré · 14:32</div>
          </div>

          {/* Compensation */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 mb-4">
            <SectionHead kicker="01" title="Compensation" sub="Choisissez ce que vous offrez à la Face." />
            <CompensationToggle value={comp} onChange={setComp} />
            <div className="mt-3 grid grid-cols-2 gap-3 text-[11px] text-gray-500">
              <div className="flex items-start gap-1.5">
                <Icon name="info" size={12} style={{color:'#198496', marginTop:2}}/>
                <span>Produit seul : <strong className="text-gray-900">2 vidéos imposées</strong> (Unboxing + Avis), idéal pour les budgets serrés.</span>
              </div>
              <div className="flex items-start gap-1.5">
                <Icon name="info" size={12} style={{color:'#198496', marginTop:2}}/>
                <span>Produit + Argent : <strong className="text-gray-900">vous fixez le nombre de vidéos</strong>, parfait pour les campagnes denses.</span>
              </div>
            </div>
          </div>

          {/* Product */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 mb-4">
            <SectionHead kicker="02" title="Le produit envoyé" sub="Ce que la Face reçoit physiquement." />
            <div className="grid grid-cols-[1fr_120px] gap-3">
              <div className="space-y-3">
                <Field label="Nom du produit" required>
                  <TextInput placeholder="Sneakers Shade Fit · pointure 39" value="Sneakers Shade Fit · 39" onChange={()=>{}} />
                </Field>
                <Field label="Valeur marchande" required hint="Base du calcul de commission">
                  <TextInput type="number" suffix="FCFA" value={productValue} onChange={v=>setProductValue(+v||0)} />
                </Field>
              </div>
              <div className="self-end">
                <StripePh w={120} h={120} label="Photo produit" />
                <button className="mt-2 w-full text-[10px] font-medium text-[#198496] hover:underline">+ Ajouter</button>
              </div>
            </div>
          </div>

          {/* Livrables */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 mb-4">
            <SectionHead kicker="03" title="Livrables vidéo"
              sub={comp === 'product' ? 'Fixés par WeAct pour ce type de deal.' : 'Définissez les contenus attendus.'} />
            <div className="grid grid-cols-2 gap-3">
              <Field label="Nombre de vidéos" required>
                {comp === 'product' ? (
                  <div className="px-3 py-2 bg-gray-50 border border-dashed border-gray-300 rounded-md text-sm flex items-center justify-between">
                    <span className="font-semibold text-gray-900">2 vidéos</span>
                    <Icon name="lock" size={12} className="text-gray-400" />
                  </div>
                ) : (
                  <TextInput type="number" suffix="vidéos" value={videos} onChange={v=>setVideos(+v||1)} />
                )}
              </Field>
              {comp === 'hybrid' && (
                <Field label="Rémunération de la Face" required>
                  <TextInput type="number" suffix="FCFA" value={paid} onChange={v=>setPaid(+v||0)} />
                </Field>
              )}
            </div>
            <div className="mt-3 grid gap-2">
              <div className="flex items-center gap-2 px-3 py-2 rounded-md bg-[rgba(25,132,150,0.05)] border border-[rgba(25,132,150,0.15)]">
                <div className="w-6 h-6 rounded-md bg-[#198496] text-white text-[10px] font-bold flex items-center justify-center">1</div>
                <span className="text-xs font-medium text-gray-900">Unboxing</span>
                <span className="text-[10px] text-gray-500">Déballage · max 60s · vertical</span>
                <span className="ml-auto text-[10px] font-medium text-[#198496]">7 jours</span>
              </div>
              <div className="flex items-center gap-2 px-3 py-2 rounded-md bg-[rgba(25,132,150,0.05)] border border-[rgba(25,132,150,0.15)]">
                <div className="w-6 h-6 rounded-md bg-[#198496] text-white text-[10px] font-bold flex items-center justify-center">2</div>
                <span className="text-xs font-medium text-gray-900">Avis</span>
                <span className="text-[10px] text-gray-500">Test utilisateur · 30-90s</span>
                <span className="ml-auto text-[10px] font-medium text-[#198496]">14 jours</span>
              </div>
              {comp === 'hybrid' && videos > 2 && Array.from({length: videos-2}).map((_,i) => (
                <div key={i} className="flex items-center gap-2 px-3 py-2 rounded-md bg-white border border-dashed border-gray-200">
                  <div className="w-6 h-6 rounded-md bg-gray-100 text-gray-500 text-[10px] font-bold flex items-center justify-center">{i+3}</div>
                  <input className="flex-1 text-xs bg-transparent placeholder:text-gray-400 outline-none" placeholder={`Brief vidéo ${i+3} · max 60s`} />
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Right — Live preview rail */}
        <div className="overflow-auto space-y-4 pb-2">
          {/* Order preview */}
          <div className="rounded-xl bg-[#0F1419] text-white p-5">
            <div className="text-[10px] font-bold uppercase tracking-widest text-white/50 mb-3">Aperçu envoi</div>
            <div className="flex items-center gap-3 pb-3 border-b border-white/10">
              <div className="w-10 h-10 rounded-full bg-white/10 ph-stripe-dark" />
              <div>
                <div className="text-sm font-semibold">Aïcha B.</div>
                <div className="text-[11px] text-white/60">Cotonou · 4.9 ★ · 12 UGC livrés</div>
              </div>
            </div>
            <div className="pt-3 space-y-2 text-xs">
              <Row label={<span className="text-white/60">Produit</span>} value={<span className="text-white">Sneakers Shade Fit · 39</span>} />
              <Row label={<span className="text-white/60">Valeur déclarée</span>} value={<span className="text-white">{productValue.toLocaleString('fr-FR')} FCFA</span>} />
              {comp === 'hybrid' && <Row label={<span className="text-white/60">Cash Face</span>} value={<span className="text-white">{paid.toLocaleString('fr-FR')} FCFA</span>} />}
              <Row label={<span className="text-white/60">Vidéos</span>} value={<span className="text-white">{comp === 'product' ? 2 : videos}</span>} />
              <div className="pt-2 mt-2 border-t border-white/10 flex items-center justify-between">
                <span className="text-white/60 text-[11px] uppercase tracking-wider">Commission WeAct</span>
                <span className="text-base font-bold text-white">{Math.max(2500, Math.round(productValue*0.1)).toLocaleString('fr-FR')} FCFA</span>
              </div>
            </div>
            <BtnPrimary full>Payer & envoyer la demande</BtnPrimary>
          </div>

          {/* Reassurance */}
          <ReassuranceCard compact />
        </div>
      </div>
    </DashChrome>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 2. FORMULAIRE CRÉATION MISSION UGC (appel à projets)
// ═══════════════════════════════════════════════════════════════════

// 2A · SAFE — Single page form
function MissionCreateSafe() {
  const [comp, setComp] = React.useState('product');
  const [value, setValue] = React.useState(25000);
  return (
    <DashChrome page="missions">
      <div className="h-full overflow-auto">
        <div className="max-w-3xl mx-auto">
          <div className="flex items-baseline justify-between mb-5">
            <h1 className="text-xl font-bold text-gray-900">Nouvelle mission UGC</h1>
            <div className="flex gap-2"><BtnGhost sm>Brouillon</BtnGhost><BtnPrimary sm>Publier · 2 500 FCFA</BtnPrimary></div>
          </div>

          <div className="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
            <div className="p-5">
              <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-3">Brief</div>
              <Field label="Titre de la mission" required>
                <TextInput value="Test produit · Routine skincare matinale" placeholder="" onChange={()=>{}} />
              </Field>
              <div className="mt-3">
                <Field label="Description" hint="Markdown autorisé">
                  <textarea rows={4} value={`Nous cherchons 5 Faces pour tester notre nouveau sérum Lumi-C et publier deux vidéos de moins de 60s.\n\nMise en avant : texture, parfum, sensation après application.`}
                    onChange={()=>{}}
                    className="w-full px-3 py-2 text-sm bg-white border border-gray-200 rounded-md focus:outline-none focus:border-[#198496] focus:ring-2 focus:ring-[#198496]/20"/>
                </Field>
              </div>
            </div>

            <div className="p-5" style={{background:'rgba(25,132,150,0.025)'}}>
              <div className="flex items-center gap-2 mb-3">
                <Icon name="video" size={14} stroke={2.2} style={{color:'#198496'}}/>
                <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496]">Dotation UGC</div>
              </div>
              <Field label="Type de compensation" required>
                <CompensationToggle value={comp} onChange={setComp} />
              </Field>
              <div className="grid grid-cols-2 gap-3 mt-3">
                <Field label="Produit offert" required><TextInput value="Sérum Lumi-C 30ml" onChange={()=>{}}/></Field>
                <Field label="Valeur marchande" required><TextInput type="number" suffix="FCFA" value={value} onChange={v=>setValue(+v||0)} /></Field>
              </div>
              {comp === 'hybrid' && (
                <div className="grid grid-cols-2 gap-3 mt-3">
                  <Field label="Nb de vidéos" required><TextInput type="number" suffix="vidéos" value={3} onChange={()=>{}}/></Field>
                  <Field label="Rémunération Face" required><TextInput type="number" suffix="FCFA" value={10000} onChange={()=>{}}/></Field>
                </div>
              )}
            </div>

            <div className="p-5">
              <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-3">Cible</div>
              <div className="grid grid-cols-3 gap-3">
                <Field label="Nb de Faces"><TextInput type="number" value={5} onChange={()=>{}}/></Field>
                <Field label="Ville"><TextInput value="Cotonou" onChange={()=>{}}/></Field>
                <Field label="Genre">
                  <select className="w-full px-3 py-2 text-sm bg-white border border-gray-200 rounded-md">
                    <option>Femmes 18-35</option><option>Tous</option><option>Hommes 18-35</option>
                  </select>
                </Field>
              </div>
            </div>

            <div className="p-5 bg-gray-50/50 rounded-b-xl">
              <CommissionBreakdown productValue={value} paid={comp === 'hybrid' ? 10000 : 0} />
            </div>
          </div>
        </div>
      </div>
    </DashChrome>
  );
}

// 2B · BOLD — Stepper layout
function MissionCreateBold() {
  const [step, setStep] = React.useState(2);
  const steps = ['Type', 'Brief & livrables', 'Cible', 'Publication'];
  return (
    <DashChrome page="missions" density="compact">
      <div className="h-full grid grid-cols-[240px_1fr_320px] gap-5">
        {/* Vertical stepper */}
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-4">Création mission</div>
          <ol className="space-y-4">
            {steps.map((s, i) => {
              const done = i+1 < step, active = i+1 === step;
              return (
                <li key={s} className="flex gap-3">
                  <div className={`w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold border-2 flex-shrink-0 ${
                    done ? 'bg-[#198496] border-[#198496] text-white' :
                    active ? 'bg-white border-[#198496] text-[#198496]' :
                    'bg-white border-gray-200 text-gray-400'
                  }`}>{done ? <Icon name="check" size={13} stroke={3}/> : i+1}</div>
                  <div>
                    <div className={`text-xs font-semibold ${active ? 'text-gray-900' : done ? 'text-gray-700' : 'text-gray-400'}`}>{s}</div>
                    {active && <div className="text-[10px] text-[#198496] mt-0.5">Étape en cours</div>}
                  </div>
                </li>
              );
            })}
          </ol>
          <div className="mt-6 pt-4 border-t border-gray-100">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Plan</div>
            <div className="text-xs text-gray-700">Pro · missions illimitées</div>
          </div>
        </div>

        {/* Step body */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 overflow-auto">
          <div className="flex items-baseline justify-between mb-6">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-1">Étape 2 sur 4</div>
              <h1 className="text-xl font-bold text-gray-900">Brief & livrables</h1>
              <p className="text-xs text-gray-500 mt-1">Décrivez la mission et le contenu attendu des Faces.</p>
            </div>
          </div>

          <div className="space-y-5">
            <Field label="Titre court" required>
              <TextInput value="Routine skincare matinale · Lumi-C" onChange={()=>{}} />
            </Field>
            <Field label="Pitch" hint="Ce qui apparaît en aperçu pour les Faces">
              <textarea rows={3}
                value={`Tester notre sérum Lumi-C et créer 2 vidéos verticales authentiques (déballage + avis 7 jours après).`}
                onChange={()=>{}}
                className="w-full px-3 py-2 text-sm bg-white border border-gray-200 rounded-md focus:outline-none focus:border-[#198496] focus:ring-2 focus:ring-[#198496]/20"/>
            </Field>

            <div className="grid grid-cols-2 gap-4 p-4 rounded-lg border border-[rgba(25,132,150,0.2)] bg-[rgba(25,132,150,0.03)]">
              <div>
                <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-2">Livrable 1 · Unboxing</div>
                <div className="text-xs text-gray-700 leading-relaxed">Vidéo verticale 9:16 · 30-60s · à publier <strong>sous 7 jours</strong> après réception.</div>
              </div>
              <div>
                <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-2">Livrable 2 · Avis</div>
                <div className="text-xs text-gray-700 leading-relaxed">Test honnête de 7 jours · 30-90s · à publier <strong>sous 14 jours</strong> après réception.</div>
              </div>
            </div>

            <Field label="Hashtags à intégrer (optionnel)">
              <TextInput value="#shadefit #ugcbenin" onChange={()=>{}} />
            </Field>
            <Field label="Critères additionnels">
              <div className="flex flex-wrap gap-1.5">
                {['Maquillage minimal','Lumière naturelle','Voix off française','Sans musique copyright','Sous-titres'].map((c,i) =>
                  <button key={c} className={`px-2.5 py-1 text-[11px] rounded-full border ${i<3
                    ? 'bg-[#198496] border-[#198496] text-white'
                    : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300'}`}>{c}</button>
                )}
              </div>
            </Field>
          </div>

          <div className="mt-7 pt-5 border-t border-gray-100 flex items-center justify-between">
            <BtnGhost sm onClick={() => setStep(Math.max(1,step-1))}><span className="flex items-center gap-1"><Icon name="chevron-left" size={14}/>Retour</span></BtnGhost>
            <BtnPrimary sm onClick={() => setStep(Math.min(4,step+1))}>Continuer · Cible <span className="ml-1">→</span></BtnPrimary>
          </div>
        </div>

        {/* Right rail summary */}
        <div className="space-y-4 overflow-auto pb-2">
          <div className="bg-white rounded-xl border border-gray-200 p-4">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Aperçu de la mission</div>
            <div className="text-sm font-semibold text-gray-900">Routine skincare matinale · Lumi-C</div>
            <div className="text-[11px] text-gray-500 mt-1">Cotonou · Femmes 18-35</div>
            <div className="mt-3 flex gap-1.5 flex-wrap">
              <span className="text-[10px] px-2 py-0.5 rounded-full bg-[rgba(25,132,150,0.10)] text-[#198496] font-medium">UGC</span>
              <span className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-medium">Produit seul</span>
              <span className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-medium">2 vidéos</span>
            </div>
          </div>
          <CommissionBreakdown productValue={25000} paid={0} compact />
          <ReassuranceCard compact />
        </div>
      </div>
    </DashChrome>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 3. TUNNEL PAIEMENT COMMISSION
// ═══════════════════════════════════════════════════════════════════

// 3A · SAFE — Centered modal
function PaymentSafe() {
  const [prov, setProv] = React.useState('mtn');
  const [phase, setPhase] = React.useState('select'); // select | confirm | success
  return (
    <DashChrome page="bookings">
      <div className="h-full flex items-center justify-center">
        <div className="bg-white rounded-xl border border-gray-200 shadow-xl w-full max-w-md">
          {phase === 'success' ? (
            <div className="p-7 text-center">
              <div className="w-14 h-14 rounded-full bg-[rgba(25,132,150,0.10)] mx-auto flex items-center justify-center mb-4">
                <Icon name="check" size={28} stroke={2.5} style={{color:'#198496'}} />
              </div>
              <div className="text-base font-semibold text-gray-900">Commission payée</div>
              <div className="text-xs text-gray-500 mt-1.5 leading-relaxed">La demande est envoyée à Aïcha B. Vous serez prévenu dès qu\u2019elle accepte.</div>
              <div className="mt-4 px-3 py-2 bg-gray-50 rounded-md text-[11px] text-gray-700 font-mono">REF · WACT-BOOK-8821</div>
              <div className="mt-5 flex gap-2"><BtnOutline full>Suivre le booking</BtnOutline><BtnPrimary full>Nouveau booking</BtnPrimary></div>
            </div>
          ) : (
            <>
              <div className="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                  <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496]">Étape 1/6 · Paiement</div>
                  <div className="text-sm font-semibold text-gray-900 mt-0.5">Commission WeAct</div>
                </div>
                <button className="w-7 h-7 rounded-md hover:bg-gray-100 flex items-center justify-center text-gray-500"><Icon name="x" size={16}/></button>
              </div>
              <div className="p-5">
                <div className="flex items-baseline justify-between mb-4">
                  <span className="text-xs text-gray-500">Total à payer</span>
                  <span className="text-2xl font-bold text-gray-900">4 500 <span className="text-sm font-medium text-gray-500">FCFA</span></span>
                </div>
                <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Méthode de paiement</div>
                <div className="space-y-2">
                  <PayTile provider="mtn" selected={prov==='mtn'} onSelect={()=>setProv('mtn')} />
                  <PayTile provider="moov" selected={prov==='moov'} onSelect={()=>setProv('moov')} />
                  <PayTile provider="fedapay" selected={prov==='fedapay'} onSelect={()=>setProv('fedapay')} />
                </div>
                <div className="mt-4 flex items-start gap-2 text-[10px] text-gray-500 leading-relaxed">
                  <Icon name="shield-check" size={12} style={{color:'#198496', marginTop:1}} />
                  Paiement sécurisé par FedaPay. La commission n\u2019est encaissée qu\u2019après acceptation par la Face.
                </div>
              </div>
              <div className="px-5 pb-5">
                <BtnPrimary full onClick={() => setPhase('success')}>
                  {prov === 'fedapay' ? 'Payer par carte · 4 500 FCFA' : 'Recevoir le code USSD · 4 500 FCFA'}
                </BtnPrimary>
              </div>
            </>
          )}
        </div>
      </div>
    </DashChrome>
  );
}

// 3B · BOLD — Full-page split checkout with PIN entry
function PaymentBold() {
  const pin = ['7','3','4','•'];
  return (
    <DashChrome page="bookings" density="compact">
      <div className="h-full grid grid-cols-[1fr_1fr] gap-5">
        {/* Left — Order summary dark */}
        <div className="bg-[#0F1419] text-white rounded-xl p-7 flex flex-col overflow-auto">
          <div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-white/40">
            <span>WeAct</span><span>·</span><span>Tunnel sécurisé</span>
          </div>
          <h1 className="text-2xl font-bold mt-2">Commission booking UGC</h1>
          <div className="text-xs text-white/60 mt-1">Ref WACT-BOOK-8821</div>

          <div className="mt-7 pt-6 border-t border-white/10 space-y-3 text-xs">
            <div className="flex items-center gap-3 pb-3 border-b border-white/10">
              <div className="w-12 h-12 rounded-md ph-stripe-dark border border-white/10" />
              <div>
                <div className="text-sm font-semibold">Sneakers Shade Fit · 39</div>
                <div className="text-[11px] text-white/50">Pour Aïcha B. · Cotonou</div>
              </div>
            </div>
            <Row label={<span className="text-white/60">Valeur produit</span>}     value={<span className="text-white">35 000 FCFA</span>} />
            <Row label={<span className="text-white/60">Cash Face</span>}          value={<span className="text-white">20 000 FCFA</span>} />
            <Row label={<span className="text-white/60">Vidéos attendues</span>}   value={<span className="text-white">3</span>} />
          </div>

          <div className="mt-auto pt-7">
            <div className="rounded-lg bg-white/5 border border-white/10 p-4">
              <div className="flex items-baseline justify-between">
                <span className="text-xs text-white/60">À payer maintenant</span>
                <span className="text-3xl font-bold text-white">3 500 <span className="text-base font-medium text-white/60">FCFA</span></span>
              </div>
              <div className="text-[10px] text-white/40 mt-1">10 % de 35 000 = 3 500 (≥ plancher 2 500)</div>
            </div>
            <div className="mt-3 flex items-center gap-2 text-[11px] text-white/50">
              <Icon name="shield-check" size={12} style={{color:'#198496'}} /> Fonds bloqués jusqu\u2019à acceptation par la Face
            </div>
          </div>
        </div>

        {/* Right — Provider + PIN */}
        <div className="bg-white rounded-xl border border-gray-200 p-7 overflow-auto">
          <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-1">Étape 1 sur 6 · Paiement</div>
          <h2 className="text-lg font-bold text-gray-900">Choisissez votre méthode</h2>

          <div className="mt-4 flex gap-2 p-1 bg-gray-100 rounded-md">
            {[{k:'mtn',l:'MTN MoMo'},{k:'moov',l:'Moov Money'},{k:'fedapay',l:'Carte'}].map((p,i)=>(
              <button key={p.k} className={`flex-1 px-3 py-2 text-xs font-medium rounded ${i===0 ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}>{p.l}</button>
            ))}
          </div>

          <div className="mt-5">
            <Field label="Numéro MTN" required>
              <TextInput value="+229 61 23 45 67" onChange={()=>{}} />
            </Field>
          </div>

          <div className="mt-5 rounded-lg border border-gray-200 bg-gray-50/40 p-4">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Étape 2 · Confirmer sur le téléphone</div>
            <div className="text-xs text-gray-700 leading-relaxed">Vous allez recevoir un USSD <span className="font-mono font-semibold text-gray-900">*880*4500#</span>. Composez votre PIN pour valider.</div>
            <div className="mt-3 flex gap-2 justify-center">
              {pin.map((d,i) => (
                <div key={i} className={`w-11 h-12 rounded-md border ${d==='•' ? 'border-dashed border-gray-300 bg-white' : 'border-[#198496] bg-[rgba(25,132,150,0.05)]'} flex items-center justify-center text-lg font-mono font-bold text-gray-900`}>{d}</div>
              ))}
            </div>
          </div>

          <div className="mt-5 flex items-center gap-2 text-[11px] text-gray-500">
            <div className="w-3 h-3 rounded-full border-2 border-[#198496] border-t-transparent animate-spin" />
            En attente de votre confirmation MTN…
          </div>

          <div className="mt-6 pt-5 border-t border-gray-100 flex gap-2">
            <BtnGhost sm>Annuler</BtnGhost>
            <BtnPrimary sm>J\u2019ai validé sur mon téléphone</BtnPrimary>
          </div>
        </div>
      </div>
    </DashChrome>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 4. CONFIRMATION EXPÉDITION + TRACKING
// ═══════════════════════════════════════════════════════════════════

// 4A · SAFE — Inline form panel
function ShippingSafe() {
  return (
    <DashChrome page="bookings">
      <div className="h-full overflow-auto">
        <div className="max-w-2xl mx-auto">
          <div className="flex items-baseline justify-between mb-5">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-1">Étape 3 sur 6 · Expédition</div>
              <h1 className="text-xl font-bold text-gray-900">Confirmer l\u2019envoi du produit</h1>
            </div>
            <StatusPill kind="accepted">Aïcha a accepté · il y a 2h</StatusPill>
          </div>

          <BookingTimelineH current={3} />

          <div className="mt-7 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div className="p-5 flex items-center gap-3">
              <StripePh w={60} h={60} label="" />
              <div className="flex-1">
                <div className="text-sm font-semibold text-gray-900">Sneakers Shade Fit · 39</div>
                <div className="text-[11px] text-gray-500">Pour Aïcha B. · Akpakpa, Cotonou · 35 000 FCFA</div>
              </div>
            </div>
            <div className="p-5 space-y-4">
              <Field label="Transporteur" required>
                <div className="grid grid-cols-4 gap-2">
                  {['DHL','Chronopost','Gozem','Autre'].map((c,i)=>(
                    <button key={c} className={`px-3 py-2.5 text-xs rounded-md border ${i===2 ? 'bg-[#198496] border-[#198496] text-white font-medium' : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300'}`}>{c}</button>
                  ))}
                </div>
              </Field>
              <Field label="Numéro de suivi" required hint="Visible par la Face">
                <TextInput value="GZM-COT-882194" onChange={()=>{}} suffix={<button className="text-[11px] text-[#198496] font-medium hover:underline pointer-events-auto">Coller</button>} />
              </Field>
              <Field label="Notes pour la Face" hint="Optionnel">
                <textarea rows={2}
                  value="Le colis arrive demain entre 14h-16h. Si absente, le livreur appelle au numéro fourni."
                  onChange={()=>{}}
                  className="w-full px-3 py-2 text-sm bg-white border border-gray-200 rounded-md focus:outline-none focus:border-[#198496] focus:ring-2 focus:ring-[#198496]/20"/>
              </Field>
            </div>
            <div className="p-4 bg-gray-50/50 rounded-b-xl flex items-center justify-between">
              <div className="text-[11px] text-gray-500 flex items-center gap-1.5">
                <Icon name="info" size={12} style={{color:'#198496'}}/> Le chrono démarrera quand Aïcha clique sur « Produit reçu »
              </div>
              <div className="flex gap-2"><BtnGhost sm>Sauvegarder</BtnGhost><BtnPrimary sm>Confirmer l\u2019envoi</BtnPrimary></div>
            </div>
          </div>
        </div>
      </div>
    </DashChrome>
  );
}

// 4B · BOLD — Split with map preview
function ShippingBold() {
  return (
    <DashChrome page="bookings" density="compact">
      <div className="h-full overflow-auto">
        <div className="mb-4">
          <BookingTimelineH current={3} />
        </div>

        <div className="grid grid-cols-[1fr_1fr] gap-5">
          {/* Left — form */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-1">Expédition · Booking WACT-8821</div>
            <h2 className="text-lg font-bold text-gray-900">Vous envoyez à Aïcha</h2>
            <div className="mt-1 text-xs text-gray-500">Saisissez vos infos transporteur. Aïcha recevra une notification temps réel.</div>

            <div className="mt-5 space-y-4">
              <Field label="Transporteur" required>
                <div className="grid grid-cols-2 gap-2">
                  {[
                    {n:'Gozem', sub:'2-4h · Cotonou', sel:true, ic:'bike'},
                    {n:'DHL Express', sub:'24-48h · National', sel:false, ic:'truck'},
                    {n:'Chronopost', sub:'48h · International', sel:false, ic:'plane'},
                    {n:'Autre', sub:'À préciser', sel:false, ic:'package'},
                  ].map(c => (
                    <button key={c.n} className={`flex items-center gap-2.5 p-3 rounded-md border text-left ${c.sel ? 'border-[#198496] bg-[rgba(25,132,150,0.05)] ring-2 ring-[#198496]/20' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                      <Icon name={c.ic} size={16} style={{color: c.sel ? '#198496' : '#6B7280'}}/>
                      <div className="min-w-0">
                        <div className="text-xs font-semibold text-gray-900">{c.n}</div>
                        <div className="text-[10px] text-gray-500">{c.sub}</div>
                      </div>
                    </button>
                  ))}
                </div>
              </Field>
              <Field label="Numéro de suivi" required>
                <TextInput value="GZM-COT-882194" onChange={()=>{}} />
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Date d\u2019envoi"><TextInput value="04 / 02 / 2026" onChange={()=>{}} /></Field>
                <Field label="Heure"><TextInput value="14h32" onChange={()=>{}} /></Field>
              </div>
            </div>

            <div className="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
              <div className="text-[10px] text-gray-500 flex items-center gap-1.5">
                <Icon name="shield-check" size={12} style={{color:'#198496'}}/>
                Tant que la Face n\u2019a pas cliqué « Reçu », aucun chrono ne court.
              </div>
              <BtnPrimary sm>Confirmer l\u2019envoi</BtnPrimary>
            </div>
          </div>

          {/* Right — Map + recipient */}
          <div className="space-y-4">
            <div className="rounded-xl border border-gray-200 overflow-hidden">
              <div className="h-44 ph-stripe relative">
                <div className="absolute inset-0 grid grid-cols-12 grid-rows-6 opacity-40">
                  {Array.from({length:72}).map((_,i)=>(
                    <div key={i} className="border-r border-b border-gray-300/40" />
                  ))}
                </div>
                {/* Pin */}
                <div className="absolute top-[40%] left-[55%]">
                  <div className="relative">
                    <div className="w-3 h-3 rounded-full bg-[#198496] ring-4 ring-[#198496]/30" />
                    <div className="absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap text-[10px] font-medium bg-white px-2 py-1 rounded shadow-sm border border-gray-200">Akpakpa</div>
                  </div>
                </div>
                <div className="absolute top-[60%] left-[25%]">
                  <div className="w-2.5 h-2.5 rounded-full bg-gray-400 ring-2 ring-white" />
                </div>
              </div>
              <div className="p-4">
                <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Adresse de livraison</div>
                <div className="mt-1 text-sm font-medium text-gray-900">Aïcha B. · +229 61 23 45 67</div>
                <div className="text-xs text-gray-600">Quartier Akpakpa, lot 412 · Cotonou</div>
                <div className="mt-2 text-[10px] text-gray-500">Adresse vérifiée par WeAct · KYC validé</div>
              </div>
            </div>

            <div className="rounded-xl bg-[#0F1419] text-white p-5">
              <div className="text-[10px] font-bold uppercase tracking-widest text-white/40 mb-2">Ce qui se passe ensuite</div>
              <ol className="space-y-2 text-xs">
                {[
                  ['Notification temps réel envoyée à Aïcha avec le tracking', true],
                  ['Aïcha confirme la réception → chrono Unboxing (7j) démarre', false],
                  ['Vous validez la vidéo 1 → chrono Avis (14j) démarre', false],
                ].map(([t,done],i) => (
                  <li key={i} className="flex gap-2.5">
                    <div className={`w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold flex-shrink-0 ${done ? 'bg-[#198496] text-white' : 'bg-white/10 text-white/60'}`}>
                      {done ? <Icon name="check" size={11} stroke={3}/> : i+1}
                    </div>
                    <span className={done ? 'text-white' : 'text-white/60'}>{t}</span>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        </div>
      </div>
    </DashChrome>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 5. VALIDATION LIVRABLES PRODUCTEUR
// ═══════════════════════════════════════════════════════════════════

// 5A · SAFE — Two-pane list + light video preview
function ValidationSafe() {
  return (
    <DashChrome page="ugc">
      <div className="h-full grid grid-cols-[320px_1fr] gap-5">
        {/* List */}
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden flex flex-col">
          <div className="p-4 border-b border-gray-100">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">À valider</div>
            <div className="flex items-baseline justify-between">
              <h2 className="text-sm font-semibold text-gray-900">3 livrables en attente</h2>
              <span className="text-[11px] text-gray-500">SLA: 48h</span>
            </div>
          </div>
          <div className="flex-1 overflow-auto divide-y divide-gray-100">
            {[
              { n:'Aïcha B.', p:'Sneakers Shade Fit', v:'Unboxing', t:'12h restantes', prog:0.5, sel:true },
              { n:'Bénédicte K.', p:'Sérum Lumi-C', v:'Avis', t:'2j 4h restantes', prog:0.3, sel:false },
              { n:'Mariam D.', p:'Casque Audio Pro', v:'Unboxing', t:'34h restantes', prog:0.7, sel:false },
            ].map((b,i) => (
              <div key={i} className={`p-3 cursor-pointer ${b.sel ? 'bg-[rgba(25,132,150,0.04)] border-l-2 border-[#198496]' : 'hover:bg-gray-50 border-l-2 border-transparent'}`}>
                <div className="flex items-center gap-2.5">
                  <StripePh w={48} h={48} label="" />
                  <div className="flex-1 min-w-0">
                    <div className="text-xs font-semibold text-gray-900 truncate">{b.n}</div>
                    <div className="text-[10px] text-gray-500 truncate">{b.p}</div>
                    <div className="mt-1 flex items-center gap-1.5">
                      <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">{b.v}</span>
                      <ChronoBadge progress={b.prog} label={b.t} />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Preview */}
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden flex flex-col">
          <div className="p-4 border-b border-gray-100 flex items-center justify-between">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496]">Livrable 1 · Unboxing</div>
              <div className="text-sm font-semibold text-gray-900 mt-0.5">Aïcha B. · Sneakers Shade Fit</div>
            </div>
            <ChronoBadge progress={0.5} label="12h pour valider" size="lg" />
          </div>
          <div className="flex-1 grid grid-cols-[1fr_280px]">
            {/* Video preview area */}
            <div className="bg-black flex items-center justify-center relative">
              <div style={{width:220, height:390}} className="ph-stripe-dark rounded-md relative flex items-center justify-center">
                <div className="w-14 h-14 rounded-full bg-white/15 backdrop-blur flex items-center justify-center">
                  <Icon name="play" size={22} style={{color:'#fff'}} stroke={2.4}/>
                </div>
                <div className="absolute bottom-3 left-3 right-3 flex items-center gap-2">
                  <div className="flex-1 h-1 bg-white/20 rounded-full overflow-hidden"><div className="h-full bg-white" style={{width:'34%'}}/></div>
                  <span className="text-[10px] font-mono text-white/80">0:18 / 0:54</span>
                </div>
              </div>
            </div>
            {/* Side panel */}
            <div className="border-l border-gray-100 p-5 overflow-auto">
              <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Brief original</div>
              <div className="text-xs text-gray-700 leading-relaxed">Déballage 30-60s · Vertical · Mise en avant texture & ambiance.</div>

              <div className="mt-5 text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Checklist</div>
              <ul className="space-y-2">
                {[
                  ['Format vertical 9:16', true],
                  ['Durée ≤ 60s', true],
                  ['Hashtags présents', true],
                  ['Mention WeAct visible', false],
                ].map(([t,ok],i) => (
                  <li key={i} className="flex items-center gap-2 text-xs">
                    <div className={`w-4 h-4 rounded ${ok ? 'bg-[#198496]' : 'bg-gray-200'} flex items-center justify-center`}>
                      <Icon name={ok?'check':'x'} size={10} stroke={3} style={{color: ok?'#fff':'#9CA3AF'}}/>
                    </div>
                    <span className={ok ? 'text-gray-700' : 'text-gray-400 line-through'}>{t}</span>
                  </li>
                ))}
              </ul>

              <div className="mt-5">
                <Field label="Note / demande de retouche">
                  <textarea rows={3} placeholder="Optionnel · si rejet"
                    className="w-full px-3 py-2 text-xs bg-white border border-gray-200 rounded-md focus:outline-none focus:border-[#198496] focus:ring-2 focus:ring-[#198496]/20"/>
                </Field>
              </div>

              <div className="mt-5 grid grid-cols-2 gap-2">
                <BtnOutline full sm>Demander retouche</BtnOutline>
                <BtnPrimary full sm>Valider · Livrable 2 →</BtnPrimary>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DashChrome>
  );
}

// 5B · BOLD — Immersive dark viewer with bottom toolbar
function ValidationBold() {
  return (
    <div className="h-full bg-[#0A0E12] text-white flex flex-col">
      {/* Top bar */}
      <div className="h-14 px-6 flex items-center justify-between border-b border-white/5">
        <div className="flex items-center gap-3">
          <button className="w-7 h-7 rounded-md hover:bg-white/10 flex items-center justify-center"><Icon name="x" size={16}/></button>
          <div>
            <div className="text-xs font-semibold">Validation · Livrable 1 / 2</div>
            <div className="text-[10px] text-white/50">Aïcha B. · Sneakers Shade Fit · WACT-8821</div>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <ChronoBadge progress={0.5} label="12h restantes pour valider" size="lg" />
          <div className="w-px h-6 bg-white/10" />
          <button className="text-xs text-white/70 hover:text-white flex items-center gap-1.5">
            <Icon name="message-circle" size={14}/> Discuter
          </button>
        </div>
      </div>

      {/* Stage */}
      <div className="flex-1 flex items-center justify-center px-10 gap-10">
        {/* Phone-style video */}
        <div className="relative">
          <div style={{width:300, height:540}} className="ph-stripe-dark rounded-2xl relative flex items-center justify-center border border-white/10">
            <div className="w-16 h-16 rounded-full bg-white/15 backdrop-blur flex items-center justify-center">
              <Icon name="play" size={26} style={{color:'#fff'}} stroke={2.4}/>
            </div>
            <div className="absolute top-4 left-4 right-4 flex items-center gap-2">
              <span className="text-[9px] font-mono uppercase tracking-widest text-white/60">UNBOX_001.mp4</span>
              <span className="ml-auto text-[9px] font-mono text-white/60">9:16 · 54s</span>
            </div>
            <div className="absolute bottom-4 left-4 right-4 flex items-center gap-2">
              <div className="flex-1 h-1 bg-white/20 rounded-full overflow-hidden"><div className="h-full bg-[#198496]" style={{width:'34%'}}/></div>
              <span className="text-[10px] font-mono text-white/80">0:18</span>
            </div>
          </div>
        </div>

        {/* Side */}
        <div className="w-[340px] space-y-4">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-2">Brief</div>
            <div className="text-sm leading-relaxed text-white/80">Déballage des sneakers avec mise en avant de la texture du cuir et l\u2019ambiance lifestyle. 9:16 · 30-60s · ton spontané.</div>
          </div>
          <div>
            <div className="text-[10px] font-bold uppercase tracking-widest text-white/40 mb-2">Conformité auto</div>
            <ul className="space-y-1.5">
              {[
                ['Vertical 9:16', true],
                ['Durée 54s ≤ 60s', true],
                ['Hashtags #shadefit', true],
                ['Volume audio normalisé', true],
              ].map(([t,ok],i)=>(
                <li key={i} className="flex items-center gap-2 text-xs">
                  <Icon name="check" size={12} stroke={3} style={{color:'#198496'}}/>
                  <span className="text-white/80">{t}</span>
                </li>
              ))}
            </ul>
          </div>
          <div className="rounded-lg bg-white/5 border border-white/10 p-3">
            <div className="text-[10px] font-bold uppercase tracking-widest text-white/40 mb-2">Note Face</div>
            <div className="text-xs text-white/70 italic">« J\u2019ai filmé tôt le matin pour avoir la lumière naturelle. J\u2019espère que l\u2019ambiance plaît ! »</div>
          </div>
        </div>
      </div>

      {/* Bottom action bar */}
      <div className="border-t border-white/5 px-6 py-4 flex items-center justify-between bg-[#0F1419]">
        <div className="text-[11px] text-white/40 flex items-center gap-2">
          <Icon name="info" size={12}/> Une validation déclenche le chrono Avis (14j)
        </div>
        <div className="flex gap-2">
          <button className="px-5 py-2.5 text-sm font-medium rounded-md text-white/80 border border-white/15 hover:bg-white/5">
            Demander retouche
          </button>
          <button className="px-5 py-2.5 text-sm font-medium rounded-md text-white border border-red-500/30 bg-red-500/10 hover:bg-red-500/20">
            Rejeter
          </button>
          <button className="px-6 py-2.5 text-sm font-semibold rounded-md text-white" style={{background:'#198496'}}>
            Valider · Livrable 2 →
          </button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, {
  BookingFormSafe, BookingFormBold,
  MissionCreateSafe, MissionCreateBold,
  PaymentSafe, PaymentBold,
  ShippingSafe, ShippingBold,
  ValidationSafe, ValidationBold,
});
