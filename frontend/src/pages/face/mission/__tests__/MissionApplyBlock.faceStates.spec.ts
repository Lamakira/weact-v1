import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionApplyBlock from '../MissionApplyBlock.vue'
import type { Mission, MissionCandidature } from '@/features/mission/types'

function makeCandidature(overrides: Partial<MissionCandidature> = {}): MissionCandidature {
  return {
    id: 'cand-1',
    mission_id: '1',
    face_id: '1',
    status: 'pending',
    status_label: 'En attente',
    message_motivation: null,
    created_at: '2026-10-01T08:00:00+00:00',
    updated_at: '2026-10-01T08:00:00+00:00',
    ...overrides,
  }
}

function mountBlock(candidature: MissionCandidature, canCancelCandidature: boolean) {
  return mount(MissionApplyBlock, {
    props: {
      mission: { id: 'mission-uuid-1', is_accepting_candidatures: false } as unknown as Mission,
      candidature,
      hasApplied: true,
      showFaceTrackingCard: false,
      candidatureShipment: null,
      ugcTrackingStep: 0,
      isSubmittingReceipt: false,
      isUploadingDeliverable: false,
      uploadProgress: null,
      ugcEngaged: false,
      canCancelCandidature,
      canReconfirmUgc: false,
      isReconfirming: false,
      isCancelling: false,
      canApply: true,
      isSubscriptionGated: false,
      isResendingVerification: false,
      isGenderContextUnknown: false,
      isRefreshingGenderContext: false,
      genderContextMessage: '',
      isGenderMismatch: false,
      genderMismatchMessage: '',
    },
    global: { stubs: { ConfirmModal: true } },
  })
}

describe('MissionApplyBlock — états Face (clôture, absence)', () => {
  it('shows « Mission clôturée » in grey and no cancel button for a pending candidature on a closed mission', () => {
    const wrapper = mountBlock(
      makeCandidature({ display_status: 'mission_closed', display_status_label: 'Mission clôturée' }),
      false,
    )

    const banner = wrapper.find('[data-testid="candidature-mission-closed"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('Mission clôturée')
    expect(wrapper.text()).not.toContain('En attente')
    expect(wrapper.text()).not.toContain('Annuler ma candidature')
  })

  it('keeps the regular « Candidature envoyée » banner when the mission is still open', () => {
    const wrapper = mountBlock(makeCandidature({ display_status: 'pending' }), true)

    expect(wrapper.find('[data-testid="candidature-mission-closed"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Candidature envoyée')
    expect(wrapper.text()).toContain('Annuler ma candidature')
  })

  it('shows the absence contest block', () => {
    const wrapper = mountBlock(
      makeCandidature({
        status: 'confirmed',
        status_label: 'Confirmée',
        attendance_dispute: { state: 'contestable', deadline: '2026-10-12T08:00:00+00:00', can_contest: true },
      }),
      false,
    )

    expect(wrapper.find('[data-testid="absence-contestable"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="absence-contest-btn"]').exists()).toBe(true)
  })

  it('shows no contest block without attendance_dispute', () => {
    const wrapper = mountBlock(makeCandidature({ status: 'confirmed' }), false)

    expect(wrapper.find('[data-testid="absence-contestable"]').exists()).toBe(false)
  })
})
