/* sections.jsx — content for each of the 11 sections. Exports getSection(id). */

const SEXE_OPTS = ['Homme', 'Femme', 'Autre'];
const VILLE_OPTS = ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', 'Bohicon', 'Natitingou'];
const PAYS_OPTS = ['Bénin', 'Togo', "Côte d'Ivoire", 'Nigeria', 'France', 'Sénégal'];
const CAT_OPTS = ['Acteur / Actrice', 'Mannequin', 'Modèle UGC', 'Figurant', 'Voix-off', 'Danseur', 'Influenceur'];
const NICHE_OPTS = ['Mode', 'Beauté', 'Publicité', 'Cinéma', 'Clip musical', 'Événementiel', 'Corporate', 'Sport'];

function SecHead({ title, desc }) {
  return (
    <div className="pf-sec-head">
      <h2>{title}</h2>
      {desc && <p>{desc}</p>}
    </div>
  );
}

/* ---------- individual bodies ---------- */
function InfosBody() {
  return (
    <div className="pf-form">
      <div className="pf-2col">
        <Field label="Prénom" icon="user" defaultValue="Imrane" />
        <Field label="Nom" icon="user" defaultValue="Sani" />
      </div>
      <Field label="Nom d'utilisateur" icon="idcard" defaultValue="imrane_ss" full />
      <div className="pf-note">
        <Icon name="info" size={16} />
        <p>Votre nom d'utilisateur apparaît dans l'adresse publique de votre profil : weact.bj/@imrane_ss</p>
      </div>
    </div>
  );
}

function IdentiteBody() {
  return (
    <div className="pf-form">
      <div className="pf-2col">
        <SelectField label="Sexe" icon="users" options={SEXE_OPTS} defaultValue="Homme" />
        <Field label="Date de naissance" icon="calendar" type="date" defaultValue="1996-04-12" />
      </div>
      <div className="pf-2col">
        <Field label="Nationalité" icon="globe" defaultValue="Béninois" />
        <SelectField label="Pays de résidence" icon="mappin" options={PAYS_OPTS} defaultValue="Bénin" />
      </div>
      <Field label="Numéro WhatsApp" icon="phone" type="tel" defaultValue="+229 01 67 45 23 10" full />
      <div className="pf-row" style={{ border: '1px solid var(--line)', borderRadius: 12, padding: '14px 16px' }}>
        <div className="k" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: 2 }}>
          <span style={{ fontWeight: 600, color: 'var(--ink-700)' }}>Afficher mon âge publiquement</span>
          <span style={{ fontSize: 12, color: 'var(--ink-500)' }}>Votre âge sera visible sur votre profil public</span>
        </div>
        <Toggle defaultOn={true} />
      </div>
    </div>
  );
}

function PhysiqueBody() {
  return (
    <div className="pf-form">
      <div className="pf-2col">
        <Field label="Taille (cm)" icon="ruler" type="number" defaultValue="175" />
        <Field label="Poids (kg)" icon="weight" type="number" defaultValue="72" />
      </div>
      <div className="pf-note">
        <Icon name="info" size={16} />
        <p>Ces informations aident les producteurs à trouver des talents correspondant à leurs besoins de casting.</p>
      </div>
    </div>
  );
}

function LanguesBody() {
  return (
    <div className="pf-form">
      <ChipInput label="Langues parlées" defaultChips={['Français', 'Fon', 'Yoruba', 'Anglais']} placeholder="Ajouter une langue puis Entrée" />
      <div className="pf-help">Jusqu'à 10 langues · appuyez sur Entrée pour ajouter</div>
    </div>
  );
}

function BioBody() {
  return (
    <div className="pf-form">
      <TextareaField label="Bio" icon="filetext" rows={5} max={500}
        defaultValue="Comédien et modèle basé à Cotonou. Passionné par le cinéma et la publicité, je collabore depuis 5 ans avec des marques et productions locales." />
      <div className="pf-2col">
        <SelectField label="Ville" icon="mappin" options={VILLE_OPTS} defaultValue="Cotonou" />
        <SelectField label="Pays" icon="globe" options={PAYS_OPTS} defaultValue="Bénin" />
      </div>
    </div>
  );
}

