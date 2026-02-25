<script setup lang="ts">
import { computed } from 'vue'
import { User, Calendar, Languages, MapPin, Flag, Ruler, Palette, FileText } from 'lucide-vue-next'
import type { CandidateFullProfile } from '../types'

const props = defineProps<{
  candidate: CandidateFullProfile
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
    label: 'Age',
    value: props.candidate.age ? `${props.candidate.age} Ans` : null,
    fallback: 'Non renseigné',
    icon: Calendar,
    testId: 'resume-age',
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
    label: 'Langue(s) parlée(s)',
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
</script>

<template>
  <div
    class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden"
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

    <!-- Fields -->
    <div class="divide-y divide-border">
      <div
        v-for="field in fields"
        :key="field.testId"
        class="flex items-center gap-3 px-6 py-3"
        :data-testid="field.testId"
      >
        <component
          :is="field.icon"
          class="h-4 w-4 flex-shrink-0 text-muted-foreground"
          aria-hidden="true"
        />
        <span class="text-sm font-medium text-foreground w-32 sm:w-40 flex-shrink-0">
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

    <!-- Bio Section -->
    <div class="border-t border-border px-6 py-4" data-testid="resume-bio-section">
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
  </div>
</template>
