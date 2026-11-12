import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgendaEarnings from '../components/AgendaEarnings.vue'
import { formatMoney } from '@/utils/money'

function mountEarnings(earnings: { amount: number; count: number } | null) {
  return mount(AgendaEarnings, { props: { earnings } })
}

const text = (wrapper: ReturnType<typeof mountEarnings>, testid: string) =>
  wrapper.get(`[data-testid="${testid}"]`).text()

describe('AgendaEarnings', () => {
  it('affiche le titre, le montant formaté, le nombre d\'engagements et la mention de versement', () => {
    const wrapper = mountEarnings({ amount: 75000, count: 2 })
    expect(wrapper.text()).toContain('Gains à venir')
    expect(text(wrapper, 'earnings-amount')).toBe(formatMoney(75000))
    expect(text(wrapper, 'earnings-count')).toBe('2 engagements en séquestre')
    expect(wrapper.text()).toContain('Versés sur votre portefeuille après le tournage.')
  })

  it('met « engagement » au singulier pour un seul', () => {
    expect(text(mountEarnings({ amount: 30000, count: 1 }), 'earnings-count')).toBe('1 engagement en séquestre')
  })

  it('affiche « Aucun gain en attente » à 0 ou sans donnée', () => {
    expect(text(mountEarnings({ amount: 0, count: 0 }), 'earnings-empty')).toBe('Aucun gain en attente')
    const none = mountEarnings(null)
    expect(text(none, 'earnings-empty')).toBe('Aucun gain en attente')
    expect(none.find('[data-testid="earnings-amount"]').exists()).toBe(false)
  })
})
