/* core.jsx — icons, profile data, tab structure. Exports to window. */

const ICON_PATHS = {
  piechart: '<path d="M21 12c.552 0 1.005-.449.95-.998a10 10 0 0 0-8.953-8.951c-.55-.055-.998.398-.998.95v8a1 1 0 0 0 1 1z"/><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>',
  alert: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  idcard: '<rect width="18" height="18" x="3" y="4" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/>',
  fingerprint: '<rect width="18" height="18" x="3" y="4" rx="2"/><circle cx="12" cy="10" r="2.2"/><path d="M8.5 16a3.5 3.5 0 0 1 7 0"/>',
  ruler: '<path d="M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z"/><path d="m7.5 10.5 2 2"/><path d="m10.5 7.5 2 2"/><path d="m13.5 4.5 2 2"/><path d="m4.5 13.5 2 2"/>',
  globe: '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
  mappin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
  image: '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
  video: '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
  briefcase: '<rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
  tag: '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
  layers: '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 12.1-9.17 4.16a2 2 0 0 1-1.66 0L2 12.1"/><path d="m22 17.1-9.17 4.16a2 2 0 0 1-1.66 0L2 17.1"/>',
  lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
  database: '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
  camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
  calendar: '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 2v4"/><path d="M16 2v4"/>',
  filetext: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>',
  mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
  phone: '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384z"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  weight: '<circle cx="12" cy="5" r="3"/><path d="M6.5 8h11l1.7 11.3A2 2 0 0 1 17.2 22H6.8a2 2 0 0 1-2-2.7Z"/>',
  plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  chevron: '<path d="m6 9 6 6 6-6"/>',
  x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  pencil: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
  trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
  star: '<path d="M11.5 2.3a.5.5 0 0 1 .9 0l2.4 4.9 5.4.8a.5.5 0 0 1 .3.85l-3.9 3.8.9 5.4a.5.5 0 0 1-.72.52L12 16.5l-4.8 2.5a.5.5 0 0 1-.72-.52l.9-5.4-3.9-3.8a.5.5 0 0 1 .3-.85l5.4-.8z"/>',
  play: '<polygon points="6 3 20 12 6 21 6 3"/>',
  coins: '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
  sparkles: '<path d="M9.94 14.34A2 2 0 0 0 8.66 13.06L3.6 11.5l5.06-1.56a2 2 0 0 0 1.28-1.28L11.5 3.6l1.56 5.06a2 2 0 0 0 1.28 1.28l5.06 1.56-5.06 1.56a2 2 0 0 0-1.28 1.28L11.5 19.4Z"/>',
  shieldcheck: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
};

function Icon({ name, size = 18, stroke = 2, className = '', style = {} }) {
  const inner = ICON_PATHS[name] || ICON_PATHS.user;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round"
      className={className} style={style}
      dangerouslySetInnerHTML={{ __html: inner }} />
  );
}

/* ---- profile mock data ---- */
const PROFILE = {
  prenom: 'Imrane',
  nom: 'Sani',
  username: 'imrane_ss',
  initials: 'IS',
  completion: 22,
  available: true,
  tarif: '25 000',
  rating: 4.5,
  ratingCount: 12,
  sexe: 'Homme',
  age: '30 ans',
  taille: '1m75',
  poids: '72 kg',
  nationalite: 'Béninois',
  ville: 'Cotonou',
  pays: 'Bénin',
  langues: ['Français', 'Fon', 'Yoruba', 'Anglais'],
};

/* ---- families → sections structure ----
   complete: false marks a section the talent still needs to fill (drives subtle "à compléter" dot) */
const FAMILIES = [
  {
    id: 'profil', label: 'Profil', icon: 'user',
    sections: [
      { id: 'infos', label: 'Infos perso', icon: 'idcard', complete: true },
      { id: 'identite', label: 'Identité', icon: 'fingerprint', complete: true },
      { id: 'physique', label: 'Caractéristiques physiques', short: 'Physique', icon: 'ruler', complete: true },
      { id: 'langues', label: 'Langues parlées', short: 'Langues', icon: 'globe', complete: true },
      { id: 'bio', label: 'Bio & Localisation', short: 'Bio', icon: 'mappin', complete: false },
    ],
  },
  {
    id: 'portfolio', label: 'Portfolio', icon: 'image',
    sections: [
      { id: 'album', label: 'Album photos', short: 'Photos', icon: 'image', complete: true },
      { id: 'videos', label: 'Vidéos', icon: 'video', complete: false },
    ],
  },
  {
    id: 'carriere', label: 'Carrière', icon: 'briefcase',
    sections: [
      { id: 'categorie', label: 'Catégorie & Niche', short: 'Catégorie', icon: 'tag', complete: true },
      { id: 'experiences', label: 'Expériences', icon: 'briefcase', complete: false },
      { id: 'tarif', label: 'Tarif', icon: 'coins', complete: true },
    ],
  },
  {
    id: 'compte', label: 'Compte', icon: 'shield',
    sections: [
      { id: 'securite', label: 'Email & mot de passe', short: 'Sécurité', icon: 'lock', complete: true },
      { id: 'donnees', label: 'Mes données', icon: 'database', complete: true },
    ],
  },
];

function findSection(id) {
  for (const f of FAMILIES) {
    const s = f.sections.find((x) => x.id === id);
    if (s) return { family: f, section: s };
  }
  return null;
}

Object.assign(window, { Icon, PROFILE, FAMILIES, findSection });
