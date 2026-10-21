import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUgcValidationCountStore } from '../ugcValidationCount'

describe('useUgcValidationCountStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('starts at 0', () => {
    expect(useUgcValidationCountStore().count).toBe(0)
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
