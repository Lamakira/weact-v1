import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingTimeline from '../BookingTimeline.vue'
import { BookingStatus } from '@/features/booking/types'

describe('BookingTimeline', () => {
  it('renders a dedicated no-show step after payment', () => {
    const wrapper = mount(BookingTimeline, {
      props: {
        status: BookingStatus.NO_SHOW,
      },
    })

    const text = wrapper.text()

    expect(text).toContain('Demande envoyée')
    expect(text).toContain('Acceptation')
    expect(text).toContain('Paiement')
    expect(text).toContain('Absence signalée')
    expect(text).not.toContain('Confirmation Face')
    expect(text).not.toContain('Confirmation Producteur')
    expect(text).not.toContain('Terminé')
  })

  it('marks earlier steps complete for a UGC commission_paid booking (not all future — review F2)', () => {
    const wrapper = mount(BookingTimeline, {
      props: {
        status: BookingStatus.COMMISSION_PAID,
      },
    })

    // commission_paid maps to the paid step (index 2): without the mapping the booking would
    // fall to currentStepIndex -1 and every step would render as 'future' (text-gray-400 only).
    expect(wrapper.html()).toContain('text-emerald-700') // at least one completed step present
    expect(wrapper.text()).toContain('Paiement')
  })

  it('keeps the standard completion flow for non no-show statuses', () => {
    const wrapper = mount(BookingTimeline, {
      props: {
        status: BookingStatus.COMPLETED,
      },
    })

    const text = wrapper.text()

    expect(text).toContain('Confirmation Face')
    expect(text).toContain('Confirmation Producteur')
    expect(text).toContain('Terminé')
    expect(text).not.toContain('Absence signalée')
  })
})
