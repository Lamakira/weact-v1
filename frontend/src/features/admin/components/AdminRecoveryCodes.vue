<script setup lang="ts">
/**
 * AdminRecoveryCodes
 * Displays the one-time 2FA recovery codes with copy / download helpers.
 * The codes are shown ONCE: the warning is part of the component on purpose.
 */
import { ref } from 'vue'
import { AlertTriangle, Copy, Download, Check } from 'lucide-vue-next'

const props = defineProps<{
  codes: string[]
}>()

const copied = ref(false)

function asText(): string {
  return props.codes.join('\n')
}

async function copyCodes(): Promise<void> {
  try {
    await navigator.clipboard.writeText(asText())
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    copied.value = false
  }
}

function downloadCodes(): void {
  const content =
    'WEACT Administration - codes de secours (usage unique)\n' +
    'Conservez ce fichier en lieu sûr (gestionnaire de mots de passe).\n\n' +
    asText() +
    '\n'
  const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'weact-admin-codes-de-secours.txt'
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div data-testid="recovery-codes">
    <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 flex items-start gap-2">
      <AlertTriangle class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
      <p class="text-sm text-amber-800" data-testid="recovery-codes-warning">
        Ces codes ne seront <strong>plus jamais affichés</strong>. Chacun ne fonctionne qu'une
        seule fois et permet de vous connecter si vous perdez votre téléphone. Copiez-les ou
        téléchargez-les maintenant et conservez-les dans un endroit sûr.
      </p>
    </div>

    <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm text-gray-900">
      <li
        v-for="code in codes"
        :key="code"
        class="rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-center"
      >
        {{ code }}
      </li>
    </ul>

    <div class="mt-4 flex flex-wrap gap-3">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        data-testid="copy-recovery-codes"
        @click="copyCodes"
      >
        <Check v-if="copied" class="h-4 w-4 text-green-600" />
        <Copy v-else class="h-4 w-4" />
        {{ copied ? 'Copiés' : 'Copier' }}
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        data-testid="download-recovery-codes"
        @click="downloadCodes"
      >
        <Download class="h-4 w-4" />
        Télécharger
      </button>
    </div>
  </div>
</template>
