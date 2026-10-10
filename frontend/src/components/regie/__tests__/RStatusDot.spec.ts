import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RStatusDot from '../RStatusDot.vue'
import {
  bookingStatusDot,
  missionStatusDot,
  candidatureStatusDot,
  bookingTones,
  missionTones,
  candidatureTones,
  type StatusTone,
} from '../statusTone'
import { BookingStatus, BookingStatusLabel } from '@/features/booking/types/booking'
import { MissionStatus, MissionStatusLabel } from '@/features/mission/types/mission'
import { CandidatureStatus, CandidatureStatusLabel } from '@/features/candidature/types'

describe('RStatusDot', () => {
  const cases: [StatusTone, string][] = [
    ['pending', 'bg-state-pending'],
    ['progress', 'bg-state-progress'],
    ['success', 'bg-state-success'],
    ['done', 'bg-state-done'],
    ['danger', 'bg-state-danger'],
    ['neutral', 'bg-state-neutral'],
  ]

  it.each(cases)('le ton %s applique %s sur un point de 7 px masqué aux lecteurs d’écran', (tone, cls) => {
    const wrapper = mount(RStatusDot, { props: { tone, label: 'Libellé' } })
    const mark = wrapper.find('[data-testid="r-status-dot-mark"]')
    expect(mark.classes()).toContain(cls)
    expect(mark.classes()).toContain('size-[7px]')
    expect(mark.attributes('aria-hidden')).toBe('true')
    expect(wrapper.text()).toBe('Libellé')
  })

  it('utilise neutral par défaut et accepte le slot', () => {
    const wrapper = mount(RStatusDot, { slots: { default: 'Slot' } })
    expect(wrapper.attributes('data-tone')).toBe('neutral')
    expect(wrapper.text()).toBe('Slot')
  })

  it('hideLabel garde le libellé en sr-only', () => {
    const wrapper = mount(RStatusDot, { props: { tone: 'success', label: 'Payée', hideLabel: true } })
    expect(wrapper.find('.sr-only').text()).toBe('Payée')
  })
})

describe('statusTone', () => {
  it('couvre tous les statuts booking / mission / candidature', () => {
    expect(Object.keys(bookingTones).sort()).toEqual(Object.values(BookingStatus).sort())
    expect(Object.keys(missionTones).sort()).toEqual(Object.values(MissionStatus).sort())
    expect(Object.keys(candidatureTones).sort()).toEqual(Object.values(CandidatureStatus).sort())
  })

  it('réutilise les libellés existants', () => {
    expect(bookingStatusDot(BookingStatus.PAID).label).toBe(BookingStatusLabel.paid)
    expect(missionStatusDot(MissionStatus.PUBLISHED).label).toBe(MissionStatusLabel.published)
    expect(candidatureStatusDot(CandidatureStatus.PENDING).label).toBe(CandidatureStatusLabel.pending)
  })

  it('suit la convention de la maquette', () => {
    expect(candidatureStatusDot(CandidatureStatus.PENDING).tone).toBe('pending')
    expect(candidatureStatusDot(CandidatureStatus.ACCEPTED).tone).toBe('success')
    expect(candidatureStatusDot(CandidatureStatus.IN_PROGRESS).tone).toBe('progress')
    expect(candidatureStatusDot(CandidatureStatus.COMPLETED).tone).toBe('done')
    expect(candidatureStatusDot(CandidatureStatus.REJECTED).tone).toBe('danger')
    expect(bookingStatusDot(BookingStatus.CANCELLED_BY_FACE).tone).toBe('danger')
    expect(bookingStatusDot(BookingStatus.NO_SHOW).tone).toBe('danger')
    expect(bookingStatusDot(BookingStatus.COMPLETED).tone).toBe('done')
    expect(missionStatusDot(MissionStatus.PENDING_PAYMENT).tone).toBe('pending')
  })

  it('aligne les statuts de mission sur les KPI du dashboard Producteur', () => {
    expect(missionStatusDot(MissionStatus.PUBLISHED).tone).toBe('success')
    expect(missionStatusDot(MissionStatus.CLOSED).tone).toBe('pending')
    expect(missionStatusDot(MissionStatus.PENDING_ATTENDANCE_VALIDATION).tone).toBe('progress')
    expect(missionStatusDot(MissionStatus.COMPLETED).tone).toBe('done')
    expect(missionStatusDot(MissionStatus.DRAFT).tone).toBe('neutral')
    expect(missionStatusDot(MissionStatus.CLOSED).tone).not.toBe(missionStatusDot(MissionStatus.COMPLETED).tone)
  })
})
