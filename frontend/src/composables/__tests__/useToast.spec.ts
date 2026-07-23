/**
 * useToast est le SEUL point du code applicatif qui connaît la librairie de
 * toasts : 43 call-sites en dépendent. Ces tests figent le contrat du wrapper
 * (types de toast, passage des options, clear) pour que la prochaine migration
 * — ou une mise à jour de vue-sonner — casse ici plutôt qu'en production.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'

const sonner = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
  dismiss: vi.fn(),
}))

vi.mock('vue-sonner', () => ({ toast: sonner }))

const { useToast } = await import('../useToast')

describe('useToast', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('transmet chaque type de toast à vue-sonner', () => {
    const toast = useToast()

    toast.success('Profil enregistré')
    toast.error('Échec de l’enregistrement')
    toast.warning('Session expirée')
    toast.info('Un email vous a été envoyé')

    expect(sonner.success).toHaveBeenCalledWith('Profil enregistré', undefined)
    expect(sonner.error).toHaveBeenCalledWith('Échec de l’enregistrement', undefined)
    expect(sonner.warning).toHaveBeenCalledWith('Session expirée', undefined)
    expect(sonner.info).toHaveBeenCalledWith('Un email vous a été envoyé', undefined)
  })

  it('passe la durée personnalisée (ex. inscription : 8 s)', () => {
    useToast().info('Un email de vérification a été envoyé.', { duration: 8000 })

    expect(sonner.info).toHaveBeenCalledWith('Un email de vérification a été envoyé.', {
      duration: 8000,
    })
  })

  it('clear() ferme tous les toasts ouverts', () => {
    useToast().clear()

    expect(sonner.dismiss).toHaveBeenCalledTimes(1)
    expect(sonner.dismiss).toHaveBeenCalledWith()
  })

  it('expose l’instance sonner pour les usages avancés', () => {
    expect(useToast().toast).toBe(sonner)
  })
})
