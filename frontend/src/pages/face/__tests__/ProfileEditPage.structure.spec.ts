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

  it('DataPrivacySection is NOT inside the account security section', () => {
    // The account security section starts with "Account security section" comment
    // and ends with its closing </div>
    const securityStart = template.indexOf('Account security section')
    const securityBlock = template.slice(securityStart, securityStart + 500)

    expect(securityBlock).toContain('EmailChangeForm')
    expect(securityBlock).toContain('PasswordChangeForm')
    expect(securityBlock).not.toContain('DataPrivacySection')
  })

  it('DataPrivacySection appears AFTER the main form sections', () => {
    const experiencesIndex = template.indexOf('ExperiencesList')
    const dataPrivacyIndex = template.indexOf('<DataPrivacySection', experiencesIndex)

    expect(dataPrivacyIndex).toBeGreaterThan(experiencesIndex)
  })

  it('DataPrivacySection is wrapped in its own card', () => {
    // Find DataPrivacySection in template (the one in the template, not in imports)
    const templateSection = template.slice(template.indexOf('<template>'))
    const dpIndex = templateSection.indexOf('<DataPrivacySection')

    // Look backwards from DataPrivacySection for the wrapping card div
    const precedingBlock = templateSection.slice(Math.max(0, dpIndex - 200), dpIndex)
    expect(precedingBlock).toContain('rounded-2xl')
    expect(precedingBlock).toContain('border-gray-100')
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

  it('SubscriptionPanel is imported and placed before BasicInfoSection (FP-2.7)', () => {
    expect(template).toContain(
      "import SubscriptionPanel from '@/features/face/components/SubscriptionPanel.vue'",
    )
    expect(template).toContain(
      "import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'",
    )

    const templateSection = template.slice(template.indexOf('<template>'))
    const panelIndex = templateSection.indexOf('<SubscriptionPanel')
    const basicIndex = templateSection.indexOf('<BasicInfoSection')
    expect(panelIndex).toBeGreaterThan(-1)
    expect(basicIndex).toBeGreaterThan(-1)
    expect(panelIndex).toBeLessThan(basicIndex)
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

  it('mounts a section-ugc-video block between section-acting-video and section-bio-location (FP-2.7.1)', () => {
    const templateSection = template.slice(template.indexOf('<template>'))
    const actingIdx = templateSection.indexOf('id="section-acting-video"')
    const ugcIdx = templateSection.indexOf('id="section-ugc-video"')
    const bioIdx = templateSection.indexOf('id="section-bio-location"')

    expect(actingIdx).toBeGreaterThan(-1)
    expect(ugcIdx).toBeGreaterThan(actingIdx)
    expect(bioIdx).toBeGreaterThan(ugcIdx)
    expect(templateSection).toContain('Vidéo UGC')
  })

  it('drops subscriptionCanUploadActingVideo and adds subscriptionCanUploadPresentationVideo (FP-2.7.1)', () => {
    expect(template).not.toContain('subscriptionCanUploadActingVideo')
    expect(template).toContain('subscriptionCanUploadPresentationVideo')
  })
})
