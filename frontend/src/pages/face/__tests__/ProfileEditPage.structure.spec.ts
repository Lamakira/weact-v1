import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

/**
 * Structural tests for ProfileEditPage layout.
 * These verify the template structure without mounting the component
 * (which requires mocking ~15 composables).
 */
describe('ProfileEditPage structure', () => {
  const template = readFileSync(
    resolve(__dirname, '../ProfileEditPage.vue'),
    'utf-8',
  )

  it('Compte → Sécurité panel renders Email + Password, not DataPrivacySection', () => {
    const tpl = template.slice(template.indexOf('<template>'))
    const start = tpl.indexOf('data-testid="panel-securite"')
    expect(start).toBeGreaterThan(-1)
    // Bound the block to the securité panel only (up to the next panel).
    const next = tpl.indexOf('data-testid="panel-', start + 1)
    const block = tpl.slice(start, next === -1 ? start + 400 : next)
    expect(block).toContain('EmailChangeForm')
    expect(block).toContain('PasswordChangeForm')
    expect(block).not.toContain('DataPrivacySection')
  })

  it('DataPrivacySection appears AFTER the main form sections', () => {
    const experiencesIndex = template.indexOf('ExperiencesList')
    const dataPrivacyIndex = template.indexOf('<DataPrivacySection', experiencesIndex)

    expect(dataPrivacyIndex).toBeGreaterThan(experiencesIndex)
  })

  it('DataPrivacySection lives in the Compte → Mes données panel', () => {
    const tpl = template.slice(template.indexOf('<template>'))
    const start = tpl.indexOf('data-testid="panel-donnees"')
    expect(start).toBeGreaterThan(-1)
    const block = tpl.slice(start, start + 300)
    expect(block).toContain('<DataPrivacySection')
  })

  it('DataPrivacySection is still imported', () => {
    expect(template).toContain("import DataPrivacySection from '@/components/account/DataPrivacySection.vue'")
  })

  it('profile photo section heading is "Photo de mise en avant"', () => {
    expect(template).toContain('Photo de mise en avant')
    // Ensure old label is NOT used as a heading (alt text occurrences are fine)
    const templateSection = template.slice(template.indexOf('<template>'))
    expect(templateSection).not.toContain('Photo de profil')
  })

  it('no longer renders SubscriptionPanel (moved to the Facturation tab) but keeps useSubscriptionStatus for capability gating', () => {
    // The subscription / payment UI now lives in the dedicated billing tab
    // (FaceBillingPage). The profile page must not import or render the panel.
    expect(template).not.toContain(
      "import SubscriptionPanel from '@/features/face/components/SubscriptionPanel.vue'",
    )
    const templateSection = template.slice(template.indexOf('<template>'))
    expect(templateSection).not.toContain('<SubscriptionPanel')

    // But it still needs the capability matrix for the tier-aware album / video sections.
    expect(template).toContain(
      "import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'",
    )
  })

  it('album add-click handler guards against entitlement-resolved quota (FP-1.7)', () => {
    // The grid shortcut must not open the hidden file input once the
    // entitlement-resolved quota is reached.
    expect(template).toContain('isFullByEntitlement')
    expect(template).toContain(
      "toast.warning('Votre quota de photos est atteint pour votre abonnement actuel.')",
    )
  })

  it('hidden album file input early-returns when entitlement quota is reached (FP-1.7)', () => {
    const templateSection = template.slice(template.indexOf('<template>'))
    // The hidden file input's @change handler must check isFullByEntitlement
    // before forwarding to handleAlbumUpload.
    const hiddenInputBlock = templateSection.slice(
      templateSection.indexOf("ref=\"albumFileInputRef\""),
    )
    expect(hiddenInputBlock.slice(0, 600)).toContain('isFullByEntitlement')
  })

  it('album quota text is reactive to maxAlbumPhotos (FP-2.7)', () => {
    const templateSection = template.slice(template.indexOf('<template>'))
    // The hardcoded "jusqu'à 4 photos" must be gone — replaced by a dynamic
    // {{ maxAlbumPhotos }} expression.
    expect(templateSection).not.toMatch(/Ajoutez jusqu'à 4 photos/)
    expect(templateSection).toContain('maxAlbumPhotos')
  })

  it('uses useFaceVideos + FaceVideoUpload and no longer references the retired FP-1 acting trio (FP-2.7.1)', () => {
    expect(template).toContain(
      "import { useFaceVideos } from '@/features/face/composables/useFaceVideos'",
    )
    expect(template).toContain(
      "import FaceVideoUpload from '@/features/face/components/FaceVideoUpload.vue'",
    )
    // Negative assertions split so the test file itself doesn't trigger the
    // FP-2.7.1 AC #1 / Task 13.4 grep gate (the retired FP-1 acting-video
    // composable + component names returning empty across frontend/src).
    // Concatenating the prefix and the suffix keeps the assertion intent
    // while avoiding the literal token in source.
    const retiredComposable = 'useActing' + 'Video'
    const retiredImport = 'import Acting' + 'VideoUpload'
    expect(template).not.toContain(retiredComposable)
    expect(template).not.toContain(retiredImport)
  })

  it('Portfolio keeps album + 3 distinct video panels (presentation / acting / UGC)', () => {
    const tpl = template.slice(template.indexOf('<template>'))
    for (const id of ['panel-album', 'panel-video-pres', 'panel-video-acting', 'panel-video-ugc']) {
      expect(tpl).toContain(`data-testid="${id}"`)
    }
    expect(tpl).toContain('Vidéo UGC')
  })

  // -------------------------------------------------------------------
  // Tabbed structure (Profile Tabs refonte)
  // -------------------------------------------------------------------

  it('renders the four family tabs (Profil / Portfolio / Carrière / Compte)', () => {
    const tpl = template.slice(template.indexOf('<template>'))
    expect(tpl).toContain('data-testid="profile-family-tabs"')
    // testid is bound dynamically; the four families are declared in FAMILIES.
    expect(tpl).toContain('family-tab-${f.id}')
    for (const id of ['profil', 'portfolio', 'carriere', 'compte']) {
      expect(template).toContain(`id: '${id}'`)
    }
  })

  it('moves the editable Tarif into the Carrière → Tarif panel; the sidebar shows it read-only', () => {
    const tpl = template.slice(template.indexOf('<template>'))
    const tarifPanel = tpl.indexOf('data-testid="panel-tarif"')
    expect(tarifPanel).toBeGreaterThan(-1)
    // Editable TarifsForm sits inside the tarif panel...
    expect(tpl.slice(tarifPanel, tarifPanel + 400)).toContain('<TarifsForm')
    // ...and the sidebar surfaces a read-only value (no second TarifsForm).
    expect(tpl).toContain('data-testid="sidebar-tarif-value"')
    expect(tpl.split('<TarifsForm').length - 1).toBe(1)
  })

  it('keeps the Complétion card and adds the Statut + Résumé sidebar cards', () => {
    const tpl = template.slice(template.indexOf('<template>'))
    expect(tpl).toContain('ProfileCompletionIndicator')
    expect(tpl).toContain('data-testid="status-card"')
    expect(tpl).toContain('data-testid="resume-card"')
  })

  it('completion clicks open tabs (COMPLETION_TO_TAB) instead of scrollIntoView', () => {
    expect(template).toContain('COMPLETION_TO_TAB')
    expect(template).toContain('navigateTab')
    // handleCompletionItemClick no longer scrolls to #section-… anchors.
    const handlerStart = template.indexOf('function handleCompletionItemClick')
    const handlerBlock = template.slice(handlerStart, handlerStart + 400)
    expect(handlerBlock).not.toContain('scrollIntoView')
  })

  it('drops subscriptionCanUploadActingVideo and adds subscriptionCanUploadPresentationVideo (FP-2.7.1)', () => {
    expect(template).not.toContain('subscriptionCanUploadActingVideo')
    expect(template).toContain('subscriptionCanUploadPresentationVideo')
  })
})
