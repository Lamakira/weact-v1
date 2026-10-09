import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MessageBubble from '../components/MessageBubble.vue'
import MessageInput from '../components/MessageInput.vue'
import ConversationContextDetails from '../components/ConversationContextDetails.vue'
import { makeContext, makeMessage } from './fixtures'

describe('MessageBubble', () => {
  it('envoyé : teal 600 texte blanc ; reçu : blanc avec anneau', () => {
    const own = mount(MessageBubble, { props: { message: makeMessage({ is_own_message: true }) } })
    expect(own.find('.bg-weact-600').exists()).toBe(true)
    expect(own.find('.text-white').exists()).toBe(true)

    const received = mount(MessageBubble, { props: { message: makeMessage() } })
    expect(received.find('.bg-white.ring-1').exists()).toBe(true)
  })

  it('affiche « Lu » seulement si demandé et message envoyé lu', () => {
    const readAt = new Date(2030, 9, 9, 10, 30).toISOString()
    const message = makeMessage({ is_own_message: true, read_at: readAt })
    expect(mount(MessageBubble, { props: { message } }).find('[data-testid="read-receipt"]').exists()).toBe(false)

    const withReceipt = mount(MessageBubble, { props: { message, showReadReceipt: true } })
    expect(withReceipt.find('[data-testid="read-receipt"]').text()).toBe('Lu 10:30')

    const unread = mount(MessageBubble, {
      props: { message: makeMessage({ is_own_message: true }), showReadReceipt: true },
    })
    expect(unread.find('[data-testid="read-receipt"]').exists()).toBe(false)
  })
})

describe('MessageInput', () => {
  const mountInput = (modelValue: string) => mount(MessageInput, { props: { modelValue } })

  it('Entrée envoie le message', async () => {
    const wrapper = mountInput('  Salut  ')
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('submit')?.[0]).toEqual(['Salut'])
  })

  it('Maj+Entrée n’envoie pas (retour à la ligne)', async () => {
    const wrapper = mountInput('Salut')
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter', shiftKey: true })
    expect(wrapper.emitted('submit')).toBeUndefined()
  })

  it('le bouton « Envoyer » explicite envoie, et est désactivé si le texte est vide', async () => {
    const wrapper = mountInput('Hello')
    const button = wrapper.find('[data-testid="message-send"]')
    expect(button.text()).toContain('Envoyer')
    await button.trigger('click')
    expect(wrapper.emitted('submit')?.[0]).toEqual(['Hello'])

    const empty = mountInput('   ')
    expect(empty.find('[data-testid="message-send"]').attributes('disabled')).toBeDefined()
    await empty.find('textarea').trigger('keydown', { key: 'Enter' })
    expect(empty.emitted('submit')).toBeUndefined()
  })

  it('n’envoie pas pendant un envoi en cours', async () => {
    const wrapper = mount(MessageInput, { props: { modelValue: 'Hello', isLoading: true } })
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('submit')).toBeUndefined()
  })
})

describe('ConversationContextDetails', () => {
  it('Face : montre ce qu’elle reçoit, jamais frais de service ni total', () => {
    const wrapper = mount(ConversationContextDetails, {
      props: { context: makeContext('face'), role: 'face' },
    })
    const text = wrapper.text()
    expect(text).toContain('Vous recevez')
    expect(text).toContain('85')
    expect(text).not.toContain('Frais de service')
    expect(text).not.toContain('Total payé')
    expect(text).not.toContain('Cachet')
  })

  it('Producteur : cachet, frais de service et total', () => {
    const wrapper = mount(ConversationContextDetails, {
      props: { context: makeContext('producer'), role: 'producer' },
    })
    const text = wrapper.text()
    expect(text).toContain('Cachet')
    expect(text).toContain('Frais de service')
    expect(text).toContain('Total payé')
    expect(text).not.toContain('Vous recevez')
  })

  it('marque l’étape courante et rend les 4 étapes', () => {
    const wrapper = mount(ConversationContextDetails, {
      props: { context: makeContext('face'), role: 'face' },
    })
    const steps = wrapper.findAll('[data-testid="context-step"]')
    expect(steps.map((s) => s.text())).toEqual(['Demandé', 'Accepté', 'Payé', 'Réalisé'])
    expect(steps.map((s) => s.attributes('data-state'))).toEqual(['done', 'done', 'current', 'todo'])
    expect(steps[2]!.attributes('aria-current')).toBe('step')
  })

  it('candidature close : le signale', () => {
    const wrapper = mount(ConversationContextDetails, {
      props: {
        context: makeContext('face', {
          closed: true,
          step: null,
          candidature_status_label: 'Refusée',
          steps: [
            { key: 'requested', label: 'Demandé', state: 'todo' },
            { key: 'accepted', label: 'Accepté', state: 'todo' },
            { key: 'paid', label: 'Payé', state: 'todo' },
            { key: 'done', label: 'Réalisé', state: 'todo' },
          ],
        }),
        role: 'face',
      },
    })
    expect(wrapper.find('[data-testid="context-closed"]').text()).toBe('Candidature refusée')
  })
})
