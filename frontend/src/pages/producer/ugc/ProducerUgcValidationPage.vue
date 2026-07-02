<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { BadgeCheck, Check } from 'lucide-vue-next'
import { ChronoBadge } from '@/components/ugc'
import { useUgcDeliverableReview } from '@/composables/useUgcDeliverableReview'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import { useToast } from '@/composables/useToast'

const { items, isLoading, isSubmitting, error, errorCode, fetchPending, validate, reject, requestRetouche } =
  useUgcDeliverableReview()
const toast = useToast()

// Re-synchronise le badge de nav « X à valider » avec l'inbox (0 GET en plus) :
// fetchPending réassigne `items` au mount/retry/après action ⇒ le watch (lazy)
// repropage la taille de la file dans le store partagé (D-7.2.d).
const ugcValidationCountStore = useUgcValidationCountStore()
watch(items, () => ugcValidationCountStore.setCount(items.value.length))

const selectedId = ref<string | null>(null)
const reviewNote = ref('')
const noteError = ref<string | null>(null)

const selected = computed(() => items.value.find((d) => d.id === selectedId.value) ?? null)

// Checklist de conformité STATIQUE (D-4.4.h) : aide-mémoire visuel neutre, non
// interactive/persistée. Le Producteur fait un jugement holistique.
const CHECKLIST = ['Format vertical 9:16', 'Durée ≤ 60s', 'Hashtags présents', 'Mention WeAct visible']

function selectFirst(): void {
  selectedId.value = items.value[0]?.id ?? null
  reviewNote.value = ''
  noteError.value = null
}

onMounted(async () => {
  await fetchPending()
  selectFirst()
})

function selectItem(id: string): void {
  selectedId.value = id
  reviewNote.value = ''
  noteError.value = null
}

async function afterAction(deliverable: unknown, okMessage: string): Promise<void> {
  if (deliverable) {
    toast.success(okMessage)
    await fetchPending()
    selectFirst()
    return
  }
  if (errorCode.value === 'INVALID_STATUS') {
    toast.error(error.value || 'Ce livrable n’est plus en attente de validation.')
    await fetchPending()
    selectFirst()
    return
  }
  toast.error(error.value || 'Action impossible. Réessayez.')
}

async function onValidate(): Promise<void> {
  if (!selected.value || isSubmitting.value) return
  await afterAction(await validate(selected.value.id), 'Livrable validé')
}

function requireNote(): boolean {
  if (reviewNote.value.trim().length < 5) {
    noteError.value = 'Indique un motif (5 caractères min.).'
    return false
  }
  noteError.value = null
  return true
}

async function onReject(): Promise<void> {
  if (!selected.value || isSubmitting.value || !requireNote()) return
  await afterAction(await reject(selected.value.id, reviewNote.value.trim()), 'Livrable refusé')
}

async function onRetouche(): Promise<void> {
  if (!selected.value || isSubmitting.value || !requireNote()) return
  await afterAction(await requestRetouche(selected.value.id, reviewNote.value.trim()), 'Retouche demandée')
}
</script>