function AlbumBody() {
  const covers = [true, false, false, false, false];
  return (
    <div>
      <div className="pf-photos">
        {covers.map((isCover, i) => (
          <div className="pf-photo" key={i}>
            <div className="ph-stripe" />
            {isCover && <span className="ph-cover">Couverture</span>}
            <button className="ph-del" aria-label="Supprimer"><Icon name="trash" size={14} /></button>
          </div>
        ))}
        <button className="pf-photo-add">
          <Icon name="plus" size={22} />
          <span>Ajouter</span>
        </button>
      </div>
      <div className="pf-help" style={{ marginTop: 14 }}>5 / 8 photos · JPG ou PNG, 5 Mo max. La photo de couverture s'affiche en premier sur votre profil.</div>
    </div>
  );
}

function VidRow({ icon, title, badge, badgeKind, sub, filled }) {
  return (
    <div className="pf-vid">
      <div className={'thumb' + (filled ? '' : ' empty')}>
        {filled ? <span className="play"><Icon name="play" size={20} /></span> : <Icon name="video" size={22} />}
      </div>
      <div className="info">
        <div className="vt">{title} <span className={'pf-badge ' + badgeKind}>{badge}</span></div>
        <div className="vs">{sub}</div>
      </div>
      <button className="pf-btn pf-btn-soft">
        <Icon name={filled ? 'pencil' : 'plus'} size={15} />{filled ? 'Remplacer' : 'Ajouter'}
      </button>
    </div>
  );
}

function VideosBody() {
  return (
    <div className="pf-form">
      <VidRow icon="video" title="Vidéo de présentation" badge="Ajoutée" badgeKind="ok" filled
        sub="Une courte vidéo pour vous présenter aux producteurs · 0:42" />
      <VidRow icon="video" title="Vidéo d'acting" badge="À ajouter" badgeKind="todo"
        sub="Démontrez votre talent d'acteur" />
      <VidRow icon="video" title="Vidéo UGC" badge="Premium" badgeKind="pro"
        sub="Contenu généré par l'utilisateur — disponible avec un abonnement supérieur" />
    </div>
  );
}

function CategorieBody() {
  return (
    <div className="pf-form">
      <SelChips label="Catégories" options={CAT_OPTS} defaultSelected={['Acteur / Actrice', 'Modèle UGC']} />
      <SelChips label="Niches" options={NICHE_OPTS} defaultSelected={['Publicité', 'Cinéma']} />
    </div>
  );
}

function ExpCard({ yr, role, org, desc }) {
  return (
    <div className="pf-exp">
      <span className="yr">{yr}</span>
      <div className="body">
        <div className="role">{role}</div>
        <div className="org">{org}</div>
        <div className="desc">{desc}</div>
      </div>
      <div className="acts">
        <button className="pf-iconbtn" aria-label="Modifier"><Icon name="pencil" size={15} /></button>
        <button className="pf-iconbtn del" aria-label="Supprimer"><Icon name="trash" size={15} /></button>
      </div>
    </div>
  );
}

function ExperiencesBody() {
  return (
    <div className="pf-form">
      <ExpCard yr="2024" role="Rôle principal — Spot TV" org="MTN Bénin · Agence Voodoo"
        desc="Visage de la campagne nationale « Yello Family », diffusée sur les chaînes nationales et les réseaux sociaux." />
      <ExpCard yr="2023" role="Mannequin défilé" org="Fashion Week Cotonou"
        desc="Présentation des collections de trois créateurs béninois lors de l'édition 2023." />
      <button className="pf-btn pf-btn-ghost" style={{ alignSelf: 'flex-start' }}>
        <Icon name="plus" size={16} />Ajouter une expérience
      </button>
    </div>
  );
}

function SecuriteBody() {
  return (
    <div>
      <div className="pf-block">
        <div className="bh">
          <span className="ic"><Icon name="mail" size={18} /></span>
          <div><div className="tt">Adresse e-mail</div><div className="ts">imrane.sani@email.com · vérifiée</div></div>
        </div>
        <div className="pf-form">
          <Field label="Nouvelle adresse e-mail" icon="mail" type="email" full />
          <button className="pf-btn pf-btn-soft" style={{ alignSelf: 'flex-start' }}>Mettre à jour l'e-mail</button>
        </div>
      </div>
      <div className="pf-block">
        <div className="bh">
          <span className="ic"><Icon name="lock" size={18} /></span>
          <div><div className="tt">Mot de passe</div><div className="ts">Dernière modification il y a 3 mois</div></div>
        </div>
        <div className="pf-form">
          <Field label="Mot de passe actuel" icon="lock" type="password" full />
          <div className="pf-2col">
            <Field label="Nouveau mot de passe" icon="lock" type="password" />
            <Field label="Confirmer" icon="lock" type="password" />
          </div>
          <button className="pf-btn pf-btn-soft" style={{ alignSelf: 'flex-start' }}>Changer le mot de passe</button>
        </div>
      </div>
    </div>
  );
}

