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
