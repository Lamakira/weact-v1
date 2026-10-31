import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MessageThread from '../components/MessageThread.vue'
import { makeContext, makeListItem, makeMessage } from './fixtures'
import type { ConversationContext } from '../types'

function mountThread(role: 'face' | 'producer', context: ConversationContext | null) {
  return mount(MessageThread, {
    props: {
      participant: makeListItem().other_participant, // « Afiavi Hounkpatin »
      context,
      role,
      messages: [makeMessage({ id: 1 })],
      isLoading: false,
      isRefreshing: false,
      error: null,
      refreshError: null,
      isSending: false,
      sendError: null,
      modelValue: '',
      compact: false,
    },
  })
}

describe('MessageThread — consigne « adresse de livraison »', () => {
  it('Face : encadré « Envoyez votre adresse de livraison à {nom} » quand awaiting_delivery_address est vrai', () => {
    const wrapper = mountThread('face', makeContext('face', { awaiting_delivery_address: true }))
    const hint = wrapper.find('[data-testid="delivery-address-hint"]')

    expect(hint.exists()).toBe(true)
    expect(hint.text()).toContain('Première étape')
    expect(hint.text()).toContain('Envoyez votre adresse de livraison à Afiavi Hounkpatin')
  })

  it('Producteur : encadré « … va vous envoyer son adresse de livraison ici »', () => {
    const wrapper = mountThread('producer', makeContext('producer', { awaiting_delivery_address: true }))
    const hint = wrapper.find('[data-testid="delivery-address-hint"]')

    expect(hint.exists()).toBe(true)
    expect(hint.text()).toContain('Afiavi Hounkpatin va vous envoyer son adresse de livraison ici.')
    expect(hint.text()).toContain('Confirmez l\'expédition sur WeAct')
  })

  it('absent quand awaiting_delivery_address est faux', () => {
    const wrapper = mountThread('face', makeContext('face', { awaiting_delivery_address: false }))

    expect(wrapper.find('[data-testid="delivery-address-hint"]').exists()).toBe(false)
  })

  it('absent quand awaiting_delivery_address est absent ou le contexte nul', () => {
    expect(
      mountThread('face', makeContext('face')).find('[data-testid="delivery-address-hint"]').exists(),
    ).toBe(false)
    expect(mountThread('face', null).find('[data-testid="delivery-address-hint"]').exists()).toBe(false)
  })
})
