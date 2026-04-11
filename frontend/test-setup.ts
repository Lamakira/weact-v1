import { beforeEach } from 'vitest'
import { resetSharedCachedResourcesForTests } from '@/lib/createSharedCachedResource'

beforeEach(() => {
  resetSharedCachedResourcesForTests()
})
