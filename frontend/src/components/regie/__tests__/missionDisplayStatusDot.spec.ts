import { describe, expect, it } from 'vitest'
import { missionDisplayStatusDot, missionDisplayTones } from '../statusTone'
import { MissionDisplayStatus } from '@/features/mission/types/mission'

describe('missionDisplayStatusDot', () => {
  it.each(Object.values(MissionDisplayStatus))('utilise le libellé serveur et un ton défini pour %s', (display) => {
    const dot = missionDisplayStatusDot({ status: 'closed', display_status: display, display_status_label: 'Libellé serveur' })
    expect(dot.label).toBe('Libellé serveur')
    expect(dot.tone).toBe(missionDisplayTones[display])
  })

  it('retombe sur le statut stocké sans display_status', () => {
    expect(missionDisplayStatusDot({ status: 'closed' })).toEqual({ tone: 'pending', label: 'Clôturée' })
  })
})
