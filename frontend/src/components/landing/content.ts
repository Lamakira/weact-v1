import {
  UserPlus,
  Mail,
  Clapperboard,
  Wallet,
  Shield,
  CreditCard,
  Award,
  // Producer icons (for future use)
  Search,
  FileText,
  Users,
  BadgeCheck,
  Clock,
  Star,
} from 'lucide-vue-next'
import type { LandingContent, Perspective } from './types'

export const faceContent: LandingContent = {
  howItWorks: {
    title: 'Comment ça marche ?',
    subtitle: '4 étapes simples pour commencer votre carrière',
    steps: [
      {
        number: '01',
        icon: UserPlus,
        title: 'CRÉER VOTRE PROFIL',
        description: 'Ajoutez photos, vidéos, définissez vos tarifs.',
      },
      {
        number: '02',
        icon: Mail,
        title: 'RECEVOIR OU CANDIDATER',
        description: 'Les producteurs vous contactent ou vous postulez aux offres.',
      },
      {
        number: '03',
        icon: Clapperboard,
        title: 'TRAVAILLER',
        description: 'Participez aux shootings et créations de contenu.',
      },
      {
        number: '04',
        icon: Wallet,
        title: 'RECEVOIR VOS GAINS',
        description: 'Recevez votre versement via Mobile Money.',
      },
    ],
  },
  missions: {
    title: 'Missions en cours',
    subtitle: 'Découvre les dernières opportunités disponibles',
    ctaText: 'Voir toutes les missions',
    ctaLink: '/missions',
  },
  whyWeact: {
    title: 'Pourquoi WEACT ?',
    subtitle: 'La plateforme de confiance pour les talents et les producteurs',
    features: [
      {
        icon: Shield,
        title: 'Plateforme sécurisée et professionnelle',
        description:
          'Vos données sont protégées et vos transactions sécurisées sur une plateforme fiable.',
      },
      {
        icon: CreditCard,
        title: 'Paiement sécurisé',
        description:
          'Recevez vos paiements en toute sécurité via Mobile Money après chaque mission.',
      },
      {
        icon: Award,
        title: 'Transactions protégées et sans souci',
        description:
          'Travaillez en toute confiance avec des producteurs vérifiés et des projets de qualité.',
      },
    ],
    certifiedText: 'Plateforme certifiée',
    footerText:
      "Weact est la première plateforme de casting digitale au Bénin, connectant talents et professionnels de l'audiovisuel depuis 2024",
  },
  finalCta: {
    title: 'Prêt à commencer ?',
    subtitle: 'Rejoins maintenant la communauté WeAct et monétise ton talent.',
    buttonText: "S'inscrire comme Face",
    buttonLink: '/register/face',
  },
}

export const producerContent: LandingContent = {
  howItWorks: {
    title: 'Comment ça marche',
    subtitle: '4 étapes simples pour trouver vos talents',
    steps: [
      {
        number: '01',
        icon: FileText,
        title: 'PUBLIEZ VOTRE MISSION',
        description: 'Décrivez votre projet, vos besoins et votre budget.',
      },
      {
        number: '02',
        icon: Search,
        title: 'RECEVEZ DES CANDIDATURES',
        description: 'Les talents qualifiés postulent à votre offre.',
      },
      {
        number: '03',
        icon: Users,
        title: 'SÉLECTIONNEZ VOS TALENTS',
        description: 'Consultez les profils et choisissez les meilleurs candidats.',
      },
      {
        number: '04',
        icon: BadgeCheck,
        title: 'COLLABOREZ EN CONFIANCE',
        description: 'Travaillez avec des talents vérifiés et évalués.',
      },
    ],
  },
  missions: {
    title: 'Gérez vos missions facilement',
    subtitle: 'Publiez, suivez et gérez toutes vos missions en un seul endroit',
    ctaText: 'Publier une mission',
    ctaLink: '/register/producer',
  },
  whyWeact: {
    title: 'Pourquoi WEACT',
    subtitle: 'La plateforme de confiance pour recruter vos talents',
    features: [
      {
        icon: Users,
        title: 'Accès à un vivier de talents',
        description:
          'Trouvez rapidement les profils qui correspondent à vos besoins parmi des centaines de talents.',
      },
      {
        icon: Clock,
        title: 'Gain de temps',
        description:
          'Publiez une mission et recevez des candidatures qualifiées en quelques heures.',
      },
      {
        icon: Star,
        title: 'Talents évalués',
        description:
          'Consultez les avis et notes des autres producteurs pour faire le bon choix.',
      },
    ],
    certifiedText: 'Plateforme certifiée',
    footerText:
      "Weact est la première plateforme de casting digitale au Bénin, connectant talents et professionnels de l'audiovisuel depuis 2024",
  },
  finalCta: {
    title: 'Prêt à trouver vos talents ?',
    subtitle: 'Rejoignez WEACT et accédez à un vivier de talents qualifiés.',
    buttonText: "S'inscrire comme Producteur",
    buttonLink: '/register/producer',
  },
}

export function getContent(perspective: Perspective): LandingContent {
  return perspective === 'face' ? faceContent : producerContent
}
