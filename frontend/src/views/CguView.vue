<script setup lang="ts">
/**
 * Conditions Générales d'Utilisation (CGU)
 * Conformité Livre IV du Code du Numérique du Bénin (Loi 2017-20)
 * Inclut les obligations Art. 338-340 (information préalable, caractéristiques, prix)
 */
import { RouterLink } from 'vue-router'
import { FileText } from 'lucide-vue-next'

const lastUpdate = '4 avril 2026'
</script>

<template>
  <div class="py-1">
    <header class="mb-8">
      <h1 class="text-2xl text-center lg:text-left font-semibold text-gray-900 mb-2">Conditions Générales d'Utilisation</h1>
      <p class="text-sm text-gray-500">
        Dernière mise à jour : <span class="font-medium text-gray-700">{{ lastUpdate }}</span>
      </p>
    </header>

    <div class="bg-white border border-gray-200 rounded-md p-6 text-sm text-gray-700 leading-relaxed space-y-6">
      <!-- 1. Objet -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 1 - Objet</h2>
        <p>
          Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») régissent l'accès et l'utilisation de la plateforme WEACT, accessible à l'adresse weact.bj, conformément aux dispositions de la Loi n° 2017-20 portant code du numérique en République du Bénin.
        </p>
        <p class="mt-2">
          WEACT est une marketplace de casting opérant au Bénin, mettant en relation des talents (« Faces ») avec des recruteurs professionnels (« Producteurs ») pour des projets audiovisuels (films, séries, publicités, clips musicaux, etc.).
        </p>
      </div>

      <!-- 2. Acceptation -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 2 - Acceptation des CGU</h2>
        <p>
          L'inscription sur la plateforme WEACT implique l'acceptation pleine et entière des présentes CGU. Conformément à l'article 343 du Code du Numérique, l'utilisateur a la possibilité de vérifier le détail de ses engagements et d'y apporter des corrections avant de confirmer son acceptation.
        </p>
        <p class="mt-2">
          WEACT se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification par notification sur la plateforme. L'utilisation continue de la plateforme après notification vaut acceptation des nouvelles conditions.
        </p>
      </div>

      <!-- 3. Inscription et Compte -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 3 - Inscription et compte utilisateur</h2>
        <p>
          L'accès aux services de WEACT nécessite la création d'un compte. L'utilisateur s'engage à fournir des informations exactes, complètes et à jour, conformément à l'article 383 du Code du Numérique relatif à la qualité des données personnelles.
        </p>
        <p class="mt-2">
          L'utilisateur est responsable de la confidentialité de ses identifiants de connexion et de toute activité réalisée depuis son compte.
        </p>
        <!-- Étapes de conclusion du contrat (Art. 338 point 1) -->
        <div class="mt-3 p-4 bg-gray-50 rounded border border-gray-100">
          <p class="font-medium text-gray-900 text-xs mb-2">Étapes d'inscription :</p>
          <ol class="list-decimal pl-4 space-y-1 text-xs text-gray-600">
            <li>Remplir le formulaire d'inscription (Face ou Producteur)</li>
            <li>Accepter les présentes CGU et la Politique de Confidentialité</li>
            <li>Valider son adresse email via le lien de vérification reçu</li>
            <li>Compléter son profil pour accéder à l'ensemble des services</li>
          </ol>
        </div>
      </div>

      <!-- 4. Description des services -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 4 - Description des services</h2>
        <p>WEACT propose les services suivants :</p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li><span class="font-medium">Pour les Faces :</span> création de profil, visibilité auprès des producteurs, candidature aux missions de casting, messagerie, gestion des bookings et portefeuille de gains.</li>
          <li><span class="font-medium">Pour les Producteurs :</span> publication de missions de casting, recherche et sélection de talents, messagerie, gestion des bookings et paiements sécurisés.</li>
        </ul>
        <p class="mt-2">
          La langue de conclusion des contrats sur la plateforme est le <span class="font-medium">français</span>.
        </p>
      </div>

      <!-- 5. Obligations des Faces -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 5 - Obligations des Faces</h2>
        <div class="p-4 bg-gray-50 rounded border border-gray-100">
          <ul class="list-disc pl-4 space-y-1 text-xs">
            <li>Fournir des photos et informations authentiques et à jour</li>
            <li>Respecter les engagements pris dans le cadre des missions acceptées</li>
            <li>Faire preuve de ponctualité et de professionnalisme</li>
            <li>Respecter l'image de marque de WEACT et des Producteurs</li>
            <li>Ne pas utiliser la plateforme à des fins illégales ou contraires aux bonnes mœurs</li>
            <li>Ne pas contourner la plateforme pour des transactions directes avec les Producteurs</li>
          </ul>
        </div>
      </div>

      <!-- 6. Obligations des Producteurs -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 6 - Obligations des Producteurs</h2>
        <div class="p-4 bg-gray-50 rounded border border-gray-100">
          <ul class="list-disc pl-4 space-y-1 text-xs">
            <li>Fournir une description honnête et complète des missions publiées</li>
            <li>Respecter le droit du travail béninois dans toutes les collaborations</li>
            <li>Effectuer les paiements via la plateforme WEACT exclusivement</li>
            <li>Ne pas solliciter directement les Faces en dehors de la plateforme</li>
            <li>Garantir la sécurité et le bien-être des Faces lors des missions</li>
          </ul>
        </div>
      </div>

      <!-- 7. Tarification et paiements -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 7 - Tarification et paiements</h2>
        <p>
          Conformément à l'article 340 du Code du Numérique, les informations sur les prix sont présentées de façon claire et non équivoque :
        </p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li>Les prix des missions sont fixés par les Producteurs et affichés en <span class="font-medium">Francs CFA (FCFA)</span>, toutes taxes comprises.</li>
          <li>WEACT prélève une commission sur chaque transaction réalisée via la plateforme. Le montant de cette commission est clairement indiqué avant la validation du paiement.</li>
          <li>Les paiements sont effectués via les moyens de paiement proposés sur la plateforme (mobile money, virement bancaire).</li>
          <li>Les Faces peuvent demander le retrait de leurs gains depuis leur portefeuille, sous réserve du respect des conditions de retrait minimum.</li>
        </ul>
      </div>

      <!-- 8. Droit de rétractation -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 8 - Droit de rétractation</h2>
        <p>
          Conformément aux articles 347 à 355 du Code du Numérique, le consommateur dispose d'un délai de <span class="font-medium">quinze (15) jours ouvrables</span> à compter du lendemain de la passation de la commande pour exercer son droit de rétractation, sans justification et sans frais.
        </p>
        <p class="mt-2">
          Ce droit de rétractation ne s'applique pas aux services qui ont été intégralement fournis (article 354), notamment lorsqu'une mission de casting a été entièrement réalisée.
        </p>
        <p class="mt-2">
          Pour exercer votre droit de rétractation, adressez votre demande par email à
          <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a>
          ou par courrier à l'adresse du siège social. Le remboursement sera effectué dans un délai maximum de trente (30) jours ouvrables.
        </p>
      </div>

      <!-- 9. Propriété intellectuelle -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 9 - Propriété intellectuelle</h2>
        <p>
          Tous les éléments du site (logos, interfaces, algorithmes, code source) sont la propriété exclusive de WEACT et sont protégés par les lois béninoises et internationales relatives à la propriété intellectuelle.
        </p>
        <p class="mt-2">
          Les Faces concèdent à WEACT un droit d'utilisation non exclusif de leur image (photos, vidéos) dans le cadre strict de la mise en relation sur la plateforme et de la promotion du service WEACT.
        </p>
      </div>

      <!-- 10. Protection des données personnelles -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 10 - Protection des données personnelles</h2>
        <p>
          WEACT s'engage à protéger les données à caractère personnel de ses utilisateurs conformément au Livre V de la Loi n° 2017-20. Les détails relatifs à la collecte, au traitement et à la protection de vos données sont disponibles dans notre
          <RouterLink to="/politique-confidentialite" class="text-[#198496] hover:underline font-medium">Politique de Confidentialité</RouterLink>.
        </p>
      </div>

      <!-- 11. Responsabilité -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 11 - Responsabilité</h2>
        <p>
          Conformément à l'article 329 du Code du Numérique, WEACT est responsable de plein droit de la bonne exécution des obligations résultant de l'utilisation de la plateforme, que ces obligations soient exécutées par elle-même ou par des tiers.
        </p>
        <p class="mt-2">
          WEACT agit en tant qu'intermédiaire technique et n'est pas partie aux contrats conclus entre Faces et Producteurs. Conformément à l'article 497 du Code du Numérique, la responsabilité de WEACT ne peut être engagée pour les contenus publiés par les utilisateurs, sauf si elle en avait effectivement connaissance et n'a pas agi promptement pour les retirer.
        </p>
        <p class="mt-2">
          Conformément à l'article 377, WEACT exerce une obligation générale de vigilance sur les contenus et activités de ses utilisateurs et signale sans délai toute activité illicite aux autorités compétentes.
        </p>
      </div>

      <!-- 12. Signalement de contenu -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 12 - Signalement de contenu illicite</h2>
        <p>
          Conformément à l'article 498 du Code du Numérique, tout utilisateur peut signaler un contenu qu'il estime illicite via le mécanisme de signalement intégré à la plateforme. WEACT s'engage à traiter promptement tout signalement conforme aux exigences légales.
        </p>
      </div>

      <!-- 13. Résiliation -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 13 - Résiliation et suppression de compte</h2>
        <p>
          L'utilisateur peut à tout moment demander la suppression de son compte depuis les paramètres de son profil ou par email à <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a>.
        </p>
        <p class="mt-2">
          WEACT se réserve le droit de suspendre ou supprimer tout compte en cas de manquement grave aux présentes CGU, sans préavis ni indemnité, conformément à la législation en vigueur.
        </p>
        <p class="mt-2">
          En cas de suppression de compte, les données personnelles seront traitées conformément à notre Politique de Confidentialité et aux obligations légales de conservation.
        </p>
      </div>

      <!-- 14. Réclamations -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 14 - Réclamations et règlement des litiges</h2>
        <p>
          Conformément à l'article 338 du Code du Numérique, tout utilisateur peut adresser une réclamation :
        </p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li>Par email : <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a></li>
          <li>Par courrier à l'adresse du siège social</li>
        </ul>
        <p class="mt-2">
          WEACT s'engage à accuser réception de toute réclamation et à y répondre dans un délai raisonnable. En cas de litige non résolu, les parties peuvent recourir à la médiation avant toute action judiciaire.
        </p>
      </div>

      <!-- 15. Loi applicable -->
      <div class="pt-4 border-t border-gray-100">
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">Article 15 - Loi applicable</h2>
        <p class="italic text-gray-600">
          Les présentes CGU sont régies par le droit en vigueur en République du Bénin, et notamment la Loi n° 2017-20 portant code du numérique. En cas de litige, et après tentative de résolution amiable, les tribunaux de Cotonou seront seuls compétents.
        </p>
      </div>
    </div>

    <!-- Navigation vers autres pages légales -->
    <div class="flex flex-wrap gap-3 pt-6">
      <RouterLink
        to="/mentions-legales"
        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-[#198496] bg-[#198496]/5 border border-[#198496]/15 rounded-md hover:bg-[#198496]/10 transition-colors"
      >
        <FileText :size="14" />
        Mentions Légales
      </RouterLink>
      <RouterLink
        to="/politique-confidentialite"
        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-[#198496] bg-[#198496]/5 border border-[#198496]/15 rounded-md hover:bg-[#198496]/10 transition-colors"
      >
        <FileText :size="14" />
        Politique de Confidentialité
      </RouterLink>
    </div>
  </div>
</template>
