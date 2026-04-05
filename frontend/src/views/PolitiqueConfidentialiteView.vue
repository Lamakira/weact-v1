<script setup lang="ts">
/**
 * Politique de Confidentialité
 * Conformité Livre V du Code du Numérique du Bénin (Loi 2017-20)
 * Couvre les 14 points obligatoires des articles 415-416
 */
import { RouterLink } from 'vue-router'
import { Mail, FileText, ShieldCheck } from 'lucide-vue-next'

const lastUpdate = '4 avril 2026'
</script>

<template>
  <div class="py-1">
    <header class="mb-8">
      <h1 class="text-2xl text-center lg:text-left font-semibold text-gray-900 mb-2">Politique de Confidentialité</h1>
      <p class="text-sm text-gray-500">
        Dernière mise à jour : <span class="font-medium text-gray-700">{{ lastUpdate }}</span> — Conformément au Livre V de la Loi n° 2017-20.
      </p>
    </header>

    <div class="bg-white border border-gray-200 rounded-md p-6 text-sm text-gray-700 leading-relaxed space-y-6">
      <!-- 1. Responsable du traitement (Art. 415 point 1) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">1. Responsable du traitement</h2>
        <p>
          Le responsable du traitement des données à caractère personnel collectées sur la plateforme WEACT est :
        </p>
        <div class="mt-2 p-3 bg-gray-50 rounded border border-gray-100 space-y-1">
          <p><span class="font-medium text-gray-900">WEACT</span></p>
          <p>Siège social : Cotonou, Bénin</p>
          <p>Email : <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a></p>
        </div>
      </div>

      <!-- 2. DPO (Art. 415 point 2) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">2. Délégué à la Protection des Données (DPO)</h2>
        <p>
          Conformément à l'article 430 du Code du Numérique, le Délégué à la Protection des Données de WEACT est joignable à l'adresse :
          <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a>.
        </p>
      </div>

      <!-- 3. Finalités du traitement (Art. 415 point 3) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">3. Finalités du traitement</h2>
        <p>Vos données à caractère personnel sont collectées et traitées pour les finalités suivantes :</p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li>Création et gestion de votre compte utilisateur</li>
          <li>Mise en relation entre Faces et Producteurs (matching de casting)</li>
          <li>Traitement des candidatures et gestion des missions</li>
          <li>Traitement des paiements et gestion du portefeuille</li>
          <li>Messagerie entre utilisateurs</li>
          <li>Amélioration de la plateforme et de l'expérience utilisateur</li>
          <li>Communication transactionnelle (vérification email, notifications de booking, etc.)</li>
          <li>Respect des obligations légales et réglementaires</li>
        </ul>
      </div>

      <!-- 4. Catégories de données (Art. 415 point 4) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">4. Catégories de données collectées</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs mb-2">Données d'identité</p>
            <ul class="text-xs text-gray-600 space-y-0.5">
              <li>Nom, prénom, nom d'utilisateur</li>
              <li>Date de naissance, sexe</li>
              <li>Nationalité, pays de résidence</li>
              <li>Adresse email, numéro WhatsApp</li>
            </ul>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs mb-2">Données professionnelles (Faces)</p>
            <ul class="text-xs text-gray-600 space-y-0.5">
              <li>Photos et vidéos de profil / portfolio</li>
              <li>Caractéristiques physiques (taille, poids)</li>
              <li>Catégories, niches, langues</li>
              <li>Tarifs horaire et journalier</li>
              <li>Expériences professionnelles</li>
            </ul>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs mb-2">Données financières</p>
            <ul class="text-xs text-gray-600 space-y-0.5">
              <li>Informations de paiement (mobile money)</li>
              <li>Historique des transactions</li>
              <li>Données de retrait</li>
            </ul>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs mb-2">Données techniques</p>
            <ul class="text-xs text-gray-600 space-y-0.5">
              <li>Adresse IP</li>
              <li>Données de session (user agent)</li>
              <li>Cookies techniques</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- 5. Base légale (Art. 389) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">5. Base légale du traitement</h2>
        <p>Le traitement de vos données repose sur les bases légales suivantes (article 389 du Code du Numérique) :</p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li><span class="font-medium">Votre consentement</span> : donné lors de l'inscription et pouvant être retiré à tout moment (article 390)</li>
          <li><span class="font-medium">L'exécution du contrat</span> : les données nécessaires à la fourniture des services WEACT</li>
          <li><span class="font-medium">Les obligations légales</span> : conservation des données requise par la loi</li>
          <li><span class="font-medium">L'intérêt légitime</span> : amélioration et sécurisation de la plateforme</li>
        </ul>
      </div>

      <!-- 6. Destinataires (Art. 415 point 5) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">6. Destinataires des données</h2>
        <p>Vos données personnelles peuvent être communiquées aux catégories de destinataires suivantes :</p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li><span class="font-medium">Les autres utilisateurs :</span> les Producteurs ont accès aux profils publics des Faces dans le cadre des missions de casting</li>
          <li><span class="font-medium">Les prestataires techniques :</span> hébergement, services de paiement, services email (sous contrat de sous-traitance conforme à l'article 386)</li>
          <li><span class="font-medium">Les autorités compétentes :</span> sur demande judiciaire ou administrative conforme à la loi</li>
        </ul>
        <p class="mt-2 font-medium">Vos données ne sont jamais vendues à des tiers.</p>
      </div>

      <!-- 7. Droit d'opposition à la prospection (Art. 415 point 7) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">7. Prospection commerciale et droit d'opposition</h2>
        <p>
          Conformément aux articles 332 à 336 et à l'article 440 du Code du Numérique, vous disposez du droit de vous opposer, sur demande et gratuitement, au traitement de vos données à des fins de prospection commerciale.
        </p>
        <p class="mt-2">
          Vous pouvez à tout moment gérer vos préférences de communication depuis les paramètres de votre compte ou en cliquant sur le lien de désinscription présent dans chaque email promotionnel.
        </p>
      </div>

      <!-- 8. Durée de conservation (Art. 415 point 12) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">8. Durée de conservation des données</h2>
        <p>Conformément à l'article 433 du Code du Numérique, vos données sont conservées pour les durées suivantes :</p>
        <div class="mt-2 overflow-x-auto">
          <table class="w-full text-xs border-collapse">
            <thead>
              <tr class="border-b border-gray-200 bg-gray-50">
                <th class="text-left p-2 font-medium text-gray-900">Type de données</th>
                <th class="text-left p-2 font-medium text-gray-900">Durée de conservation</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr>
                <td class="p-2">Données de compte</td>
                <td class="p-2">Durée de vie du compte + 3 ans après suppression</td>
              </tr>
              <tr>
                <td class="p-2">Données de transaction</td>
                <td class="p-2">10 ans (obligation légale, Art. 346)</td>
              </tr>
              <tr>
                <td class="p-2">Données de connexion (logs)</td>
                <td class="p-2">1 an</td>
              </tr>
              <tr>
                <td class="p-2">Cookies</td>
                <td class="p-2">13 mois maximum</td>
              </tr>
              <tr>
                <td class="p-2">Données de prospection</td>
                <td class="p-2">3 ans à compter du dernier contact</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 9. Vos droits (Art. 437-443) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">9. Vos droits</h2>
        <p>Conformément aux articles 437 à 443 du Code du Numérique, vous disposez des droits suivants :</p>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs flex items-center gap-1.5">
              <ShieldCheck :size="12" class="text-[#198496]" /> Droit d'accès (Art. 437)
            </p>
            <p class="text-xs text-gray-600 mt-1">Obtenir une copie de toutes vos données personnelles traitées par WEACT.</p>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs flex items-center gap-1.5">
              <ShieldCheck :size="12" class="text-[#198496]" /> Droit de portabilité (Art. 438)
            </p>
            <p class="text-xs text-gray-600 mt-1">Recevoir vos données dans un format structuré et lisible par machine (JSON).</p>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs flex items-center gap-1.5">
              <ShieldCheck :size="12" class="text-[#198496]" /> Droit de rectification (Art. 441)
            </p>
            <p class="text-xs text-gray-600 mt-1">Corriger, compléter ou mettre à jour vos données personnelles.</p>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs flex items-center gap-1.5">
              <ShieldCheck :size="12" class="text-[#198496]" /> Droit de suppression (Art. 443)
            </p>
            <p class="text-xs text-gray-600 mt-1">Demander l'effacement de vos données (droit à l'oubli numérique).</p>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs flex items-center gap-1.5">
              <ShieldCheck :size="12" class="text-[#198496]" /> Droit d'opposition (Art. 440)
            </p>
            <p class="text-xs text-gray-600 mt-1">S'opposer au traitement de vos données pour des motifs légitimes.</p>
          </div>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="font-medium text-gray-900 text-xs flex items-center gap-1.5">
              <ShieldCheck :size="12" class="text-[#198496]" /> Retrait du consentement (Art. 390)
            </p>
            <p class="text-xs text-gray-600 mt-1">Retirer votre consentement à tout moment, sans affecter la licéité du traitement antérieur.</p>
          </div>
        </div>
        <p class="mt-3">
          Pour exercer ces droits, adressez votre demande par email à
          <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a>
          ou utilisez les fonctionnalités disponibles dans les paramètres de votre compte. WEACT s'engage à répondre à votre demande dans un délai maximum de <span class="font-medium">trente (30) jours</span> (article 420).
        </p>
      </div>

      <!-- 10. Droit de réclamation (Art. 415 point 11) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">10. Droit de réclamation auprès de l'APDP</h2>
        <p>
          Conformément à l'article 448 du Code du Numérique, vous avez le droit d'introduire une réclamation auprès de l'Autorité de Protection des Données Personnelles (APDP) si vous estimez que le traitement de vos données constitue une violation de vos droits :
        </p>
        <div class="mt-2 p-3 bg-gray-50 rounded border border-gray-100">
          <p class="font-medium text-gray-900 text-xs">Autorité de Protection des Données Personnelles (APDP)</p>
          <p class="text-xs text-gray-600">République du Bénin</p>
          <p class="text-xs text-gray-600">Site web : <span class="text-[#198496]">www.apdp.bj</span></p>
        </div>
      </div>

      <!-- 11. Cookies -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">11. Cookies</h2>
        <p>WEACT utilise les catégories de cookies suivantes :</p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li><span class="font-medium">Cookies strictement nécessaires :</span> indispensables au fonctionnement du site (session, authentification). Ils ne nécessitent pas votre consentement.</li>
          <li><span class="font-medium">Cookies analytiques :</span> permettent de mesurer l'audience et d'améliorer le service. Soumis à votre consentement.</li>
        </ul>
        <p class="mt-2">
          Vous pouvez gérer vos préférences en matière de cookies à tout moment via le bandeau de consentement ou les paramètres de votre navigateur. La durée de validité du consentement aux cookies est de 13 mois.
        </p>
      </div>

      <!-- 12. Profilage et décisions automatisées (Art. 415 point 13) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">12. Profilage et décisions automatisées</h2>
        <p>
          Conformément à l'article 401 du Code du Numérique, WEACT n'utilise pas de traitement automatisé de données produisant des effets juridiques ou affectant de manière significative les utilisateurs. Le matching entre Faces et missions repose sur des filtres de recherche volontairement appliqués par les Producteurs (catégorie, localisation, etc.) et non sur un profilage automatisé.
        </p>
      </div>

      <!-- 13. Transferts internationaux (Art. 415 point 14) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">13. Transferts internationaux de données</h2>
        <p>
          Conformément aux articles 391 et 392 du Code du Numérique, vos données peuvent être transférées vers des pays situés en dehors de la CEDEAO uniquement si ces pays assurent un niveau de protection adéquat, ou avec votre consentement explicite, ou sous autorisation de l'APDP.
        </p>
        <p class="mt-2">
          Les prestataires techniques utilisés par WEACT (hébergement, services email) peuvent impliquer des transferts vers des pays tiers. Dans ce cas, WEACT s'assure que des garanties appropriées sont mises en place (clauses contractuelles, certifications de sécurité).
        </p>
      </div>

      <!-- 14. Sécurité (Art. 426) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">14. Sécurité des données</h2>
        <p>
          Conformément à l'article 426 du Code du Numérique, WEACT met en œuvre les mesures techniques et organisationnelles appropriées pour protéger vos données :
        </p>
        <ul class="list-disc pl-5 mt-2 space-y-1">
          <li>Chiffrement des communications (HTTPS/TLS)</li>
          <li>Hashage des mots de passe (bcrypt)</li>
          <li>Contrôle d'accès strict aux données</li>
          <li>Sauvegardes régulières</li>
          <li>Surveillance et détection des incidents de sécurité</li>
        </ul>
        <p class="mt-2">
          En cas de violation de données à caractère personnel, WEACT notifie l'APDP et les personnes concernées sans délai, conformément à l'article 427 du Code du Numérique.
        </p>
      </div>

      <!-- 15. Mineurs (Art. 446) -->
      <div>
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">15. Protection des mineurs</h2>
        <p>
          Conformément à l'article 446 du Code du Numérique, le traitement des données d'un mineur de moins de seize (16) ans n'est licite que si le consentement est donné ou autorisé par le titulaire de la responsabilité parentale. WEACT s'efforce raisonnablement de vérifier cette condition.
        </p>
      </div>

      <!-- 16. Modifications -->
      <div class="pt-4 border-t border-gray-100">
        <h2 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">16. Modifications de la politique</h2>
        <p>
          WEACT se réserve le droit de modifier la présente politique à tout moment. Toute modification sera communiquée aux utilisateurs via la plateforme. La version en vigueur est celle accessible sur le site à la date de consultation.
        </p>
      </div>
    </div>

    <!-- Contact DPO CTA -->
    <div class="mt-6 p-6 bg-[#198496]/5 rounded-lg border border-[#198496]/10 flex flex-col md:flex-row items-center gap-4 justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm text-[#198496]">
          <Mail :size="20" />
        </div>
        <div>
          <p class="text-xs font-semibold text-gray-900">Délégué à la Protection des Données</p>
          <p class="text-[11px] text-gray-500">Pour toute question relative à vos données personnelles</p>
        </div>
      </div>
      <a
        href="mailto:contact@weact.bj"
        class="text-xs font-medium bg-[#198496] text-white px-4 py-2 rounded hover:bg-[#146c7a] transition-colors"
      >
        Contacter le DPO
      </a>
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
        to="/cgu"
        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-[#198496] bg-[#198496]/5 border border-[#198496]/15 rounded-md hover:bg-[#198496]/10 transition-colors"
      >
        <FileText :size="14" />
        Conditions Générales d'Utilisation
      </RouterLink>
    </div>
  </div>
</template>