<template>
  <div class="h-full">
    <!-- Loading -->
    <div
      v-if="isLoading"
      class="flex h-64 items-center justify-center text-sm text-gray-500"
      data-testid="ugc-review-loading"
    >
      Chargement des livrables…
    </div>

    <!-- Error (fetch en échec, rien à afficher) -->
    <div
      v-else-if="error && !items.length"
      class="flex h-64 flex-col items-center justify-center gap-2 text-center"
      data-testid="ugc-review-error"
    >
      <p class="text-sm font-medium text-red-600">{{ error }}</p>
      <button
        type="button"
        class="text-xs font-medium text-[#198496] hover:underline"
        @click="fetchPending"
      >
        Réessayer
      </button>
    </div>

    <!-- Empty -->
    <div
      v-else-if="!items.length"
      class="flex h-64 flex-col items-center justify-center gap-3 text-center"
      data-testid="ugc-review-empty"
    >
      <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[rgba(25,132,150,0.08)]">
        <BadgeCheck class="h-6 w-6 text-[#198496]" />
      </div>
      <p class="text-sm font-semibold text-gray-900">Aucun livrable en attente</p>
      <p class="text-xs text-gray-500">Les vidéos déposées par les Faces apparaîtront ici pour validation.</p>
    </div>

    <!-- 2 panes (design 5A) -->
    <div v-else class="grid h-full grid-cols-[320px_1fr] gap-5">
      <!-- Pane gauche : liste -->
      <div
        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white"
        data-testid="ugc-review-list"
      >
        <div class="border-b border-gray-100 p-4">
          <div class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">À valider</div>
          <div class="flex items-baseline justify-between">
            <h2 class="text-sm font-semibold text-gray-900">
              {{ items.length }} livrable{{ items.length > 1 ? 's' : '' }} en attente
            </h2>
            <span class="text-[11px] text-gray-500">SLA : 48 h</span>
          </div>
        </div>
        <div class="flex-1 divide-y divide-gray-100 overflow-auto">
          <button
            v-for="d in items"
            :key="d.id"
            type="button"
            class="block w-full cursor-pointer border-l-2 p-3 text-left"
            :class="
              d.id === selectedId
                ? 'border-[#198496] bg-[rgba(25,132,150,0.04)]'
                : 'border-transparent hover:bg-gray-50'
            "
            data-testid="ugc-review-item"
            @click="selectItem(d.id)"
          >
            <div class="flex items-center gap-2.5">
              <img
                v-if="d.thumbnail_url"
                :src="d.thumbnail_url"
                alt=""
                class="h-12 w-12 shrink-0 rounded-md object-cover"
              />
              <div v-else class="h-12 w-12 shrink-0 rounded-md bg-gray-100" aria-hidden="true" />
              <div class="min-w-0 flex-1">
                <div class="truncate text-xs font-semibold text-gray-900">{{ d.face_name ?? '—' }}</div>
                <div class="truncate text-[10px] text-gray-500">{{ d.product_name ?? '—' }}</div>
                <div class="mt-1 flex items-center gap-1.5">
                  <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                    {{ d.kind_label }}
                  </span>
                  <ChronoBadge :start-at="d.submitted_at" :deadline-at="d.review_due_at" />
                </div>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Pane droite : preview + checklist + actions -->
      <div
        v-if="selected"
        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white"
        data-testid="ugc-review-detail"
      >
        <div class="flex items-center justify-between border-b border-gray-100 p-4">
          <div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-[#198496]">
              Livrable · {{ selected.kind_label }}
            </div>
            <div class="mt-0.5 text-sm font-semibold text-gray-900">
              {{ selected.face_name ?? '—' }} · {{ selected.product_name ?? '—' }}
            </div>
          </div>
          <ChronoBadge size="lg" :start-at="selected.submitted_at" :deadline-at="selected.review_due_at" />
        </div>

        <div class="grid flex-1 grid-cols-[1fr_300px] overflow-hidden">
          <!-- Lecteur vidéo -->
          <div class="flex items-center justify-center bg-black p-4">
            <video
              :key="selected.id"
              :src="selected.video_url"
              controls
              class="max-h-full w-auto rounded-md bg-black"
              data-testid="ugc-review-video"
            >
              Votre navigateur ne supporte pas la lecture vidéo.
            </video>
          </div>

          <!-- Panneau latéral -->
          <div class="overflow-auto border-l border-gray-100 p-5">
            <div class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">Brief original</div>
            <p class="text-xs leading-relaxed text-gray-700">
              Déballage 30-60 s · format vertical · mise en avant de la texture et de l’ambiance du produit.
            </p>

            <div class="mb-2 mt-5 text-[10px] font-bold uppercase tracking-widest text-gray-400">
              Points de conformité
            </div>
            <ul class="space-y-2">
              <li v-for="item in CHECKLIST" :key="item" class="flex items-center gap-2 text-xs text-gray-700">
                <span class="flex h-4 w-4 items-center justify-center rounded bg-[rgba(25,132,150,0.12)]">
                  <Check class="h-2.5 w-2.5 text-[#198496]" />
                </span>
                {{ item }}
              </li>
            </ul>

            <div class="mt-5">
              <label class="mb-1.5 block text-xs font-medium text-gray-700" for="ugc-review-note">
                Note / demande de retouche
              </label>
              <textarea
                id="ugc-review-note"
                v-model="reviewNote"
                rows="3"
                placeholder="Optionnel pour valider · requis pour rejeter/retoucher"
                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-xs focus:border-[#198496] focus:outline-none focus:ring-2 focus:ring-[#198496]/20"
                data-testid="ugc-review-note"
              />
              <p v-if="noteError" class="mt-1 text-[11px] text-red-600" data-testid="ugc-review-note-error">
                {{ noteError }}
              </p>
            </div>

            <div class="mt-5 space-y-2">
              <button
                type="button"
                class="w-full rounded-md bg-[#198496] px-3 py-2 text-xs font-semibold text-white hover:bg-[#147486] disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isSubmitting"
                data-testid="ugc-review-validate"
                @click="onValidate"
              >
                Valider
              </button>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="isSubmitting"
                  data-testid="ugc-review-retouche"
                  @click="onRetouche"
                >
                  Demander retouche
                </button>
                <button
                  type="button"
                  class="w-full rounded-md border border-red-300 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="isSubmitting"
                  data-testid="ugc-review-reject"
                  @click="onReject"
                >
                  Rejeter
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
