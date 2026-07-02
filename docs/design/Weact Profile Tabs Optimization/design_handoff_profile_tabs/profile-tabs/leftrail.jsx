/* leftrail.jsx — consolidated, lighter left rail (shared by both variants). */

/* Missing-profile items → which family/section each links to */
const MISSING_ITEMS = [
  { id: 'photo', label: 'Ajoutez une photo de profil', fam: 'portfolio', sec: 'album' },
  { id: 'video-pres', label: 'Ajoutez une vidéo de présentation', fam: 'portfolio', sec: 'videos' },
  { id: 'video-acting', label: "Ajoutez une vidéo d'acting", fam: 'portfolio', sec: 'videos' },
  { id: 'bio', label: 'Ajoutez une bio', fam: 'profil', sec: 'bio' },
  { id: 'ville', label: 'Ajoutez votre ville', fam: 'profil', sec: 'bio' },
  { id: 'categorie', label: 'Sélectionnez votre catégorie', fam: 'carriere', sec: 'categorie' },
  { id: 'langues', label: 'Ajoutez vos langues parlées', fam: 'profil', sec: 'langues' },
];

function CompletionCard({ onNavigate }) {
  return (
    <div className="pf-card pf-pad">
      <div className="pf-compl-head">
        <Icon name="piechart" size={19} />
        <span>Complétion</span>
      </div>
      <div className="pf-compl">
        <div className="pf-compl-top">
          <span className="lbl">Complétez votre profil</span>
          <span className="pct">{PROFILE.completion}%</span>
        </div>
        <div className="pf-bar"><span style={{ width: PROFILE.completion + '%' }} /></div>
      </div>
      <div className="pf-missing-h">Éléments manquants</div>
      <div className="pf-missing-list">
        {MISSING_ITEMS.map((m) => (
          <button key={m.id} className="pf-missing-item" onClick={() => onNavigate && onNavigate(m.fam, m.sec)}>
            <Icon name="alert" size={16} />
            <span>{m.label}</span>
          </button>
        ))}
      </div>
    </div>
  );
}

function Stars({ value }) {
  return (
    <span className="pf-stars">
      {[1, 2, 3, 4, 5].map((i) => (
        <Icon key={i} name="star" size={15} className={i <= Math.round(value) ? '' : 'empty'} />
      ))}
    </span>
  );
}

function ResumeRow({ icon, k, children }) {
  return (
    <div className="pf-row">
      <span className="k"><Icon name={icon} size={16} />{k}</span>
      <span className="v">{children}</span>
    </div>
  );
}

function AvailabilityRow() {
  const [on, setOn] = React.useState(true);
  return (
    <div className="pf-row">
      <span className="k"><Icon name="check" size={16} />Disponibilité</span>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
        <span className="pf-pill-on" style={on ? {} : { color: 'var(--ink-500)', background: 'var(--line)' }}>
          {on ? 'Disponible' : 'Indisponible'}
        </span>
        <button className={'pf-switch' + (on ? ' on' : '')} onClick={() => setOn(!on)} role="switch" aria-checked={on} aria-label="Disponibilité"><i /></button>
      </div>
    </div>
  );
}

function TarifRow() {
  return (
    <div className="pf-row">
      <span className="k"><Icon name="coins" size={16} />Tarif / jour</span>
      <span className="v">{PROFILE.tarif} FCFA</span>
    </div>
  );
}

function LeftRail({ onNavigate }) {
  const p = PROFILE;
  return (
    <aside className="pf-rail">
      {/* identity */}
      <div className="pf-card pf-identity">
        <div className="pf-avatar-wrap">
          <div className="pf-avatar">{p.initials}</div>
          <button className="pf-cam" aria-label="Changer la photo"><Icon name="camera" size={16} /></button>
        </div>
        <div className="pf-name">{p.prenom} {p.nom}</div>
        <div className="pf-handle">@{p.username}</div>
      </div>

      {/* completion — full card with missing items */}
      <CompletionCard onNavigate={onNavigate} />

      {/* status: dispo · tarif · note merged */}
      <div className="pf-card pf-pad">
        <div className="pf-rail-h">Statut</div>
        <AvailabilityRow />
        <TarifRow />
        <div className="pf-row">
          <span className="k"><Icon name="star" size={16} />Ma note</span>
          <span className="v" style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
            <Stars value={p.rating} /> <span style={{ color: 'var(--ink-500)', fontWeight: 500 }}>{p.rating}</span>
          </span>
        </div>
      </div>

      {/* résumé */}
      <div className="pf-card pf-pad pf-resume">
        <div className="pf-rail-h">Résumé</div>
        <ResumeRow icon="users" k="Sexe">{p.sexe}</ResumeRow>
        <ResumeRow icon="calendar" k="Âge">{p.age}</ResumeRow>
        <ResumeRow icon="ruler" k="Taille">{p.taille}</ResumeRow>
        <ResumeRow icon="globe" k="Nationalité">{p.nationalite}</ResumeRow>
        <ResumeRow icon="mappin" k="Ville">{p.ville}</ResumeRow>
        <div className="pf-row">
          <span className="k"><Icon name="globe" size={16} />Langues</span>
          <span className="v" style={{ maxWidth: 150, textAlign: 'right' }}>{p.langues.join(', ')}</span>
        </div>
      </div>
    </aside>
  );
}

Object.assign(window, { LeftRail });
