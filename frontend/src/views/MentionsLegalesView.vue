<script setup lang="ts">
/**
 * Mentions Légales - Conformité Art. 328 du Code du Numérique du Bénin (Loi 2017-20)
 */
import { RouterLink } from 'vue-router'
import { Building2, Mail, Phone, MapPin, FileText } from 'lucide-vue-next'
</script>

<template>
  <div class="py-1">
    <header class="mb-8">
      <h1 class="text-2xl text-center lg:text-left font-semibold text-gray-900 mb-2">Mentions Légales</h1>
      <p class="text-sm text-gray-500">
        Conformément à l'article 328 de la Loi n° 2017-20 portant code du numérique en République du Bénin.
      </p>
    </header>

    <div class="space-y-6">
      <!-- 1. Éditeur du site -->
      <section class="bg-white border border-gray-200 rounded-md p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="p-2 rounded-md bg-[#198496]/10 text-[#198496]">
            <Building2 :size="18" />
          </div>
          <h2 class="text-sm font-semibold text-gray-900">1. Éditeur du site</h2>
        </div>
        <div class="text-sm text-gray-700 leading-relaxed space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <p><span class="font-medium text-gray-900">Dénomination sociale :</span> WEACT SAS</p>
              <p><span class="font-medium text-gray-900">Forme juridique :</span> Société par Actions Simplifiée</p>
              <p><span class="font-medium text-gray-900">Capital social :</span> <!-- TODO: à compléter --> [À compléter] FCFA</p>
              <p><span class="font-medium text-gray-900">RCCM :</span> <!-- TODO: à compléter --> [À compléter]</p>
              <p><span class="font-medium text-gray-900">IFU (Identifiant Fiscal Unique) :</span> <!-- TODO: à compléter --> [À compléter]</p>
            </div>
            <div class="space-y-2">
              <p class="flex items-start gap-2">
                <MapPin :size="14" class="text-gray-400 mt-0.5 shrink-0" />
                <span><!-- TODO: à compléter --> [Adresse géographique complète], Cotonou, Bénin</span>
              </p>
              <p class="flex items-center gap-2">
                <Mail :size="14" class="text-gray-400 shrink-0" />
                <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a>
              </p>
              <p class="flex items-center gap-2">
                <Phone :size="14" class="text-gray-400 shrink-0" />
                <span><!-- TODO: à compléter --> [Numéro de téléphone]</span>
              </p>
            </div>
          </div>
          <p><span class="font-medium text-gray-900">Directeur de la publication :</span> <!-- TODO: à compléter --> [Nom du directeur de publication]</p>
        </div>
      </section>

      <!-- 2. Hébergeur -->
      <section class="bg-white border border-gray-200 rounded-md p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">2. Hébergeur du site</h2>
        <div class="text-sm text-gray-700 leading-relaxed space-y-1">
          <!-- TODO: à compléter avec les infos de l'hébergeur réel -->
          <p><span class="font-medium text-gray-900">Raison sociale :</span> [À compléter - ex: DigitalOcean, OVH, etc.]</p>
          <p><span class="font-medium text-gray-900">Adresse :</span> [Adresse de l'hébergeur]</p>
          <p><span class="font-medium text-gray-900">Téléphone :</span> [Téléphone de l'hébergeur]</p>
        </div>
      </section>

      <!-- 3. Activité -->
      <section class="bg-white border border-gray-200 rounded-md p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">3. Nature de l'activité</h2>
        <div class="text-sm text-gray-700 leading-relaxed space-y-3">
          <p>
            WEACT est une plateforme de commerce électronique au sens de l'article 326 de la Loi n° 2017-20, opérant en République du Bénin et à destination des utilisateurs établis sur le territoire béninois.
          </p>
          <p>
            La plateforme fournit un service de mise en relation entre des talents ("Faces") et des recruteurs professionnels ("Producteurs") dans le domaine du casting et de la production audiovisuelle. WEACT agit en qualité d'intermédiaire technique.
          </p>
        </div>
      </section>

      <!-- 4. Propriété intellectuelle -->
      <section class="bg-white border border-gray-200 rounded-md p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">4. Propriété intellectuelle</h2>
        <div class="text-sm text-gray-700 leading-relaxed space-y-3">
          <p>
            L'ensemble des éléments constituant le site WEACT (textes, graphismes, logiciels, photographies, images, vidéos, sons, plans, noms, logos, marques, créations et œuvres protégeables diverses, bases de données, etc.) ainsi que le site lui-même, relèvent de la législation béninoise et internationale sur le droit d'auteur et la propriété intellectuelle.
          </p>
          <p>
            Toute reproduction, représentation, modification, publication, transmission, dénaturation, totale ou partielle du site ou de son contenu, par quelque procédé que ce soit, et sur quelque support que ce soit, est interdite sans l'autorisation écrite préalable de WEACT.
          </p>
        </div>
      </section>

      <!-- 5. Protection des données -->
      <section class="bg-white border border-gray-200 rounded-md p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">5. Protection des données personnelles</h2>
        <div class="text-sm text-gray-700 leading-relaxed space-y-3">
          <p>
            Conformément au Livre V de la Loi n° 2017-20, WEACT s'engage à protéger les données à caractère personnel de ses utilisateurs. Le traitement des données personnelles fait l'objet d'une déclaration auprès de l'Autorité de Protection des Données Personnelles (APDP) de la République du Bénin.
          </p>
          <p>
            Pour en savoir plus sur la collecte, l'utilisation et la protection de vos données, consultez notre
            <RouterLink to="/politique-confidentialite" class="text-[#198496] hover:underline font-medium">Politique de Confidentialité</RouterLink>.
          </p>
          <div class="p-3 bg-gray-50 rounded border border-gray-100">
            <p class="text-xs text-gray-600">
              <span class="font-medium text-gray-900">Délégué à la Protection des Données (DPO) :</span><br />
              Email : <a href="mailto:contact@weact.bj" class="text-[#198496] hover:underline">contact@weact.bj</a>
            </p>
          </div>
        </div>
      </section>

      <!-- 6. Loi applicable -->
      <section class="bg-white border border-gray-200 rounded-md p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">6. Loi applicable et juridiction compétente</h2>
        <div class="text-sm text-gray-700 leading-relaxed space-y-3">
          <p>
            Les présentes mentions légales sont régies par la loi béninoise, et notamment par la Loi n° 2017-20 portant code du numérique en République du Bénin.
          </p>
          <p>
            En cas de litige, et après tentative de résolution amiable, les tribunaux de Cotonou seront seuls compétents.
          </p>
        </div>
      </section>

      <!-- Navigation vers autres pages légales -->
      <div class="flex flex-wrap gap-3 pt-4">
        <RouterLink
          to="/cgu"
          class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-[#198496] bg-[#198496]/5 border border-[#198496]/15 rounded-md hover:bg-[#198496]/10 transition-colors"
        >
          <FileText :size="14" />
          Conditions Générales d'Utilisation
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
  </div>
</template>
