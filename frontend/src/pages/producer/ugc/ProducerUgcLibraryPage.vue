<script setup lang="ts">
/**
 * Bibliothèque d'assets Producteur (UGC 4.7, écran « Mes vidéos UGC »).
 * Liste les livrables VALIDÉS (Unboxing + Avis, booking + candidature) avec
 * aperçu inline (AC7) + téléchargement réel (AC2, lien signé attachment).
 * Surface PARALLÈLE à l'inbox de validation 5A : ici une ARCHIVE persistante
 * (l'asset ne disparaît plus une fois validé — AC3), pas une file d'action.
 */
import { onMounted, ref } from 'vue'
import { FolderDown, Download, Play } from 'lucide-vue-next'
import { producerApi } from '@/features/producer/services/producerApi'
import type { DeliverableAssetItem } from '@/components/ugc'

const items = ref<DeliverableAssetItem[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)
const previewId = ref<string | null>(null)

const dateFormatter = new Intl.DateTimeFormat('fr-FR', {
  day: '2-digit',
  month: 'long',
  year: 'numeric',
})

function formatValidatedAt(iso: string | null): string {
  if (!iso) return '—'
  const date = new Date(iso)
  return Number.isNaN(date.getTime()) ? '—' : dateFormatter.format(date)
}

async function fetchAssets(): Promise<void> {
  isLoading.value = true
  error.value = null
  try {
    const response = await producerApi.listValidatedDeliverables()
    items.value = response.data
  } catch {
    error.value = 'Impossible de charger vos vidéos. Réessaie.'
  } finally {
    isLoading.value = false
  }
}

function togglePreview(id: string): void {
  previewId.value = previewId.value === id ? null : id
}

/**
 * Déclenche le téléchargement : le download_url est déjà signé et la route
 * renvoie Content-Disposition: attachment (D-4.7.c). Un simple ancre suffit.
 */
function downloadAsset(item: DeliverableAssetItem): void {
  const a = document.createElement('a')
  a.href = item.download_url
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

onMounted(fetchAssets)
</script>

<template>
  <div class="h-full">
    <!-- En-tête -->
    <div class="mb-5">
      <div class="flex items-center gap-2.5">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[rgba(25,132,150,0.08)]">
          <FolderDown class="h-5 w-5 text-[#198496]" />
        </div>
        <div>
          <h1 class="text-base font-semibold text-gray-900">Mes vidéos UGC</h1>
          <p class="text-xs text-gray-500">Retrouvez et téléchargez vos vidéos validées.</p>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="isLoading"
      class="flex h-64 items-center justify-center text-sm text-gray-500"
      data-testid="ugc-library-loading"
    >
      Chargement de vos vidéos…
    </div>

    <!-- Error -->
    <div
      v-else-if="error && !items.length"
      class="flex h-64 flex-col items-center justify-center gap-2 text-center"
      data-testid="ugc-library-error"
    >
      <p class="text-sm font-medium text-red-600">{{ error }}</p>
      <button
        type="button"
        class="text-xs font-medium text-[#198496] hover:underline"
        @click="fetchAssets"
      >
        Réessayer
      </button>
    </div>

    <!-- Empty -->
    <div
      v-else-if="!items.length"
      class="flex h-64 flex-col items-center justify-center gap-3 text-center"
      data-testid="ugc-library-empty"
    >
      <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[rgba(25,132,150,0.08)]">
        <FolderDown class="h-6 w-6 text-[#198496]" />
      </div>
      <p class="text-sm font-semibold text-gray-900">Aucune vidéo validée pour le moment</p>
      <p class="text-xs text-gray-500">
        Vos vidéos UGC validées apparaîtront ici, prêtes à être téléchargées.
      </p>
    </div>

    <!-- Grille de cartes -->
    <div
      v-else
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      data-testid="ugc-library-grid"
    >
      <div
        v-for="item in items"
        :key="item.id"
        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white"
        data-testid="ugc-library-card"
      >
        <!-- Aperçu : miniature, swap vers <video> au clic (AC7) -->
        <div class="relative aspect-video bg-black">
          <video
            v-if="previewId === item.id"
            :key="item.id"
            :src="item.video_url"
            controls
            autoplay
            class="h-full w-full bg-black object-contain"
            data-testid="ugc-library-video"
          >
            Votre navigateur ne supporte pas la lecture vidéo.
          </video>
          <button
            v-else
            type="button"
            class="group relative block h-full w-full cursor-pointer"
            data-testid="ugc-library-preview"
            @click="togglePreview(item.id)"
          >
            <img
              v-if="item.thumbnail_url"
              :src="item.thumbnail_url"
              alt=""
              class="h-full w-full object-cover"
            />
            <div v-else class="h-full w-full bg-gray-800" aria-hidden="true" />
            <span
              class="absolute inset-0 flex items-center justify-center bg-black/20 transition group-hover:bg-black/30"
            >
              <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow">
                <Play class="h-5 w-5 text-[#198496]" />
              </span>
            </span>
          </button>
        </div>

        <!-- Métadonnées -->
        <div class="flex flex-1 flex-col gap-2 p-4">
          <div class="flex items-center justify-between gap-2">
            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
              {{ item.kind_label }}
            </span>
            <span class="text-[11px] text-gray-400">{{ formatValidatedAt(item.validated_at) }}</span>
          </div>
          <div class="min-w-0">
            <div class="truncate text-sm font-semibold text-gray-900">{{ item.face_name ?? '—' }}</div>
            <div class="truncate text-xs text-gray-500">{{ item.product_name ?? '—' }}</div>
          </div>

          <button
            type="button"
            class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#198496] px-3 py-2 text-xs font-semibold text-white hover:bg-[#147486]"
            data-testid="ugc-library-download"
            @click="downloadAsset(item)"
          >
            <Download class="h-4 w-4" />
            Télécharger
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
