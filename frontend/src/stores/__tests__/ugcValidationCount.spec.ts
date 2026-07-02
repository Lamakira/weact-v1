import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { producerApi } from '@/features/producer/services/producerApi'

vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: { listDeliverablesToReview: vi.fn() },
}))
import { useUgcValidationCountStore } from '../ugcValidationCount'

describe('useUgcValidationCountStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('starts at 0', () => {
    expect(useUgcValidationCountStore().count).toBe(0)
  })

  it('fetchCount sets count to the in_review list length', async () => {
    vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [{}, {}, {}] as never })
    const store = useUgcValidationCountStore()
    await store.fetchCount()
    expect(producerApi.listDeliverablesToReview).toHaveBeenCalledOnce()
    expect(store.count).toBe(3)
  })

  it('fetchCount leaves count unchanged and does not throw on error', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const store = useUgcValidationCountStore()
    store.setCount(2)
    vi.mocked(producerApi.listDeliverablesToReview).mockRejectedValue(new Error('boom'))
    await expect(store.fetchCount()).resolves.toBeUndefined()
    expect(store.count).toBe(2)
  })

  it('setCount clamps to >= 0', () => {
    const store = useUgcValidationCountStore()
    store.setCount(5)
    expect(store.count).toBe(5)
    store.setCount(-3)
    expect(store.count).toBe(0)
  })

  it('$reset returns count to 0', () => {
    const store = useUgcValidationCountStore()
    store.setCount(4)
    store.$reset()
    expect(store.count).toBe(0)
  })
})
