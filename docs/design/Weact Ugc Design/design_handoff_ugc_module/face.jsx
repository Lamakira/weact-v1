// face.jsx — Face side screens (5 screens × 2 variations)

const {
  Icon, WeActLogo, StripePh, PhoneFrame, MobileTopBar, MobileTabBar,
  ChronoRing, ChronoBadge, StatusPill,
  BtnPrimary, BtnOutline, BtnGhost,
  Field, TextInput,
  BookingTimelineV, STEPS, SectionHead, FaceMini, LockedOverlay,
} = window;

// Small inline placeholder card for product preview inside phone
function MissionCardPh({ brand, title, value, payment, tags, locked, pinned }) {
  return (
    <div className="rounded-lg border border-gray-200 bg-white overflow-hidden">
      <div className="h-24 ph-stripe relative flex items-end p-2">
        {pinned && <span className="absolute top-2 left-2 text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-white text-[#198496] shadow-sm">Recommandé</span>}
        {locked && (
          <div className="absolute inset-0 bg-black/30 flex items-center justify-center backdrop-blur-[2px]">
            <Icon name="lock" size={20} style={{color:'#fff'}} stroke={2.4}/>
          </div>
        )}
        <div className="flex items-center gap-1.5">
          <span className="text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-[#198496] text-white">UGC</span>
          {tags && tags.map((t,i) => <span key={i} className="text-[9px] font-medium px-1.5 py-0.5 rounded bg-white/95 text-gray-700">{t}</span>)}
        </div>
      </div>
      <div className="p-3">
        <div className="text-[10px] font-medium text-gray-500 uppercase tracking-wider">{brand}</div>
        <div className="text-xs font-semibold text-gray-900 leading-snug mt-0.5">{title}</div>
        <div className="mt-2 flex items-center justify-between">
          <div className="text-[11px] text-gray-700">
            <span className="font-semibold text-[#198496]">{value}</span> <span className="text-gray-500">de produit</span>
          </div>
          {payment && <div className="text-[11px] font-semibold text-gray-900">+ {payment}</div>}
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 6. DÉCOUVERTE MISSIONS UGC + PAYWALL
// ═══════════════════════════════════════════════════════════════════

// 6A · SAFE — List visible, items locked individually (subtle paywall)
function FaceDiscoverSafe() {
  return (
    <PhoneFrame>
      <MobileTopBar back={false} title="Missions UGC"
        right={<button className="w-8 h-8 -mr-2 flex items-center justify-center text-gray-700"><Icon name="filter" size={18}/></button>}/>

      {/* Subscription banner (subtle, sticky) */}
      <div className="mx-4 mt-3 rounded-lg border border-[rgba(25,132,150,0.25)] bg-[rgba(25,132,150,0.04)] p-3 flex items-center gap-3">
        <div className="w-9 h-9 rounded-md bg-[#198496] flex items-center justify-center flex-shrink-0">
          <Icon name="lock" size={15} stroke={2.4} style={{color:'#fff'}}/>
        </div>
        <div className="flex-1 min-w-0">
          <div className="text-[11px] font-semibold text-gray-900 leading-tight">Accès UGC réservé aux abonnés</div>
          <div className="text-[10px] text-gray-500 mt-0.5">Passe Starter à <span className="font-semibold text-[#198496]">2 500 FCFA/mois</span> pour postuler.</div>
        </div>
        <BtnPrimary sm>Activer</BtnPrimary>
      </div>

      {/* Filters chips */}
      <div className="px-4 mt-3 flex gap-1.5 overflow-x-auto pb-1">
        {['Tous','Cotonou','Mode','Beauté','Tech','Food'].map((c,i) => (
          <button key={c} className={`whitespace-nowrap px-2.5 py-1 text-[11px] rounded-full border ${i===0 ? 'bg-[#0F1419] border-[#0F1419] text-white font-medium' : 'bg-white border-gray-200 text-gray-700'}`}>{c}</button>
        ))}
      </div>

      {/* Mission cards */}
      <div className="px-4 pt-3 pb-20 space-y-3 overflow-auto" style={{height: 'calc(100% - 156px)'}}>
        <MissionCardPh brand="Shade Fit" title="Test sneakers running · 2 vidéos" value="35 000 FCFA" tags={['Cotonou','Mode']} pinned />
        <MissionCardPh brand="Lumi-C" title="Routine skincare matinale · 2 vidéos" value="25 000 FCFA" tags={['Beauté']} locked />
        <MissionCardPh brand="Sonix" title="Casque audio · unbox + review" value="45 000 FCFA" payment="10 000 FCFA" tags={['Tech']} locked />
        <MissionCardPh brand="MamaFood" title="Tester sauce piment · 3 vidéos" value="8 000 FCFA" payment="5 000 FCFA" tags={['Food']} locked />
      </div>

      <MobileTabBar active="missions" />
    </PhoneFrame>
  );
}

// 6B · BOLD — Hero teaser + full paywall empty state
function FaceDiscoverBold() {
  return (
    <PhoneFrame>
      <MobileTopBar back={false} title="Missions UGC"/>

      <div className="overflow-auto" style={{height:'calc(100% - 124px)'}}>
        {/* Hero teaser */}
        <div className="relative mx-4 mt-4 rounded-2xl overflow-hidden">
          <div className="h-40 ph-stripe relative">
            <div className="absolute inset-0 bg-gradient-to-t from-[#0F1419] via-[#0F1419]/40 to-transparent" />
            <div className="absolute inset-0 p-4 flex flex-col justify-end text-white">
              <div className="text-[9px] font-bold uppercase tracking-widest text-white/70">12 nouvelles missions cette semaine</div>
              <div className="text-base font-bold leading-tight mt-1">Reçois des produits gratuits et gagne en visibilité</div>
            </div>
          </div>
        </div>

        {/* Paywall card */}
        <div className="px-4 mt-4">
          <div className="rounded-xl border-2 border-[#198496] bg-white p-5 text-center">
            <div className="inline-flex items-center gap-1.5 text-[10px] font-bold text-[#198496] uppercase tracking-widest mb-3">
              <Icon name="lock" size={12} stroke={2.4}/> Accès réservé · Starter+
            </div>
            <div className="text-sm font-semibold text-gray-900 leading-snug">
              Active ton abonnement pour postuler aux missions UGC
            </div>
            <div className="text-[11px] text-gray-500 mt-1.5 leading-relaxed">
              Pour garder une plateforme de qualité et éviter les profils fantômes, l\u2019accès est filtré.
            </div>
            <div className="mt-4 grid grid-cols-2 gap-2">
              <div className="rounded-md border border-gray-200 p-2.5 text-left">
                <div className="text-[9px] font-bold uppercase tracking-widest text-gray-400">Starter</div>
                <div className="text-sm font-bold text-gray-900 mt-0.5">2 500</div>
                <div className="text-[10px] text-gray-500">FCFA / mois</div>
              </div>
              <div className="rounded-md border-2 border-[#198496] p-2.5 text-left bg-[rgba(25,132,150,0.04)]">
                <div className="text-[9px] font-bold uppercase tracking-widest text-[#198496]">Pro · Populaire</div>
                <div className="text-sm font-bold text-gray-900 mt-0.5">5 000</div>
                <div className="text-[10px] text-gray-500">FCFA / mois</div>
              </div>
            </div>
            <div className="mt-4">
              <BtnPrimary full>Voir les abonnements</BtnPrimary>
            </div>
            <button className="mt-2 text-[11px] text-gray-500 hover:text-[#198496]">Continuer en aperçu</button>
          </div>
        </div>

        {/* Blurred preview list */}
        <div className="px-4 pt-5 pb-6">
          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Aperçu des missions</div>
          <LockedOverlay label="Débloque pour postuler">
            <div className="space-y-3">
              <MissionCardPh brand="Shade Fit" title="Sneakers running · 2 vidéos" value="35 000 FCFA" tags={['Cotonou']}/>
              <MissionCardPh brand="Lumi-C" title="Routine skincare matinale" value="25 000 FCFA" tags={['Beauté']}/>
            </div>
          </LockedOverlay>
        </div>
      </div>

      <MobileTabBar active="missions" />
    </PhoneFrame>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 7. DÉTAIL MISSION UGC + ACCEPTATION
// ═══════════════════════════════════════════════════════════════════

// 7A · SAFE — Scrollable detail
function FaceMissionDetailSafe() {
  return (
    <PhoneFrame>
      <MobileTopBar title="Mission UGC"
        right={<button className="w-8 h-8 -mr-2 flex items-center justify-center text-gray-700"><Icon name="bookmark" size={18}/></button>}/>

      <div className="overflow-auto pb-24" style={{height:'calc(100% - 112px)'}}>
        <div className="h-44 ph-stripe relative">
          <div className="absolute bottom-3 left-4 flex gap-1.5">
            <span className="text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-[#198496] text-white">UGC</span>
            <span className="text-[9px] font-medium px-1.5 py-0.5 rounded bg-white/95 text-gray-700">Produit + Argent</span>
          </div>
        </div>

        <div className="px-4 pt-4 pb-2">
          <div className="text-[10px] font-medium text-gray-500 uppercase tracking-wider">Shade Fit</div>
          <h1 className="text-base font-bold text-gray-900 leading-snug mt-0.5">Sneakers running · Unbox + review</h1>

          <div className="mt-3 grid grid-cols-3 gap-2">
            <div className="rounded-md bg-[rgba(25,132,150,0.05)] border border-[rgba(25,132,150,0.15)] p-2 text-center">
              <div className="text-[9px] font-bold uppercase tracking-widest text-gray-500">Produit</div>
              <div className="text-xs font-bold text-gray-900 mt-1">35 000<span className="text-[9px] font-medium text-gray-500"> FCFA</span></div>
            </div>
            <div className="rounded-md bg-[rgba(25,132,150,0.05)] border border-[rgba(25,132,150,0.15)] p-2 text-center">
              <div className="text-[9px] font-bold uppercase tracking-widest text-gray-500">Cash</div>
              <div className="text-xs font-bold text-gray-900 mt-1">20 000<span className="text-[9px] font-medium text-gray-500"> FCFA</span></div>
            </div>
            <div className="rounded-md bg-[#198496] p-2 text-center">
              <div className="text-[9px] font-bold uppercase tracking-widest text-white/70">Vidéos</div>
              <div className="text-xs font-bold text-white mt-1">3</div>
            </div>
          </div>

          <div className="mt-4">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1.5">Brief</div>
            <p className="text-xs text-gray-700 leading-relaxed">Test les sneakers Shade Fit lors d\u2019une session running. On veut une vidéo déballage authentique + 2 vidéos retours après usage.</p>
          </div>

          <div className="mt-4">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Livrables</div>
            <div className="space-y-2">
              {[
                ['1','Unboxing','Vidéo 30-60s · 7 jours après réception'],
                ['2','Test 5km','Sortie running · 30-60s · J+10'],
                ['3','Avis','Verdict synthèse · J+14'],
              ].map(([n,t,d]) => (
                <div key={n} className="flex gap-2.5 p-2.5 rounded-md border border-gray-100 bg-gray-50/40">
                  <div className="w-7 h-7 rounded-md bg-[#198496] text-white flex items-center justify-center text-xs font-bold flex-shrink-0">{n}</div>
                  <div className="min-w-0">
                    <div className="text-xs font-semibold text-gray-900">{t}</div>
                    <div className="text-[10px] text-gray-500">{d}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="mt-4">
            <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Producteur</div>
            <div className="flex items-center gap-2.5 p-2.5 rounded-md border border-gray-100">
              <div className="w-9 h-9 rounded-md bg-[#0F1419] flex items-center justify-center text-white text-[10px] font-bold">SF</div>
              <div>
                <div className="text-xs font-semibold text-gray-900">Shade Fit · Marque vérifiée</div>
                <div className="text-[10px] text-gray-500 flex items-center gap-1"><Icon name="shield-check" size={10} style={{color:'#198496'}}/> 24 bookings UGC réussis</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Sticky CTA */}
      <div className="absolute inset-x-0 bottom-16 px-4 py-3 bg-white border-t border-gray-100">
        <div className="flex gap-2">
          <button className="px-3 py-2.5 text-xs rounded-md border border-gray-200 text-gray-700 font-medium">
            <Icon name="message-circle" size={15} />
          </button>
          <button className="flex-1 py-2.5 text-sm font-semibold rounded-md text-white" style={{background:'#198496'}}>
            Accepter cette mission
          </button>
        </div>
      </div>

      <MobileTabBar active="missions" />
    </PhoneFrame>
  );
}

// 7B · BOLD — Full-bleed photo + acceptance modal preview
function FaceMissionDetailBold() {
  return (
    <PhoneFrame>
      <div className="relative h-full bg-[#0F1419]">
        {/* Backdrop */}
        <div className="absolute inset-0 ph-stripe-dark" />
        <div className="absolute inset-0 bg-gradient-to-b from-transparent via-[#0F1419]/40 to-[#0F1419]" />

        {/* Top nav floating */}
        <div className="absolute top-9 inset-x-0 px-4 flex justify-between z-10">
          <button className="w-9 h-9 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white"><Icon name="chevron-left" size={18}/></button>
          <button className="w-9 h-9 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white"><Icon name="share-2" size={16}/></button>
        </div>

        {/* Hero meta */}
        <div className="absolute top-1/3 inset-x-0 px-5 z-10">
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-white text-[#198496]">UGC</span>
            <span className="text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-white/15 backdrop-blur text-white">Produit + Argent</span>
          </div>
          <h1 className="text-xl font-bold text-white leading-tight mt-2">Sneakers Shade Fit · Test running</h1>
          <div className="text-[11px] text-white/70 mt-1">Par Shade Fit · Cotonou</div>
        </div>

        {/* Bottom sheet */}
        <div className="absolute inset-x-0 bottom-16 rounded-t-3xl bg-white pt-3 pb-4 px-5">
          <div className="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3"/>
          <div className="grid grid-cols-3 gap-2 mb-4">
            <div className="text-center">
              <div className="text-base font-bold text-gray-900">35k</div>
              <div className="text-[9px] font-medium uppercase tracking-wider text-gray-500">Produit</div>
            </div>
            <div className="text-center border-x border-gray-100">
              <div className="text-base font-bold text-gray-900">20k</div>
              <div className="text-[9px] font-medium uppercase tracking-wider text-gray-500">Cash</div>
            </div>
            <div className="text-center">
              <div className="text-base font-bold text-[#198496]">3</div>
              <div className="text-[9px] font-medium uppercase tracking-wider text-gray-500">Vidéos</div>
            </div>
          </div>

          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1.5">Chronos une fois reçu</div>
          <div className="flex items-center gap-2 mb-4">
            <div className="flex-1 rounded-md border border-gray-200 p-2 flex items-center gap-2">
              <ChronoRing progress={0} size={28} stroke={3} label="7" />
              <div className="text-[10px] text-gray-700 leading-tight"><span className="font-semibold">Unboxing</span><br/><span className="text-gray-500">7 jours</span></div>
            </div>
            <div className="flex-1 rounded-md border border-gray-200 p-2 flex items-center gap-2">
              <ChronoRing progress={0} size={28} stroke={3} label="14" />
              <div className="text-[10px] text-gray-700 leading-tight"><span className="font-semibold">Avis</span><br/><span className="text-gray-500">14 jours</span></div>
            </div>
          </div>

          <button className="w-full py-3 text-sm font-semibold rounded-md text-white" style={{background:'#198496'}}>
            Accepter · Recevoir le produit
          </button>
          <div className="text-[10px] text-gray-500 text-center mt-2">
            En acceptant, tu t\u2019engages à livrer les 3 vidéos dans les délais. <span className="underline">Voir les règles.</span>
          </div>
        </div>

        <MobileTabBar active="missions" />
      </div>
    </PhoneFrame>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 8. ESPACE SUIVI FACE — Produit reçu → chronos → upload
// ═══════════════════════════════════════════════════════════════════

// 8A · SAFE — Vertical timeline with chrono badges + upload section
function FaceTrackingSafe() {
  return (
    <PhoneFrame>
      <MobileTopBar title="Booking en cours"
        right={<button className="w-8 h-8 -mr-2 flex items-center justify-center text-gray-700"><Icon name="more-vertical" size={18}/></button>}/>

      <div className="overflow-auto pb-20" style={{height:'calc(100% - 112px)'}}>
        {/* Booking header */}
        <div className="px-4 pt-4 pb-3">
          <div className="flex items-center gap-3">
            <StripePh w={52} h={52} label="" />
            <div className="flex-1 min-w-0">
              <div className="text-[10px] font-medium text-gray-500 uppercase tracking-wider">Shade Fit</div>
              <div className="text-sm font-semibold text-gray-900 truncate">Sneakers running</div>
              <StatusPill kind="received">Étape 5 / 6</StatusPill>
            </div>
          </div>
        </div>

        <div className="px-4">
          <BookingTimelineV current={5} />
        </div>

        {/* Current step — Upload livrable 1 */}
        <div className="px-4 mt-4">
          <div className="rounded-lg border border-[rgba(25,132,150,0.3)] bg-[rgba(25,132,150,0.04)] p-3">
            <div className="flex items-start gap-3">
              <ChronoRing progress={0.45} size={52} stroke={5} label="3j" sublabel="rest." />
              <div className="flex-1">
                <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496]">À faire maintenant</div>
                <div className="text-sm font-semibold text-gray-900 mt-0.5 leading-tight">Uploade ta vidéo Unboxing</div>
                <div className="text-[11px] text-gray-600 mt-0.5">30-60s · format vertical 9:16</div>
              </div>
            </div>
            <div className="mt-3 rounded-md border-2 border-dashed border-[#198496]/40 bg-white p-4 text-center">
              <Icon name="upload-cloud" size={26} style={{color:'#198496'}} stroke={1.8} className="mx-auto"/>
              <div className="text-xs font-medium text-gray-900 mt-1">Choisir une vidéo</div>
              <div className="text-[10px] text-gray-500">MP4 · max 80 Mo</div>
            </div>
            <button className="mt-3 w-full py-2.5 text-xs font-medium rounded-md text-white" style={{background:'#198496'}}>Uploader maintenant</button>
          </div>
        </div>

        {/* Reminder */}
        <div className="px-4 mt-3">
          <div className="flex items-start gap-2 p-3 rounded-md bg-orange-50 border border-orange-200">
            <Icon name="alert-triangle" size={14} stroke={2} style={{color:'#EA580C', marginTop:1}}/>
            <div className="text-[11px] text-orange-900 leading-snug">
              Si tu dépasses la deadline, ton compte sera <strong>automatiquement suspendu</strong> et ton abonnement bloqué.
            </div>
          </div>
        </div>
      </div>

      <MobileTabBar active="bookings" />
    </PhoneFrame>
  );
}

// 8B · BOLD — Hero countdown + sticky deadline + minimal timeline
function FaceTrackingBold() {
  return (
    <PhoneFrame>
      <MobileTopBar title="Sneakers Shade Fit"/>

      <div className="overflow-auto pb-20" style={{height:'calc(100% - 112px)'}}>
        {/* Hero countdown */}
        <div className="px-4 pt-4">
          <div className="rounded-2xl p-5 text-center" style={{background:'linear-gradient(180deg, rgba(25,132,150,0.08), rgba(25,132,150,0.02))', border:'1px solid rgba(25,132,150,0.18)'}}>
            <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-2">Vidéo 1 · Unboxing</div>
            <ChronoRing progress={0.45} size={140} stroke={10}
              label={<span><span className="text-3xl">3</span><span className="text-base">j</span></span>}
              sublabel="08h 14m restants" />
            <div className="mt-3 text-sm font-semibold text-gray-900">Deadline : Lun. 11 fév. · 14h32</div>
            <div className="text-[11px] text-gray-500 mt-0.5">Filme dès que possible pour rester en sécurité</div>
          </div>
        </div>

        {/* Upload CTA */}
        <div className="px-4 mt-3">
          <button className="w-full p-4 rounded-xl bg-[#0F1419] text-white flex items-center gap-3 text-left">
            <div className="w-11 h-11 rounded-md bg-[#198496] flex items-center justify-center">
              <Icon name="video" size={20} stroke={2.2}/>
            </div>
            <div className="flex-1">
              <div className="text-sm font-semibold">Uploader ta vidéo Unboxing</div>
              <div className="text-[11px] text-white/60">9:16 · 30-60s · max 80 Mo</div>
            </div>
            <Icon name="chevron-right" size={18}/>
          </button>
        </div>

        {/* Mini-timeline */}
        <div className="px-4 mt-4">
          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Étapes</div>
          <div className="flex items-center justify-between">
            {STEPS.map((s, i) => {
              const done = s.id < 5, active = s.id === 5;
              return (
                <React.Fragment key={s.id}>
                  <div className="flex flex-col items-center" style={{flex:'0 0 auto'}}>
                    <div className={`w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold ${
                      done ? 'bg-[#198496] text-white' : active ? 'bg-white border-2 border-[#198496] text-[#198496]' : 'bg-gray-100 text-gray-400'
                    }`}>{done ? <Icon name="check" size={11} stroke={3}/> : s.id}</div>
                    <div className="text-[9px] font-medium mt-1 text-center" style={{maxWidth:42, color: done || active ? '#0F1419' : '#9CA3AF'}}>{s.short}</div>
                  </div>
                  {i < STEPS.length-1 && <div className={`flex-1 h-0.5 ${done ? 'bg-[#198496]' : 'bg-gray-200'}`} style={{marginTop:-18}} />}
                </React.Fragment>
              );
            })}
          </div>
        </div>

        {/* Next deadline preview */}
        <div className="px-4 mt-4">
          <div className="rounded-lg border border-gray-200 p-3 flex items-center gap-3 bg-white">
            <ChronoRing progress={0.05} size={40} stroke={4} label="14" />
            <div className="flex-1 min-w-0">
              <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Ensuite</div>
              <div className="text-xs font-semibold text-gray-900">Vidéo 2 · Avis</div>
              <div className="text-[10px] text-gray-500">Le chrono 14j démarre après ton 1er upload validé</div>
            </div>
          </div>
        </div>

        {/* Tip */}
        <div className="px-4 mt-3 text-center">
          <button className="text-[11px] font-medium text-[#198496]">+ Voir le brief & règles</button>
        </div>
      </div>

      <MobileTabBar active="bookings" />
    </PhoneFrame>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 9. ÉTATS DE NOTIFICATION DEADLINES
// ═══════════════════════════════════════════════════════════════════

// 9A · SAFE — Notifications inbox (mixed states)
function FaceNotifSafe() {
  const items = [
    { ic:'check-circle', col:'#198496', t:'Booking accepté par Shade Fit', d:'Tu recevras ton colis sous 48h.', when:'il y a 2 min', read:false, chip:null },
    { ic:'package', col:'#1D4ED8', t:'Ton colis est en route', d:'Tracking GZM-COT-882194 · Livraison demain 14h-16h.', when:'il y a 1h', read:false, chip:'Expédié' },
    { ic:'timer', col:'#198496', t:'Chrono démarré · Unboxing', d:'Tu as 7 jours pour publier ta première vidéo.', when:'Hier · 15h32', read:true, chip:'7j restants' },
    { ic:'alert-triangle', col:'#EA580C', t:'Plus que 24h pour ta vidéo Avis', d:'Uploade ta vidéo 2 avant demain 14h.', when:'Aujourd\u2019hui · 14h', read:false, chip:'24h restantes', urgent:true },
    { ic:'alert-octagon', col:'#DC2626', t:'Suspension imminente', d:'Action requise dans 4h sinon ton compte sera bloqué.', when:'il y a 30 min', read:false, chip:'4h critiques', critical:true },
  ];
  return (
    <PhoneFrame>
      <MobileTopBar back={false} title="Notifications"
        right={<button className="text-[11px] text-[#198496] font-medium">Tout lire</button>}/>

      <div className="overflow-auto pb-20" style={{height:'calc(100% - 112px)'}}>
        <div className="px-4 py-3 flex gap-1.5 overflow-x-auto">
          {['Toutes','Deadlines','Bookings','Messages'].map((c,i) =>
            <button key={c} className={`whitespace-nowrap px-2.5 py-1 text-[11px] rounded-full border ${i===0 ? 'bg-[#0F1419] border-[#0F1419] text-white font-medium' : 'bg-white border-gray-200 text-gray-700'}`}>{c}</button>
          )}
        </div>

        <ul className="divide-y divide-gray-100">
          {items.map((it,i) => (
            <li key={i} className={`px-4 py-3 flex gap-3 ${it.critical ? 'bg-red-50/50' : it.urgent ? 'bg-orange-50/40' : !it.read ? 'bg-[rgba(25,132,150,0.03)]' : ''}`}>
              <div className="w-9 h-9 rounded-md flex items-center justify-center flex-shrink-0"
                style={{background: it.critical ? 'rgba(220,38,38,0.10)' : it.urgent ? 'rgba(234,88,12,0.10)' : 'rgba(25,132,150,0.08)'}}>
                <Icon name={it.ic} size={16} stroke={2.2} style={{color: it.col}}/>
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <div className="text-[12px] font-semibold text-gray-900 leading-tight">{it.t}</div>
                  {!it.read && <div className="w-2 h-2 rounded-full bg-[#198496] mt-1 flex-shrink-0" />}
                </div>
                <div className="text-[11px] text-gray-600 mt-0.5 leading-snug">{it.d}</div>
                <div className="mt-1.5 flex items-center gap-2">
                  <span className="text-[10px] text-gray-400">{it.when}</span>
                  {it.chip && (
                    <span className="text-[9px] font-semibold px-1.5 py-0.5 rounded uppercase tracking-wider"
                      style={{background: it.critical ? '#DC2626' : it.urgent ? '#EA580C' : 'rgba(25,132,150,0.10)',
                              color: it.critical || it.urgent ? '#fff' : '#198496'}}>{it.chip}</span>
                  )}
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>

      <MobileTabBar active="missions" />
    </PhoneFrame>
  );
}

// 9B · BOLD — Floating critical sheet + ambient list
function FaceNotifBold() {
  return (
    <PhoneFrame>
      <MobileTopBar back={false} title="Notifications"/>

      <div className="overflow-auto pb-20" style={{height:'calc(100% - 112px)'}}>
        {/* Critical alert hero */}
        <div className="mx-4 mt-3 rounded-2xl overflow-hidden" style={{background:'linear-gradient(135deg, #DC2626, #991B1B)'}}>
          <div className="p-5 text-white">
            <div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-white/80">
              <Icon name="alert-octagon" size={12} stroke={2.4}/> Action critique
            </div>
            <div className="mt-2 flex items-center gap-4">
              <ChronoRing progress={0.95} size={72} stroke={6}
                label={<span className="text-white"><span className="text-xl font-bold">4</span><span className="text-xs">h</span></span>}
                sublabel={<span style={{color:'rgba(255,255,255,0.7)'}}>rest.</span>}
                danger />
              <div className="flex-1">
                <div className="text-sm font-semibold">Uploade ta vidéo Avis</div>
                <div className="text-[11px] text-white/80 mt-1 leading-snug">Sans action sous 4h, ton compte sera suspendu et ton abonnement bloqué.</div>
              </div>
            </div>
            <div className="mt-4 flex gap-2">
              <button className="flex-1 py-2.5 text-xs font-semibold rounded-md bg-white text-red-700">Uploader maintenant</button>
              <button className="px-3 py-2.5 text-xs font-medium rounded-md bg-white/15 text-white">Aide</button>
            </div>
          </div>
        </div>

        {/* Other notifs in subtle list */}
        <div className="px-4 mt-5">
          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Aujourd\u2019hui</div>
          <ul className="space-y-2">
            {[
              { ic:'package', t:'Colis livré chez toi', d:'il y a 1h', col:'#198496'},
              { ic:'check-circle', t:'Vidéo Unboxing validée', d:'Hier · 18h', col:'#198496'},
              { ic:'message-circle', t:'Shade Fit t\u2019a envoyé un message', d:'Hier · 14h', col:'#1D4ED8'},
            ].map((it,i) => (
              <li key={i} className="flex items-center gap-3 p-3 rounded-lg bg-white border border-gray-100">
                <div className="w-8 h-8 rounded-md flex items-center justify-center" style={{background:'rgba(25,132,150,0.08)'}}>
                  <Icon name={it.ic} size={14} stroke={2.2} style={{color:it.col}}/>
                </div>
                <div className="flex-1">
                  <div className="text-xs font-semibold text-gray-900">{it.t}</div>
                  <div className="text-[10px] text-gray-500">{it.d}</div>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <MobileTabBar active="missions" />
    </PhoneFrame>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 10. PAGE SUSPENSION / ABONNEMENT BLOQUÉ
// ═══════════════════════════════════════════════════════════════════

// 10A · SAFE — Banner persistent + explainer
function FaceSuspendedSafe() {
  return (
    <PhoneFrame>
      <MobileTopBar back={false} title="Mon compte"/>

      <div className="overflow-auto pb-20" style={{height:'calc(100% - 112px)'}}>
        {/* Suspension banner */}
        <div className="mx-4 mt-3 rounded-lg bg-red-50 border border-red-200 p-3 flex items-start gap-2">
          <Icon name="ban" size={16} stroke={2.2} style={{color:'#DC2626', marginTop:1}}/>
          <div className="flex-1">
            <div className="text-xs font-semibold text-red-800">Compte suspendu</div>
            <div className="text-[11px] text-red-700 mt-0.5 leading-snug">Tu as dépassé une deadline UGC. Tes missions sont en pause.</div>
          </div>
        </div>

        {/* Profile dimmed */}
        <div className="px-4 mt-3 opacity-50 pointer-events-none">
          <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-md">
            <div className="w-12 h-12 rounded-full ph-stripe border border-gray-200"/>
            <div>
              <div className="text-sm font-semibold text-gray-900">Aïcha B.</div>
              <div className="text-[10px] text-gray-500">Membre Pro · Cotonou</div>
            </div>
          </div>
        </div>

        {/* Why */}
        <div className="px-4 mt-4">
          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Pourquoi cette suspension ?</div>
          <div className="rounded-lg border border-gray-200 bg-white p-3 space-y-2.5">
            <div className="flex items-start gap-2.5">
              <Icon name="x-circle" size={14} stroke={2} style={{color:'#DC2626', marginTop:1}}/>
              <div>
                <div className="text-xs font-semibold text-gray-900">Vidéo Avis manquante</div>
                <div className="text-[11px] text-gray-500">Sneakers Shade Fit · Deadline dépassée de 6h</div>
              </div>
            </div>
            <div className="flex items-start gap-2.5">
              <Icon name="x-circle" size={14} stroke={2} style={{color:'#DC2626', marginTop:1}}/>
              <div>
                <div className="text-xs font-semibold text-gray-900">Abonnement Pro bloqué</div>
                <div className="text-[11px] text-gray-500">Prochain prélèvement suspendu</div>
              </div>
            </div>
          </div>
        </div>

        {/* How to unlock */}
        <div className="px-4 mt-4">
          <div className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Comment réactiver</div>
          <div className="rounded-lg border border-[rgba(25,132,150,0.25)] bg-[rgba(25,132,150,0.03)] p-3 space-y-2.5">
            <div className="flex items-start gap-2.5">
              <div className="w-5 h-5 rounded-full bg-[#198496] text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">1</div>
              <div className="text-xs text-gray-700 leading-snug">Termine la mission en retard (uploade la vidéo) <span className="text-[10px] text-gray-500">— possible jusqu\u2019à J+30</span></div>
            </div>
            <div className="flex items-start gap-2.5">
              <div className="w-5 h-5 rounded-full bg-[#198496] text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">2</div>
              <div className="text-xs text-gray-700 leading-snug">Demande une révision auprès de WeAct si tu as une raison valable</div>
            </div>
            <div className="flex items-start gap-2.5">
              <div className="w-5 h-5 rounded-full bg-[#198496] text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">3</div>
              <div className="text-xs text-gray-700 leading-snug">Ton compte est réactivé sous <strong>24h</strong> après validation</div>
            </div>
          </div>
        </div>

        <div className="px-4 mt-4 grid grid-cols-2 gap-2">
          <BtnOutline full sm>Contacter le support</BtnOutline>
          <BtnPrimary full sm>Terminer la mission</BtnPrimary>
        </div>
      </div>

      <MobileTabBar active="profile" />
    </PhoneFrame>
  );
}

// 10B · BOLD — Full takeover dark, dramatic
function FaceSuspendedBold() {
  return (
    <PhoneFrame>
      <div className="relative h-full bg-[#0F1419] text-white overflow-hidden">
        {/* Status bar replacement */}
        <div className="absolute top-0 inset-x-0 h-9 flex items-center justify-between px-6 text-[11px] font-medium text-white z-30">
          <span>9:41</span>
          <div className="flex items-center gap-1.5">
            <Icon name="signal" size={12}/>
            <Icon name="wifi" size={12}/>
            <Icon name="battery-full" size={14}/>
          </div>
        </div>

        {/* Stripe BG */}
        <div className="absolute inset-0 ph-stripe-dark opacity-30"/>
        <div className="absolute inset-0 bg-gradient-to-b from-[#0F1419]/70 via-[#0F1419] to-[#0F1419]"/>

        <div className="relative h-full pt-16 px-6 flex flex-col">
          <div className="flex justify-end -mt-2">
            <button className="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center">
              <Icon name="x" size={16}/>
            </button>
          </div>

          <div className="mt-8">
            <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-500/15 border border-red-500/30">
              <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"/>
              <span className="text-[10px] font-bold uppercase tracking-widest text-red-400">Compte suspendu</span>
            </div>
            <h1 className="text-2xl font-bold mt-3 leading-tight">L\u2019accès aux missions UGC est temporairement bloqué.</h1>
            <p className="text-sm text-white/60 mt-2 leading-relaxed">Une vidéo Avis n\u2019a pas été livrée à temps. Pour protéger les producteurs, ton abonnement est mis en pause.</p>
          </div>

          {/* Stats line */}
          <div className="mt-7 grid grid-cols-3 gap-3">
            <div>
              <div className="text-xl font-bold text-red-400">1</div>
              <div className="text-[10px] uppercase tracking-wider text-white/40">deadline manquée</div>
            </div>
            <div>
              <div className="text-xl font-bold">11</div>
              <div className="text-[10px] uppercase tracking-wider text-white/40">UGC réussies</div>
            </div>
            <div>
              <div className="text-xl font-bold">24h</div>
              <div className="text-[10px] uppercase tracking-wider text-white/40">délai réactivation</div>
            </div>
          </div>

          <div className="mt-auto pb-6">
            <button className="w-full py-3.5 text-sm font-semibold rounded-md text-white" style={{background:'#198496'}}>
              Terminer la mission en retard
            </button>
            <button className="mt-2 w-full py-3 text-sm font-medium rounded-md bg-white/10 text-white">
              Faire appel auprès de WeAct
            </button>
            <div className="text-[10px] text-white/40 text-center mt-3">
              Tu peux toujours consulter ton profil et discuter avec les producteurs.
            </div>
          </div>
        </div>
      </div>
    </PhoneFrame>
  );
}

Object.assign(window, {
  FaceDiscoverSafe, FaceDiscoverBold,
  FaceMissionDetailSafe, FaceMissionDetailBold,
  FaceTrackingSafe, FaceTrackingBold,
  FaceNotifSafe, FaceNotifBold,
  FaceSuspendedSafe, FaceSuspendedBold,
});
