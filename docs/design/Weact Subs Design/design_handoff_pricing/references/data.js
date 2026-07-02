// Subscription tiers data for WeAct Faces
window.WEACT_TIERS = [
  {
    key: 'decouverte',
    name: 'Découverte',
    tagline: 'Pour tester la plateforme',
    price: 0,
    priceLabel: '0',
    period: 'Gratuit',
    description: "Crée ton profil et explore l'écosystème WeAct sans engagement.",
    cta: "S'inscrire",
    badge: null,
    features: [
      { text: 'Photo de profil', included: true },
      { text: '1 photo dans la galerie', included: true },
      { text: 'Profil visible (rang standard)', included: true },
      { text: "Accès aux missions UGC", included: false },
      { text: 'Vidéo de présentation', included: false },
      { text: 'Mise en avant', included: false },
    ],
  },
  {
    key: 'starter',
    name: 'Starter',
    tagline: 'Décroche tes premiers contrats UGC',
    price: 12000,
    priceLabel: '12 000',
    period: 'FCFA / an',
    description: "Pour les talents prêts à se lancer dans les missions UGC rémunérées.",
    cta: "Choisir Starter",
    badge: null,
    features: [
      { text: 'Photo de profil', included: true },
      { text: '2 photos dans la galerie', included: true },
      { text: '1 vidéo de présentation', included: true },
      { text: 'Accès complet au module UGC', included: true },
      { text: 'Mise en avant Boostée', included: true },
      { text: 'Vidéo Acting / démo', included: false },
    ],
  },
  {
    key: 'pro',
    name: 'Pro',
    tagline: 'Acting + UGC, le combo sérieux',
    price: 25000,
    priceLabel: '25 000',
    period: 'FCFA / an',
    description: "Pour les talents qui font du cinéma, de l'acting et de l'UGC.",
    cta: "Choisir Pro",
    badge: 'Populaire',
    features: [
      { text: 'Photo de profil', included: true },
      { text: '4 photos dans la galerie', included: true },
      { text: '1 vidéo de présentation', included: true },
      { text: '1 vidéo démo Acting', included: true },
      { text: 'Accès complet au module UGC', included: true },
      { text: 'Mise en avant Premium', included: true },
    ],
  },
  {
    key: 'elite',
    name: 'Élite',
    tagline: "L'offre VIP des tops profils",
    price: 40000,
    priceLabel: '40 000',
    period: 'FCFA / an',
    description: "Pour les professionnels qui veulent une visibilité maximale et une commission réduite.",
    cta: "Passer Élite",
    badge: 'VIP',
    features: [
      { text: 'Portfolio complet : 6 photos', included: true },
      { text: '1 vidéo de présentation', included: true },
      { text: '2 vidéos Acting', included: true },
      { text: '1 vidéo modèle UGC', included: true },
      { text: 'Commission réduite à 5% (au lieu de 10%)', included: true, highlight: true },
      { text: 'Badge "VIP / Elite" sur le profil', included: true, highlight: true },
      { text: 'Mise en avant Prioritaire Absolue', included: true, highlight: true },
      { text: 'Assistance prioritaire WeAct', included: true },
    ],
  },
];

// Comparison table data
window.WEACT_COMPARE = {
  groups: [
    {
      label: 'Portfolio',
      rows: [
        { name: 'Photo de profil', decouverte: true, starter: true, pro: true, elite: true },
        { name: 'Photos dans la galerie', decouverte: '1', starter: '2', pro: '4', elite: '6' },
        { name: 'Vidéo de présentation', decouverte: false, starter: true, pro: true, elite: true },
        { name: 'Vidéos Acting / démo', decouverte: false, starter: false, pro: '1', elite: '2' },
        { name: 'Vidéo modèle UGC', decouverte: false, starter: false, pro: false, elite: true },
      ],
    },
    {
      label: 'Missions & revenus',
      rows: [
        { name: 'Accès aux missions UGC', decouverte: false, starter: true, pro: true, elite: true },
        { name: 'Dotation produit', decouverte: false, starter: true, pro: true, elite: true },
        { name: 'Missions rémunérées', decouverte: false, starter: true, pro: true, elite: true },
        { name: 'Commission plateforme', decouverte: '—', starter: '10 %', pro: '10 %', elite: '5 %' },
      ],
    },
    {
      label: 'Visibilité',
      rows: [
        { name: 'Mise en avant', decouverte: 'Standard', starter: 'Boostée', pro: 'Premium', elite: 'Prioritaire' },
        { name: 'Rang dans la recherche', decouverte: '4ᵉ', starter: '3ᵉ', pro: '2ᵉ', elite: '1ᵉʳ' },
        { name: 'Badge VIP / Élite', decouverte: false, starter: false, pro: false, elite: true },
      ],
    },
    {
      label: 'Support',
      rows: [
        { name: 'Centre d\'aide', decouverte: true, starter: true, pro: true, elite: true },
        { name: 'Assistance prioritaire WeAct', decouverte: false, starter: false, pro: false, elite: true },
      ],
    },
  ],
};
