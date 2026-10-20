import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionApplyBlock from '../MissionApplyBlock.vue'
import type { Mission } from '@/features/mission/types'

// Les états « candidature posée » (clôture, absence, sélection en cours de paiement, expirée…) vivent
// désormais dans FaceMissionHub (lot C5) : voir FaceMissionHub.spec.ts. Ce bloc ne gère plus que l'avant-candidature.
describe('MissionApplyBlock — états avant candidature', () => {
  it('annonce la réponse du producteur sous 72 h après la clôture des candidatures (lot B6)', () => {
    const wrapper = mount(MissionApplyBlock, {
      props: {
        mission: { id: 'mission-uuid-1', is_accepting_candidatures: true } as unknown as Mission,
        canApply: true,
        isSubscriptionGated: false,
        isResendingVerification: false,
        isGenderContextUnknown: false,
        isRefreshingGenderContext: false,
        genderContextMessage: '',
        isGenderMismatch: false,
        genderMismatchMessage: '',
      },
    })

    expect(wrapper.text()).toContain('Réponse du producteur sous 72 h après la clôture des candidatures')
  })

  it('affiche « candidatures fermées » quand la mission n’accepte plus de candidatures', () => {
    const wrapper = mount(MissionApplyBlock, {
      props: {
        mission: { id: 'mission-uuid-1', is_accepting_candidatures: false } as unknown as Mission,
        canApply: true,
        isSubscriptionGated: false,
        isResendingVerification: false,
        isGenderContextUnknown: false,
        isRefreshingGenderContext: false,
        genderContextMessage: '',
        isGenderMismatch: false,
        genderMismatchMessage: '',
      },
    })

    expect(wrapper.text()).toContain('Les candidatures sont fermées pour cette mission')
  })
})
