import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ShareLocationDialog from '../components/ShareLocationDialog.vue'

function mountDialog(props: Partial<{ isOpen: boolean; isBusy: boolean; error: string | null }> = {}) {
  return mount(ShareLocationDialog, {
    props: { isOpen: true, otherPartyName: 'Awa Studio', isBusy: false, error: null, ...props },
    global: { stubs: { teleport: true } },
  })
}

describe('ShareLocationDialog', () => {
  it('affiche le titre, le texte avec le nom de l’autre partie et les deux boutons', () => {
    const wrapper = mountDialog()
    const dialog = wrapper.find('[role="dialog"]')

    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(wrapper.text()).toContain('Envoyer votre position ?')
    expect(wrapper.text()).toContain(
      'Awa Studio verra votre position exacte dans cette discussion. Elle ne se mettra pas à jour si vous bougez.',
    )
    expect(wrapper.find('[data-testid="share-location-cancel"]').text()).toBe('Annuler')
    expect(wrapper.find('[data-testid="share-location-confirm"]').text()).toBe('Envoyer ma position')
  })

  it('rien n’est rendu quand le dialogue est fermé', () => {
    expect(mountDialog({ isOpen: false }).find('[role="dialog"]').exists()).toBe(false)
  })

  it('« Envoyer ma position » émet confirm, « Annuler » émet cancel', async () => {
    const wrapper = mountDialog()

    await wrapper.find('[data-testid="share-location-confirm"]').trigger('click')
    await wrapper.find('[data-testid="share-location-cancel"]').trigger('click')

    expect(wrapper.emitted('confirm')).toHaveLength(1)
    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('pendant la localisation ou l’envoi : bouton de confirmation désactivé', () => {
    const wrapper = mountDialog({ isBusy: true })
    const confirm = wrapper.find('[data-testid="share-location-confirm"]')

    expect(confirm.attributes('disabled')).toBeDefined()
    expect(confirm.find('svg.animate-spin').exists()).toBe(true)
  })

  it('affiche l’erreur dans la fenêtre', () => {
    const wrapper = mountDialog({ error: 'Position introuvable pour le moment.' })
    const alert = wrapper.find('[data-testid="share-location-error"]')

    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toBe('Position introuvable pour le moment.')
  })

  it('Échap annule', async () => {
    const wrapper = mountDialog()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('cancel')).toHaveLength(1)
    wrapper.unmount()
  })

  it('un clic sur le fond annule', async () => {
    const wrapper = mountDialog()
    await wrapper.find('[data-testid="share-location-dialog"]').trigger('click')

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })
})
