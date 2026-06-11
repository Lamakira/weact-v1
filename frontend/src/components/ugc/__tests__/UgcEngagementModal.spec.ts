import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcEngagementModal from '../UgcEngagementModal.vue'
import ChronoRing from '../ChronoRing.vue'

function mountModal(props: Partial<InstanceType<typeof UgcEngagementModal>['$props']> = {}) {
  return mount(UgcEngagementModal, {
    props: {
      isOpen: true,
      nombreVideos: 2,
      ...props,
    },
    global: {
      stubs: {
        Teleport: true,
      },
    },
  })
}

describe('UgcEngagementModal', () => {
  it('renders nothing when closed', () => {
    const wrapper = mountModal({ isOpen: false })

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('renders the engagement content when open', () => {
    const wrapper = mountModal({ nomProduit: 'Tenue Shade Fit' })

    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Accepter ce deal UGC')
    expect(wrapper.text()).toContain('Tenue Shade Fit')
    expect(wrapper.text()).toContain('Vidéo Unboxing — à livrer sous 7 jours')
    expect(wrapper.text()).toContain('Vidéo Avis — à livrer sous 14 jours')
  })

  it('renders two ChronoRing at progress 0 with labels 7 and 14', () => {
    const wrapper = mountModal()

    const rings = wrapper.findAllComponents(ChronoRing)
    expect(rings).toHaveLength(2)
    expect(rings[0]!.props('progress')).toBe(0)
    expect(rings[0]!.props('label')).toBe('7')
    expect(rings[1]!.props('progress')).toBe(0)
    expect(rings[1]!.props('label')).toBe('14')
  })

  it('shows the extras line only when nombreVideos exceeds 2', () => {
    const withExtras = mountModal({ nombreVideos: 4 })
    expect(withExtras.find('[data-testid="ugc-engagement-extras"]').exists()).toBe(true)
    expect(withExtras.text()).toContain('+ 2 vidéo(s) supplémentaire(s)')

    const withoutExtras = mountModal({ nombreVideos: 2 })
    expect(withoutExtras.find('[data-testid="ugc-engagement-extras"]').exists()).toBe(false)

    const withNull = mountModal({ nombreVideos: null })
    expect(withNull.find('[data-testid="ugc-engagement-extras"]').exists()).toBe(false)
  })

  it('shows the automatic suspension warning', () => {
    const wrapper = mountModal()

    const warning = wrapper.find('[data-testid="ugc-engagement-warning"]')
    expect(warning.exists()).toBe(true)
    expect(warning.text()).toContain('suspension automatique')
  })

  it('emits confirm from the CTA and cancel from the cancel button and Escape', async () => {
    const wrapper = mountModal()

    await wrapper.find('[data-testid="ugc-engagement-confirm"]').trigger('click')
    expect(wrapper.emitted('confirm')).toHaveLength(1)

    const cancelButton = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Annuler')
    expect(cancelButton).toBeDefined()
    await cancelButton!.trigger('click')
    expect(wrapper.emitted('cancel')).toHaveLength(1)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('cancel')).toHaveLength(2)
  })

  it('disables the confirm CTA while submitting', () => {
    const wrapper = mountModal({ isSubmitting: true })

    const confirmButton = wrapper.find('[data-testid="ugc-engagement-confirm"]')
    expect(confirmButton.attributes('disabled')).toBeDefined()
  })

  it('does not emit cancel while submitting (button, Escape)', async () => {
    const wrapper = mountModal({ isSubmitting: true })

    const cancelButton = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Annuler')
    expect(cancelButton!.attributes('disabled')).toBeDefined()
    await cancelButton!.trigger('click')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(wrapper.emitted('cancel')).toBeUndefined()
  })
})
