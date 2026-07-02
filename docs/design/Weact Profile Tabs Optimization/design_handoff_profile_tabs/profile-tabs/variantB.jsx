/* variantB.jsx — Direction B: underline family tabs + vertical section nav (master / detail). */
const { useState: useStateB } = React;

function PanelContent({ sectionId }) {
  const sec = getSection(sectionId);
  const Body = sec.Body;
  return (
    <React.Fragment>
      <div className="pf-panel-body">
        <SecHead title={sec.title} desc={sec.desc} />
        <Body />
      </div>
      {sec.save !== 0 ? <SaveBar xp={sec.save} /> : null}
    </React.Fragment>
  );
}

function VariantB() {
  const [fam, setFam] = useStateB('profil');
  const [secByFam, setSecByFam] = useStateB({
    profil: 'infos', portfolio: 'album', carriere: 'categorie', compte: 'securite',
  });
  const family = FAMILIES.find((f) => f.id === fam);
  const activeSec = secByFam[fam];

  const navTo = (famId, secId) => {
    setFam(famId);
    setSecByFam((prev) => ({ ...prev, [famId]: secId }));
  };

  return (
    <div className="pf-root">
      <div className="pf-page">
        <div className="pf-page-head">
          <h1>Mon profil</h1>
          <p>Gérez vos informations et votre présence sur WeAct</p>
        </div>
        <div className="pf-grid">
          <LeftRail onNavigate={navTo} />
          <div className="pf-main">
            {/* underline family tabs */}
            <div className="pf-utabbar">
              {FAMILIES.map((f) => (
                <button key={f.id} className={'pf-utab' + (f.id === fam ? ' active' : '')} onClick={() => setFam(f.id)}>
                  <Icon name={f.icon} size={17} />
                  {f.label}
                </button>
              ))}
            </div>
            {/* master / detail */}
            <div className="pf-card pf-panel">
              <div className="pf-detail">
                <nav className="pf-vnav">
                  {family.sections.map((s) => (
                    <button key={s.id}
                      className={'pf-vitem' + (s.id === activeSec ? ' active' : '')}
                      onClick={() => setSecByFam({ ...secByFam, [fam]: s.id })}>
                      <Icon name={s.icon} size={17} />
                      {s.short || s.label}
                      {!s.complete && <span className="dot" title="À compléter" />}
                    </button>
                  ))}
                </nav>
                <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                  <PanelContent sectionId={activeSec} />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { VariantB, PanelContent });
