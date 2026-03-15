<script setup lang="ts">
/**
 * AppFooter Component
 * Public site footer with logo, social links, navigation columns, and copyright.
 * Design: Minimalist & Clean (from design-system.md)
 */
import { RouterLink } from 'vue-router'
import { Instagram, Facebook, Twitter, Youtube } from 'lucide-vue-next'
import logoBlanc from '@/assets/images/logoblanc.png'

const footerLinks = {
  faces: {
    title: 'TROUVER DES FACES',
    links: [
      { label: 'Parcourir les profils', to: '/faces', testId: 'footer-link-faces' },
      { label: 'Publier une mission', to: '/register/producer', testId: 'footer-link-publish' },
    ],
  },
  company: {
    title: 'ENTREPRISE',
    links: [
      { label: 'Légal', to: '/legal', testId: 'footer-link-legal' },
      { label: 'À propos', to: '/about', testId: 'footer-link-about' },
      { label: 'Contact', to: '/contact', testId: 'footer-link-contact' },
    ],
  },
  resources: {
    title: 'RESSOURCES',
    links: [
      { label: 'Blog', to: '/ressources', testId: 'footer-link-blog' },
      { label: 'FAQ', to: '/faq', testId: 'footer-link-faq' },
      { label: 'Guide de démarrage', to: '/guide', testId: 'footer-link-guide' },
      { label: 'Support', to: '/support', testId: 'footer-link-support' },
    ],
  },
}

const socialLinks = [
  { icon: Instagram, href: '#', label: 'Suivez-nous sur Instagram', testId: 'social-link-instagram' },
  { icon: Facebook, href: '#', label: 'Suivez-nous sur Facebook', testId: 'social-link-facebook' },
  { icon: Twitter, href: '#', label: 'Suivez-nous sur Twitter', testId: 'social-link-twitter' },
  { icon: Youtube, href: '#', label: 'Suivez-nous sur YouTube', testId: 'social-link-youtube' },
]
</script>

<template>
  <footer
    class="bg-[#101828]"
    role="contentinfo"
    data-testid="app-footer"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 lg:py-16">
      <!-- Main Footer Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">
        <!-- Brand Section -->
        <div class="space-y-6">
          <RouterLink to="/" class="inline-block" data-testid="footer-logo-link">
            <img
              :src="logoBlanc"
              alt="WEACT Logo"
              class="h-8 w-auto"
              data-testid="footer-logo"
            />
          </RouterLink>
          <p class="text-sm text-[#99A1AF] max-w-xs leading-relaxed" data-testid="footer-tagline">
            Marketplace béninoise du casting.
          </p>
          <div class="flex items-center gap-4" data-testid="footer-social-icons">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.href"
              :aria-label="social.label"
              :data-testid="social.testId"
              class="text-[#99A1AF] hover:text-white transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2 focus-visible:ring-offset-[#101828] rounded"
              target="_blank"
              rel="noopener noreferrer"
            >
              <component :is="social.icon" :size="20" stroke-width="2" />
            </a>
          </div>
        </div>

        <!-- Navigation Columns -->
        <div
          v-for="(group, key) in footerLinks"
          :key="key"
          class="flex flex-col space-y-4"
          :data-testid="`footer-column-${key}`"
        >
          <h3 class="text-sm font-bold text-[#6A7282] tracking-wider">
            {{ group.title }}
          </h3>
          <ul class="flex flex-col space-y-3">
            <li v-for="link in group.links" :key="link.to">
              <RouterLink
                :to="link.to"
                :data-testid="link.testId"
                class="text-sm text-[#99A1AF] hover:text-white transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2 focus-visible:ring-offset-[#101828] rounded"
              >
                {{ link.label }}
              </RouterLink>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div
        class="mt-12 lg:mt-16 pt-8 border-t border-[#1F2937] flex justify-center"
        data-testid="footer-bottom-bar"
      >
        <p class="text-sm text-[#6A7282]" data-testid="footer-copyright">
          © 2026 WeAct. Tous droits réservés.
        </p>
      </div>
    </div>
  </footer>
</template>