function DonneesBody() {
  return (
    <div>
      <div className="pf-note" style={{ marginBottom: 18 }}>
        <Icon name="shieldcheck" size={16} />
        <p>Conformément à la réglementation, vous pouvez télécharger une copie de vos données ou supprimer définitivement votre compte à tout moment.</p>
      </div>
      <div className="pf-block">
        <div className="bh">
          <span className="ic"><Icon name="download" size={18} /></span>
          <div><div className="tt">Exporter mes données</div><div className="ts">Recevez une archive de votre profil, vos médias et votre activité.</div></div>
        </div>
        <button className="pf-btn pf-btn-soft" style={{ alignSelf: 'flex-start' }}>
          <Icon name="download" size={15} />Demander l'export
        </button>
      </div>
      <div className="pf-danger-zone" style={{ marginTop: 16 }}>
        <div className="dh">Supprimer mon compte</div>
        <div className="ds">Cette action est irréversible. Toutes vos données, photos et candidatures seront définitivement effacées.</div>
        <button className="pf-btn pf-btn-danger"><Icon name="trash" size={15} />Supprimer mon compte</button>
      </div>
    </div>
  );
}

function TarifBody() {
  const [v, setV] = React.useState(25000);
  const day = Number(v) || 0;
  const half = Math.round(day / 2);
  const fmt = (n) => n.toLocaleString('fr-FR').replace(/\u202f/g, ' ');
  return (
    <div className="pf-form">
      <div style={{ maxWidth: 360 }}>
        <div className="pf-field">
          <input type="number" value={v} placeholder=" " onChange={(e) => setV(e.target.value)} />
          <Icon name="coins" size={17} className="lead" />
          <label>Tarif journalier (F CFA)</label>
        </div>
      </div>
      <div className="pf-tarif-breakdown">
        <div><span>Journée (8h)</span><strong>{fmt(day)} F CFA</strong></div>
        <div><span>Demi-journée (4h)</span><strong>{fmt(half)} F CFA</strong></div>
      </div>
      <div className="pf-note">
        <Icon name="info" size={16} />
        <p>Ce tarif est affiché aux producteurs et sert de base aux propositions de mission. La demi-journée est calculée automatiquement.</p>
      </div>
    </div>
  );
}

const REGISTRY = {
  infos: { title: 'Informations personnelles', desc: 'Votre nom et votre identifiant public sur WeAct.', Body: InfosBody, save: 50 },
  identite: { title: 'Identité', desc: 'Sexe, date de naissance, nationalité et pays de résidence.', Body: IdentiteBody, save: 100 },
  physique: { title: 'Caractéristiques physiques', desc: 'Renseignez votre taille et votre poids pour les recherches des producteurs.', Body: PhysiqueBody, save: 50 },
  langues: { title: 'Langues parlées', desc: 'Les langues que vous maîtrisez pour vos rôles et tournages.', Body: LanguesBody, save: 50 },
  bio: { title: 'Bio & Localisation', desc: 'Présentez votre parcours et indiquez votre localisation aux producteurs.', Body: BioBody, save: 75 },
  album: { title: 'Album photos', desc: 'Montrez votre polyvalence avec jusqu’à 8 photos.', Body: AlbumBody, save: 0 },
  videos: { title: 'Vidéos', desc: 'Présentation, acting et UGC pour démontrer votre talent en mouvement.', Body: VideosBody, save: 0 },
  categorie: { title: 'Catégorie & Niche', desc: 'Aidez les producteurs à vous trouver selon votre spécialisation.', Body: CategorieBody, save: 75 },
  experiences: { title: 'Expériences professionnelles', desc: 'Vos collaborations, castings et projets passés.', Body: ExperiencesBody, save: 0 },
  tarif: { title: 'Tarif', desc: 'Définissez votre tarif journalier — visible par les producteurs.', Body: TarifBody, save: true },
  securite: { title: 'Email & mot de passe', desc: 'Gérez vos identifiants de connexion.', Body: SecuriteBody, save: 0 },
  donnees: { title: 'Mes données personnelles', desc: 'Exportez ou supprimez vos données personnelles.', Body: DonneesBody, save: 0 },
};

function getSection(id) { return REGISTRY[id]; }

Object.assign(window, { getSection, SecHead });
