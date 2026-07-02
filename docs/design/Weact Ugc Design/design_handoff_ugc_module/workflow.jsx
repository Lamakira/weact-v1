// workflow.jsx — System workflow diagram (6 steps)

const { Icon, ChronoRing, STEPS } = window;

// 11A · SAFE — Clean horizontal flow with role lanes
function WorkflowSafe() {
  const steps = [
    { id: 1, title: 'Paiement',    role: 'producer', icon: 'credit-card',  desc: 'Commission WeAct payée via FedaPay / MTN / Moov',  chrono: null },
    { id: 2, title: 'Acceptation', role: 'face',     icon: 'check-circle', desc: 'La Face accepte le deal',                          chrono: null },
    { id: 3, title: 'Expédition',  role: 'producer', icon: 'package',      desc: 'Produit envoyé + tracking saisi',                  chrono: null },
    { id: 4, title: 'Réception',   role: 'face',     icon: 'box',          desc: '« Produit reçu » → chronos démarrent',             chrono: 'trigger' },
    { id: 5, title: 'Unboxing',    role: 'face',     icon: 'video',        desc: 'Vidéo 1 uploadée + validée producteur',            chrono: '7 jours' },
    { id: 6, title: 'Avis',        role: 'face',     icon: 'message-square', desc: 'Vidéo 2 uploadée + validée → clôture',           chrono: '14 jours' },
  ];

  return (
    <div className="h-full bg-white p-10 overflow-auto">
      <div className="max-w-[1100px] mx-auto">
        <div className="mb-1 text-[10px] font-bold uppercase tracking-widest text-[#198496]">Workflow système · v1</div>
        <h1 className="text-2xl font-bold text-gray-900">Tunnel UGC en 6 étapes</h1>
        <p className="text-sm text-gray-500 mt-1 max-w-2xl">Le flux principal et les déclencheurs côté Producteur (P) et Face (F). Toute deadline dépassée déclenche une suspension automatique.</p>

        {/* Legend */}
        <div className="mt-5 flex items-center gap-4 text-[11px]">
          <span className="inline-flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[#198496]"/> Action Producteur</span>
          <span className="inline-flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[#0F1419]"/> Action Face</span>
          <span className="inline-flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-orange-500"/> Chrono déclenché</span>
          <span className="inline-flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-red-500"/> Suspension auto</span>
        </div>

        {/* Flow */}
        <div className="mt-10 grid grid-cols-6 gap-2">
          {steps.map((s, i) => (
            <div key={s.id} className="relative">
              <div className="absolute -top-7 left-0 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                Étape {s.id}
              </div>

              <div className="rounded-lg border border-gray-200 bg-white p-3.5">
                <div className="flex items-start justify-between mb-2">
                  <div className="w-9 h-9 rounded-md flex items-center justify-center"
                    style={{background: s.role === 'producer' ? 'rgba(25,132,150,0.10)' : 'rgba(15,20,25,0.08)'}}>
                    <Icon name={s.icon} size={16} stroke={2.2}
                      style={{color: s.role === 'producer' ? '#198496' : '#0F1419'}}/>
                  </div>
                  <span className="text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded"
                    style={{
                      background: s.role === 'producer' ? '#198496' : '#0F1419',
                      color: '#fff'
                    }}>
                    {s.role === 'producer' ? 'P' : 'F'}
                  </span>
                </div>
                <div className="text-xs font-bold text-gray-900">{s.title}</div>
                <div className="text-[10px] text-gray-500 mt-1 leading-snug min-h-[40px]">{s.desc}</div>

                {s.chrono && (
                  <div className="mt-2 pt-2 border-t border-gray-100 flex items-center gap-1.5">
                    <Icon name="timer" size={11} stroke={2.4} style={{color: '#EA580C'}}/>
                    <span className="text-[10px] font-semibold text-orange-700">{s.chrono === 'trigger' ? 'Déclencheur' : s.chrono}</span>
                  </div>
                )}
              </div>

              {i < steps.length - 1 && (
                <div className="absolute top-1/2 -right-1.5 -translate-y-1/2 w-3 h-3 rounded-full bg-white border-2 border-gray-300 z-10"
                  style={{boxShadow:'0 0 0 4px white'}} />
              )}
            </div>
          ))}
        </div>

        {/* Connector line */}
        <div className="relative -mt-[105px] mb-[80px] mx-12 h-0.5 bg-gradient-to-r from-[#198496] via-[#198496] to-[#198496]/30" />

        {/* Suspension branch */}
        <div className="mt-2 rounded-lg border-2 border-dashed border-red-300 bg-red-50/50 p-5">
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 rounded-md bg-red-100 flex items-center justify-center flex-shrink-0">
              <Icon name="ban" size={18} stroke={2.2} style={{color:'#DC2626'}}/>
            </div>
            <div className="flex-1">
              <div className="text-[10px] font-bold uppercase tracking-widest text-red-700">Branche d\u2019exception</div>
              <div className="text-sm font-semibold text-gray-900 mt-0.5">Si chrono Unboxing (7j) ou Avis (14j) dépassé :</div>
              <ul className="mt-2 grid grid-cols-3 gap-3 text-xs text-gray-700">
                <li className="flex items-start gap-1.5"><Icon name="x-circle" size={12} stroke={2} style={{color:'#DC2626', marginTop:2}}/> Compte Face <strong className="font-semibold">suspendu automatiquement</strong></li>
                <li className="flex items-start gap-1.5"><Icon name="x-circle" size={12} stroke={2} style={{color:'#DC2626', marginTop:2}}/> Abonnement Face <strong className="font-semibold">bloqué</strong> (prélèvements gelés)</li>
                <li className="flex items-start gap-1.5"><Icon name="replace" size={12} stroke={2} style={{color:'#198496', marginTop:2}}/> Producteur notifié + <strong className="font-semibold">remplacement proposé</strong></li>
              </ul>
            </div>
          </div>
        </div>

        {/* Bottom legend - states */}
        <div className="mt-8 grid grid-cols-4 gap-3">
          {[
            { c:'#198496', t:'Acceptation', sub:'Commission encaissée définitivement' },
            { c:'#1D4ED8', t:'Expédition', sub:'Tracking visible côté Face' },
            { c:'#EA580C', t:'Chrono actif', sub:'Compte à rebours visible partout' },
            { c:'#DC2626', t:'Dépassement', sub:'Suspension + remboursement' },
          ].map((b,i) => (
            <div key={i} className="rounded-md border border-gray-200 p-3">
              <div className="flex items-center gap-1.5">
                <span className="w-2 h-2 rounded-full" style={{background:b.c}}/>
                <span className="text-xs font-semibold text-gray-900">{b.t}</span>
              </div>
              <div className="text-[10px] text-gray-500 mt-0.5">{b.sub}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// 11B · BOLD — Vertical swim-lane with chrono visualization
function WorkflowBold() {
  return (
    <div className="h-full bg-[#0F1419] text-white p-8 overflow-auto">
      <div className="max-w-[1100px] mx-auto">
        <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496]">Workflow UGC · Schéma technique</div>
        <h1 className="text-2xl font-bold mt-1">Du paiement à la livraison · 6 étapes, 2 chronos</h1>

        {/* Lanes */}
        <div className="mt-8 grid grid-cols-[120px_1fr_1fr_140px] gap-x-4 gap-y-0">
          {/* Header row */}
          <div></div>
          <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] pb-3 border-b border-white/10 flex items-center gap-1.5">
            <div className="w-2 h-2 rounded-full bg-[#198496]"/> Producteur
          </div>
          <div className="text-[10px] font-bold uppercase tracking-widest text-white/60 pb-3 border-b border-white/10 flex items-center gap-1.5">
            <div className="w-2 h-2 rounded-full bg-white/60"/> Face
          </div>
          <div className="text-[10px] font-bold uppercase tracking-widest text-orange-400 pb-3 border-b border-white/10 flex items-center gap-1.5">
            <Icon name="timer" size={11} stroke={2.4}/> Chrono
          </div>

          {/* Rows */}
          {[
            { n:'01', title:'Paiement',   producer:{ic:'credit-card',  t:'Paie la commission 10% / 2 500 min via FedaPay-MTN-Moov'}, face:null, chrono:null },
            { n:'02', title:'Acceptation',producer:null, face:{ic:'check-circle', t:'Accepte le deal — la commission est définitivement encaissée'}, chrono:null },
            { n:'03', title:'Expédition', producer:{ic:'package', t:'Envoie le produit + saisit le numéro de tracking'}, face:null, chrono:null },
            { n:'04', title:'Réception',  producer:null, face:{ic:'box', t:'Clique « Produit reçu » → déclenche les chronos'}, chrono:'trigger' },
            { n:'05', title:'Unboxing',   producer:{ic:'eye', t:'Valide la vidéo (ou demande retouche)'}, face:{ic:'video', t:'Uploade la vidéo déballage'}, chrono:'7j' },
            { n:'06', title:'Avis',       producer:{ic:'eye', t:'Valide la vidéo finale → clôture mission'}, face:{ic:'message-square', t:'Uploade la vidéo avis · 7-14j après la 1ère'}, chrono:'14j' },
          ].map((r, i) => (
            <React.Fragment key={r.n}>
              <div className="py-4 border-b border-white/5 flex items-center">
                <div className="flex flex-col">
                  <span className="text-[9px] font-bold uppercase tracking-widest text-white/40">Étape {r.n}</span>
                  <span className="text-sm font-bold text-white mt-0.5">{r.title}</span>
                </div>
              </div>
              <Cell role="producer" data={r.producer} />
              <Cell role="face" data={r.face} />
              <div className="py-4 border-b border-white/5 flex items-center">
                {r.chrono === 'trigger' ? (
                  <div className="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-orange-500/15 border border-orange-500/30 text-[10px] font-bold uppercase tracking-widest text-orange-300">
                    <Icon name="zap" size={10} stroke={2.4}/> Trigger
                  </div>
                ) : r.chrono ? (
                  <div className="flex items-center gap-2">
                    <ChronoRing progress={r.chrono === '7j' ? 0.3 : 0.5} size={36} stroke={4} label={r.chrono} />
                    <span className="text-[10px] font-medium text-white/70 leading-tight">deadline<br/>dépassée<br/>= suspension</span>
                  </div>
                ) : (
                  <span className="text-[10px] text-white/30">—</span>
                )}
              </div>
            </React.Fragment>
          ))}
        </div>

        {/* Suspension callout */}
        <div className="mt-8 grid grid-cols-2 gap-4">
          <div className="rounded-xl border border-white/10 bg-white/5 p-5">
            <div className="text-[10px] font-bold uppercase tracking-widest text-[#198496] mb-2">Sécurité Producteur</div>
            <ul className="space-y-1.5 text-xs text-white/80">
              <li className="flex items-start gap-2"><Icon name="check" size={12} stroke={2.5} style={{color:'#198496', marginTop:2}}/> Commission séquestrée jusqu\u2019à acceptation</li>
              <li className="flex items-start gap-2"><Icon name="check" size={12} stroke={2.5} style={{color:'#198496', marginTop:2}}/> Remboursement si la Face refuse (24h)</li>
              <li className="flex items-start gap-2"><Icon name="check" size={12} stroke={2.5} style={{color:'#198496', marginTop:2}}/> Remplacement gratuit si abandon</li>
              <li className="flex items-start gap-2"><Icon name="check" size={12} stroke={2.5} style={{color:'#198496', marginTop:2}}/> Validation manuelle de chaque vidéo</li>
            </ul>
          </div>
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 p-5">
            <div className="text-[10px] font-bold uppercase tracking-widest text-red-300 mb-2">Sanction Face</div>
            <ul className="space-y-1.5 text-xs text-white/80">
              <li className="flex items-start gap-2"><Icon name="ban" size={12} stroke={2.5} style={{color:'#FCA5A5', marginTop:2}}/> Compte suspendu sur dépassement de chrono</li>
              <li className="flex items-start gap-2"><Icon name="ban" size={12} stroke={2.5} style={{color:'#FCA5A5', marginTop:2}}/> Abonnement bloqué (prélèvements gelés)</li>
              <li className="flex items-start gap-2"><Icon name="ban" size={12} stroke={2.5} style={{color:'#FCA5A5', marginTop:2}}/> Plus de nouvelles missions UGC visibles</li>
              <li className="flex items-start gap-2"><Icon name="rotate-ccw" size={12} stroke={2.5} style={{color:'#FCA5A5', marginTop:2}}/> Réactivation 24h après livraison du retard</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}

function Cell({ role, data }) {
  if (!data) return <div className="py-4 border-b border-white/5"><div className="text-[10px] text-white/20">—</div></div>;
  return (
    <div className="py-4 border-b border-white/5">
      <div className="flex items-start gap-2.5">
        <div className="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0"
          style={{background: role === 'producer' ? 'rgba(25,132,150,0.15)' : 'rgba(255,255,255,0.08)'}}>
          <Icon name={data.ic} size={13} stroke={2.2} style={{color: role === 'producer' ? '#198496' : '#fff'}} />
        </div>
        <div className="text-[11px] text-white/80 leading-snug">{data.t}</div>
      </div>
    </div>
  );
}

Object.assign(window, { WorkflowSafe, WorkflowBold });
