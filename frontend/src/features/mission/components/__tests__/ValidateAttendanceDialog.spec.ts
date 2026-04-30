import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ValidateAttendanceDialog from '../ValidateAttendanceDialog.vue'

function renderDialog(props: Partial<InstanceType<typeof ValidateAttendanceDialog>['$props']> = {}) {
  return mount(ValidateAttendanceDialog, {
    props: {
      isOpen: true,
      missionTitle: 'Tournage Spot TV',
      presentCount: 1,
      absentCount: 1,
      totalReleased: 90000,
      totalRefunded: 90000,
      isLoading: false,
      ...props,
    },
    attachTo: document.body,
  })
}

describe('ValidateAttendanceDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders the title, counts and amounts (truncates long mission title)', () => {
    const longTitle = 'x'.repeat(60)
    renderDialog({ missionTitle: longTitle, presentCount: 2, absentCount: 1, totalReleased: 180000, totalRefunded: 90000 })

    const text = document.body.textContent ?? ''
    expect(text).toContain('Confirmer la validation des présences')
    expect(text).toContain('2 Face(s) présente(s)')
    expect(text).toContain('1 Face(s) absente(s)')
    expect(text.replace(/\s/g, ' ')).toContain('180 000 XOF')
    expect(text.replace(/\s/g, ' ')).toContain('90 000 XOF')
    // 50-char truncation + ellipsis
    expect(text).toContain('x'.repeat(50) + '...')
  })

  it('emits cancel when Escape is pressed', async () => {
    const wrapper = renderDialog()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await nextTick()

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('emits cancel when the backdrop is clicked', async () => {
    const wrapper = renderDialog()
    const backdrop = document.querySelector('.fixed.inset-0') as HTMLElement
    expect(backdrop).toBeTruthy()

    backdrop.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('emits confirm when the Valider button is clicked', async () => {
    const wrapper = renderDialog()
    const buttons = document.querySelectorAll('button')
    const confirmButton = Array.from(buttons).find((b) => b.textContent?.includes('Valider'))
    expect(confirmButton).toBeTruthy()

    confirmButton!.click()
    await nextTick()

    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })

  it('disables both buttons when isLoading is true', () => {
    renderDialog({ isLoading: true })
    const buttons = document.querySelectorAll('button')
    expect(buttons.length).toBeGreaterThanOrEqual(2)
    for (const button of Array.from(buttons)) {
      expect(button.hasAttribute('disabled')).toBe(true)
    }
  })
})
