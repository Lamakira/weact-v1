<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Mail, Phone, MapPin, Share2, Send, CheckCircle2, AlertCircle, Loader2 } from 'lucide-vue-next'
import { submitContactForm } from '@/features/public/services/contactApi'
import { formatApiError } from '@/services/errorFormatter'

interface ContactForm {
  name: string
  email: string
  subject: string
  message: string
}

const form = reactive<ContactForm>({
  name: '',
  email: '',
  subject: '',
  message: '',
})

const isSubmitting = ref(false)
const submitStatus = ref<'idle' | 'success' | 'error'>('idle')
const errorMessage = ref('')

const handleSubmit = async () => {
  isSubmitting.value = true
  submitStatus.value = 'idle'
  errorMessage.value = ''

  try {
    await submitContactForm({
      name: form.name,
      email: form.email,
      subject: form.subject,
      message: form.message,
    })

    submitStatus.value = 'success'
    form.name = ''
    form.email = ''
    form.subject = ''
    form.message = ''
  } catch (err: unknown) {
    submitStatus.value = 'error'
    errorMessage.value = formatApiError(err, "Une erreur est survenue lors de l'envoi du message.")
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="py-4">
    <div>
      <!-- Header Section -->
      <div class="mb-10">
        <h1 class="text-3xl font-medium text-gray-900 mb-4">Contactez-nous</h1>
        <p class="text-gray-500 max-w-2xl text-sm leading-relaxed">
          Vous avez une question sur notre marketplace ou souhaitez discuter d'un projet de casting ?
          Notre équipe vous répondra dans les plus brefs délais.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">
        <!-- Left Column: Form -->
        <div class="lg:col-span-7">
          <div class="bg-white p-6 sm:p-8 rounded-lg border border-gray-200 shadow-sm">
            <form @submit.prevent="handleSubmit" class="space-y-5">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                  <label for="name" class="text-xs font-medium text-gray-700 uppercase tracking-wider"
                    >Nom</label
                  >
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    maxlength="100"
                    class="w-full text-sm px-4 py-2.5 rounded-md border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#198496] focus:border-[#198496] transition-all bg-white"
                    placeholder="Votre nom complet"
                  />
                </div>
                <div class="space-y-1.5">
                  <label for="email" class="text-xs font-medium text-gray-700 uppercase tracking-wider"
                    >Email</label
                  >
                  <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    class="w-full text-sm px-4 py-2.5 rounded-md border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#198496] focus:border-[#198496] transition-all bg-white"
                    placeholder="votre@email.com"
                  />
                </div>
              </div>

              <div class="space-y-1.5">
                <label for="subject" class="text-xs font-medium text-gray-700 uppercase tracking-wider"
                  >Sujet</label
                >
                <input
                  id="subject"
                  v-model="form.subject"
                  type="text"
                  required
                  maxlength="200"
                  class="w-full text-sm px-4 py-2.5 rounded-md border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#198496] focus:border-[#198496] transition-all bg-white"
                  placeholder="De quoi souhaitez-vous discuter ?"
                />
              </div>

              <div class="space-y-1.5">
                <label for="message" class="text-xs font-medium text-gray-700 uppercase tracking-wider"
                  >Message</label
                >
                <textarea
                  id="message"
                  v-model="form.message"
                  rows="5"
                  required
                  maxlength="2000"
                  class="w-full text-sm px-4 py-2.5 rounded-md border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#198496] focus:border-[#198496] transition-all bg-white resize-none"
                  placeholder="Détaillez votre demande ici..."
                ></textarea>
              </div>

              <div class="pt-2">
                <button
                  type="submit"
                  :disabled="isSubmitting"
                  class="inline-flex items-center justify-center gap-2 text-sm font-medium bg-[#198496] text-white px-6 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors disabled:opacity-70 disabled:cursor-not-allowed w-full sm:w-auto min-w-[140px]"
                >
                  <template v-if="isSubmitting">
                    <Loader2 class="w-4 h-4 animate-spin" />
                    Envoi en cours...
                  </template>
                  <template v-else>
                    Envoyer le message
                    <Send class="w-3.5 h-3.5" />
                  </template>
                </button>
              </div>

              <!-- Status Messages -->
              <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
              >
                <div
                  v-if="submitStatus === 'success'"
                  class="p-4 bg-teal-50 border border-teal-100 rounded-md flex items-start gap-3 mt-4"
                >
                  <CheckCircle2 class="w-5 h-5 text-[#198496] mt-0.5 shrink-0" />
                  <div>
                    <p class="text-sm font-medium text-[#198496]">Message envoyé avec succès</p>
                    <p class="text-xs text-[#198496]/80 mt-1">
                      Merci de nous avoir contactés. Nous vous reviendrons très prochainement.
                    </p>
                  </div>
                </div>
                <div
                  v-else-if="submitStatus === 'error'"
                  class="p-4 bg-red-50 border border-red-100 rounded-md flex items-start gap-3 mt-4"
                >
                  <AlertCircle class="w-5 h-5 text-red-600 mt-0.5 shrink-0" />
                  <div>
                    <p class="text-sm font-medium text-red-600">Erreur lors de l'envoi</p>
                    <p class="text-xs text-red-600/80 mt-1">{{ errorMessage }}</p>
                  </div>
                </div>
              </Transition>
            </form>
          </div>
        </div>

        <!-- Right Column: Info -->
        <div class="lg:col-span-5 space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-6">
            <!-- Email Card -->
            <div
              class="group bg-white p-6 rounded-lg border border-gray-200 hover:border-[#198496]/30 transition-all duration-200 shadow-sm"
            >
              <div class="flex items-start gap-4">
                <div
                  class="p-2.5 bg-gray-50 rounded-md group-hover:bg-[#198496]/10 transition-colors"
                >
                  <Mail class="w-5 h-5 text-[#198496]" />
                </div>
                <div>
                  <h3 class="text-sm font-medium text-gray-900 mb-1">Email</h3>
                  <p class="text-gray-500 text-sm">Notre équipe vous assiste</p>
                  <a
                    href="mailto:contact@weact.bj"
                    class="text-sm font-medium text-[#198496] hover:underline mt-2 inline-block"
                  >
                    contact@weact.bj
                  </a>
                </div>
              </div>
            </div>

            <!-- Phone Card -->
            <div
              class="group bg-white p-6 rounded-lg border border-gray-200 hover:border-[#198496]/30 transition-all duration-200 shadow-sm"
            >
              <div class="flex items-start gap-4">
                <div
                  class="p-2.5 bg-gray-50 rounded-md group-hover:bg-[#198496]/10 transition-colors"
                >
                  <Phone class="w-5 h-5 text-[#198496]" />
                </div>
                <div>
                  <h3 class="text-sm font-medium text-gray-900 mb-1">Téléphone</h3>
                  <p class="text-gray-500 text-sm">Appelez-nous directement</p>
                  <a
                    href="tel:+2290141229017"
                    class="text-sm font-medium text-[#198496] hover:underline mt-2 inline-block"
                  >
                    +229 01 41 22 90 17
                  </a>
                </div>
              </div>
            </div>

            <!-- Location Card -->
            <div
              class="group bg-white p-6 rounded-lg border border-gray-200 hover:border-[#198496]/30 transition-all duration-200 shadow-sm"
            >
              <div class="flex items-start gap-4">
                <div
                  class="p-2.5 bg-gray-50 rounded-md group-hover:bg-[#198496]/10 transition-colors"
                >
                  <MapPin class="w-5 h-5 text-[#198496]" />
                </div>
                <div>
                  <h3 class="text-sm font-medium text-gray-900 mb-1">Localisation</h3>
                  <p class="text-gray-500 text-sm">Retrouvez-nous à</p>
                  <p class="text-sm font-medium text-gray-900 mt-2">Cotonou, Bénin</p>
                </div>
              </div>
            </div>

            <!-- Social Media Card -->
            <div
              class="group bg-white p-6 rounded-lg border border-gray-200 hover:border-[#198496]/30 transition-all duration-200 shadow-sm"
            >
              <div class="flex items-start gap-4">
                <div
                  class="p-2.5 bg-gray-50 rounded-md group-hover:bg-[#198496]/10 transition-colors"
                >
                  <Share2 class="w-5 h-5 text-[#198496]" />
                </div>
                <div class="w-full">
                  <h3 class="text-sm font-medium text-gray-900 mb-1">Réseaux Sociaux</h3>
                  <p class="text-gray-500 text-sm mb-4">Suivez notre actualité</p>
                  <div class="flex flex-wrap gap-4">
                    <a
                      href="https://www.instagram.com/weact.bj?igsh=MXAwaGg4d3U5amRlcA=="
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-xs font-medium text-gray-700 hover:text-[#198496] transition-colors border border-gray-200 px-3 py-1 rounded-full bg-white"
                      >Instagram</a
                    >
                    <a
                      href="https://www.tiktok.com/@weact.bj?_r=1&_t=ZS-95BYD4Ncjky"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-xs font-medium text-gray-700 hover:text-[#198496] transition-colors border border-gray-200 px-3 py-1 rounded-full bg-white"
                      >TikTok</a
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Decorative element -->
          <div
            class="relative overflow-hidden bg-gray-900 rounded-lg p-8 text-white min-h-[200px] flex flex-col justify-end"
          >
            <div
              class="absolute top-0 right-0 w-32 h-32 bg-[#198496]/20 rounded-full blur-3xl -mr-16 -mt-16"
            ></div>
            <div
              class="absolute bottom-0 left-0 w-24 h-24 bg-[#198496]/10 rounded-full blur-2xl -ml-12 -mb-12"
            ></div>
            <h4 class="text-lg font-medium mb-2 relative z-10">Casting WEACT</h4>
            <p class="text-xs text-gray-400 leading-relaxed relative z-10">
              La plateforme de référence pour connecter les talents aux meilleurs projets
              audiovisuels au Bénin et en Afrique.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
