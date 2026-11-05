<script setup lang="ts">
import { computed } from 'vue'
import { User, Calendar, Languages, MapPin, Flag, Ruler, Palette, FileText, Wallet } from 'lucide-vue-next'
import type { CandidateFullProfile } from '../types'

const props = defineProps<{
  candidate: CandidateFullProfile
  tarifHoraire?: string | null
  tarifJournalier?: string | null
}>()

const languesDisplay = computed((): string | null => {
  const langues = props.candidate.langues
  if (!langues || langues.length === 0) return null
  return langues.join(', ')
})

const talentsDisplay = computed((): string | null => {
  const categories = props.candidate.categories
  if (!categories || categories.length === 0) return null
  return categories.map((c) => c.label).join(', ')
})

const tailleDisplay = computed((): string | null => {
  const taille = props.candidate.taille
  if (!taille) return null
  return `${taille} cm`
})

const hasBio = computed((): boolean => !!props.candidate.bio)

interface ResumeField {
  label: string
  value: string | null
  fallback: string
  icon: typeof User
  testId: string
  hidden?: boolean
}

const fields = computed((): ResumeField[] => [
  {
    label: 'Sexe',
    value: props.candidate.sexe_label,
    fallback: 'Non renseigné',
    icon: User,
    testId: 'resume-sexe',
  },
  {
    label: 'Âge',
    value: props.candidate.age ? `${props.candidate.age} ans` : null,
    fallback: 'Non renseigné',
    icon: Calendar,
    testId: 'resume-age',
    hidden: props.candidate.age == null,
  },
  {
    label: 'Taille',
    value: tailleDisplay.value,
    fallback: 'Non renseigné',
    icon: Ruler,
    testId: 'resume-taille',
  },
  {
    label: 'Nationalité',
    value: props.candidate.nationalite,
    fallback: 'Non renseigné',
    icon: Flag,
    testId: 'resume-nationalite',
  },
  {
    label: 'Langues',
    value: languesDisplay.value,
    fallback: 'Non renseigné',
    icon: Languages,
    testId: 'resume-langues',
  },
  {
    label: 'Pays',
    value: props.candidate.pays,
    fallback: 'Non renseigné',
    icon: MapPin,
    testId: 'resume-pays',
  },
  {
    label: 'Talents',
    value: talentsDisplay.value,
    fallback: 'Non renseigné',
    icon: Palette,
    testId: 'resume-talents',
  },
])

const visibleFields = computed((): ResumeField[] => fields.value.filter((field) => !field.hidden))
const hasTarifs = computed((): boolean => !!props.tarifHoraire || !!props.tarifJournalier)

// Nombre impair de champs : le dernier prend toute la largeur pour ne pas laisser de case vide.
function spansFullWidth(index: number): boolean {
  return visibleFields.value.length % 2 === 1 && index === visibleFields.value.length - 1
}
</script>

<template>
  <div
    class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden lg:flex lg:flex-col"
    data-testid="candidate-resume-summary"
  >
    <!-- Header -->
    <div class="bg-primary/5 px-6 py-4 border-b border-border">
      <h2
        class="text-sm font-semibold uppercase tracking-wider text-primary"
        data-testid="resume-title"
      >
        Résumé
      </h2>
    </div>

    <!-- Fields : deux colonnes dès sm -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex-1 lg:auto-rows-fr" data-testid="resume-fields">
      <div
        v-for="(field, index) in visibleFields"
        :key="field.testId"
        class="flex items-center gap-3 border-b border-border px-6 py-3 sm:even:border-l"
        :class="{ 'sm:col-span-2': spansFullWidth(index) }"
        :data-testid="field.testId"
      >
        <component
          :is="field.icon"
          class="h-4 w-4 flex-shrink-0 text-muted-foreground"
          aria-hidden="true"
        />
        <span class="text-sm font-medium text-foreground w-24 flex-shrink-0">
          {{ field.label }}
        </span>
        <span
          class="text-sm min-w-0"
          :class="field.value ? 'text-foreground' : 'text-muted-foreground/70 italic'"
        >
          {{ field.value ?? field.fallback }}
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2">
      <!-- Bio Section -->
      <div class="px-6 py-4" :class="{ 'sm:col-span-2': !hasTarifs }" data-testid="resume-bio-section">
        <div class="flex items-center gap-2 mb-2">
          <FileText class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
          <span class="text-sm font-medium text-foreground">Biographie</span>
        </div>
        <p
          v-if="hasBio"
          class="text-sm leading-relaxed text-muted-foreground whitespace-pre-wrap pl-6"
          data-testid="resume-bio-text"
        >
          {{ candidate.bio }}
        </p>
        <p
          v-else
          class="text-sm italic text-muted-foreground/70 pl-6"
          data-testid="resume-bio-empty"
        >
          Non renseigné
        </p>
      </div>

      <!-- Tarifs Section -->
      <div
        v-if="hasTarifs"
        class="border-t border-border px-6 py-4 sm:border-t-0 sm:border-l"
        data-testid="resume-tarifs-section"
      >
        <div class="flex items-center gap-2 mb-2">
          <Wallet class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
          <span class="text-sm font-medium text-foreground">Tarifs</span>
        </div>
        <div class="flex flex-wrap gap-x-5 gap-y-2 pl-6">
          <div v-if="tarifJournalier" data-testid="tarif-journalier">
            <p class="text-xs text-muted-foreground">Journée complète (8h)</p>
            <p class="text-lg font-bold text-[#198496]">{{ tarifJournalier }}</p>
          </div>
          <div v-if="tarifHoraire" data-testid="tarif-horaire">
            <p class="text-xs text-muted-foreground">Demi-journée (4h)</p>
            <p class="text-lg font-bold text-[#198496]">{{ tarifHoraire }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
