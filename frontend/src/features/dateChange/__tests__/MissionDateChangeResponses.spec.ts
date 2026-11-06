import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionDateChangeResponses from '../components/MissionDateChangeResponses.vue'
import type { ProducerDateChangeLatest } from '../types'

const now = new Date('2026-11-06T08:00:00Z')

const latest: ProducerDateChangeLatest = {
  old_date: '2026-11-15',
  new_date: '2026-11-20',
  reason_label: 'Lieu indisponible',
  status: 'pending',
  respond_by: '2026-11-08T07:30:00Z',
  responses: [
    { candidature_uuid: 'c1', face_name: 'Awa D.', status: 'confirmed', responded_at: '2026-11-06T10:42:00Z' },
    { candidature_uuid: 'c2', face_name: 'Fifamè H.', status: 'pending', responded_at: null },
    { candidature_uuid: 'c3', face_name: 'Rachida S.', status: 'withdrawn', responded_at: '2026-11-06T09:00:00Z' },
  ],
}

function mountBlock(canReplace: boolean, data: ProducerDateChangeLatest = latest) {
  return mount(MissionDateChangeResponses, { props: { latest: data, canReplace, now } })
}

describe('MissionDateChangeResponses', () => {
  it('une ligne par Face avec pastille, détail et résumé', () => {
    const w = mountBlock(true)

    const confirmed = w.get('[data-testid="date-change-row-c1"]')
    expect(confirmed.text()).toContain('Awa D.')
    expect(confirmed.text()).toContain('Confirmé le 6 nov. à 11:42')
    expect(confirmed.text()).toContain('Confirmée')

    const pending = w.get('[data-testid="date-change-row-c2"]')
    expect(pending.text()).toContain('Répond jusqu’au 8 nov. à 08:30')
    expect(pending.text()).toContain('En attente')

    const freed = w.get('[data-testid="date-change-row-c3"]')
    expect(freed.text()).toContain('Place libérée')

    expect(w.get('[data-testid="date-change-summary-headline"]').text()).toBe('2 réponses reçues sur 3')
    expect(w.get('[data-testid="date-change-summary-text"]').text()).toContain('Rachida S. se retire.')
    expect(w.get('[data-testid="date-change-summary-countdown"]').text()).toBe('Fifamè H. a encore 1 j 23 h')
  })

  it('« Remplacer » apparaît sur la place libérée si le remplacement est possible', async () => {
    const w = mountBlock(true)
    const replace = w.get('[data-testid="date-change-row-c3"] [data-testid="date-change-replace"]')
    await replace.trigger('click')
    expect(w.emitted('replace')).toHaveLength(1)
    expect(w.findAll('[data-testid="date-change-replace"]')).toHaveLength(1)
  })

  it('sans remplacement possible : pas de lien, la place est marquée retirée', () => {
    const w = mountBlock(false)
    expect(w.find('[data-testid="date-change-replace"]').exists()).toBe(false)
    expect(w.get('[data-testid="date-change-row-c3"]').text()).toContain('Retirée')
    expect(w.get('[data-testid="date-change-summary-text"]').text()).toContain('Le budget de la place vous revient.')
  })

  it('toutes les Faces ont confirmé', () => {
    const w = mountBlock(false, {
      ...latest,
      status: 'completed',
      responses: [{ candidature_uuid: 'c1', face_name: 'Awa D.', status: 'confirmed', responded_at: '2026-11-06T10:42:00Z' }],
    })
    expect(w.get('[data-testid="date-change-summary-headline"]').text()).toBe('Toutes les réponses sont reçues')
    expect(w.find('[data-testid="date-change-summary-countdown"]').exists()).toBe(false)
  })
})
