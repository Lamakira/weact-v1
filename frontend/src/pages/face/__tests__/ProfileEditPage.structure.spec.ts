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
})
