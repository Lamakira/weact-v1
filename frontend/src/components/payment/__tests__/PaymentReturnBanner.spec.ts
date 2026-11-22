import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PaymentReturnBanner from '../PaymentReturnBanner.vue'
import { PAYMENT_RETURN_MESSAGES } from '@/composables/usePaymentReturn'

describe('PaymentReturnBanner', () => {
  it('failed + insufficient_funds shows the dedicated message', () => {
    const wrapper = mount(PaymentReturnBanner, {
      props: { state: 'failed', failureReason: 'insufficient_funds' },
    })

    const text = wrapper.get('[data-testid="payment-return-failed"]').text()
    expect(text).toContain(
      'Paiement refusé : solde insuffisant sur votre compte Mobile Money. Rechargez-le, puis réessayez.',
    )
    expect(text).not.toContain(PAYMENT_RETURN_MESSAGES.failed)
    expect(wrapper.find('[data-testid="payment-return-retry"]').exists()).toBe(true)
  })

  it.each([undefined, null])('failed without a reason (%s) shows the generic message', (failureReason) => {
    const wrapper = mount(PaymentReturnBanner, { props: { state: 'failed', failureReason } })

    const text = wrapper.get('[data-testid="payment-return-failed"]').text()
    expect(text).toContain('Paiement annulé ou refusé. Vous pouvez réessayer.')
    expect(text).not.toContain('solde insuffisant')
  })
})
