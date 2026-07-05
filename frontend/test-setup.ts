import { beforeEach } from 'vitest'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'

beforeEach(() => {
  resetAllSharedCachedResources()
})
