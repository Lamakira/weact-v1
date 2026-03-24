import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WalletWithdrawForm from '../WalletWithdrawForm.vue'

function mountForm(withdrawalMode: 'manual' | 'fedapay') {
  return mount(WalletWithdrawForm, {
    props: {
      balance: 50000,
      withdrawalMode,
      isWithdrawing: false,
      error: null,
    },
    global: {
      stubs: {
        teleport: true,
        transition: false,
      },
    },
  })
}

describe('WalletWithdrawForm', () => {
  it('shows the manual processing banner and submit label in manual mode', () => {
    const wrapper = mountForm('manual')

    expect(wrapper.text()).toContain('Votre demande sera traitée manuellement sous 48h')
    expect(wrapper.text()).toContain('Soumettre la demande')
  })

  it('keeps the fedapay label and hides the manual banner in fedapay mode', () => {
    const wrapper = mountForm('fedapay')

    expect(wrapper.text()).not.toContain('Votre demande sera traitée manuellement sous 48h')
    expect(wrapper.text()).toContain('Retirer')
  })
})
