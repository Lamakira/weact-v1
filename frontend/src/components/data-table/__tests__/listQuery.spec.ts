import { describe, expect, it } from 'vitest'
import { buildListQuery, parseListQuery, type ListQueryState } from '../listQuery'

const options = { sorts: ['created_at', 'montant'], statuses: ['pending', 'active'] }

describe('parseListQuery', () => {
  it('returns defaults for an empty query', () => {
    expect(parseListQuery({}, options)).toEqual({
      page: 1,
      perPage: 15,
      sort: null,
      direction: 'asc',
      status: '',
    })
  })

  it('reads valid values', () => {
    expect(
      parseListQuery(
        { page: '3', per_page: '25', sort: 'montant', direction: 'desc', status: 'active' },
        options,
      ),
    ).toEqual({ page: 3, perPage: 25, sort: 'montant', direction: 'desc', status: 'active' })
  })

  it('drops values the API would reject', () => {
    expect(
      parseListQuery(
        { page: '-2', per_page: '7', sort: 'password', direction: 'desc', status: 'bogus' },
        options,
      ),
    ).toEqual({ page: 1, perPage: 15, sort: null, direction: 'asc', status: '' })
  })
})

describe('buildListQuery', () => {
  const base: ListQueryState = { page: 1, perPage: 15, sort: null, direction: 'asc', status: '' }

  it('omits defaults', () => {
    expect(buildListQuery({}, base)).toEqual({})
  })

  it('writes list keys and keeps foreign keys', () => {
    const next = buildListQuery(
      { pay: 'abc', page: '9', sort: 'created_at' },
      { page: 2, perPage: 50, sort: 'montant', direction: 'desc', status: 'pending' },
    )
    expect(next).toEqual({
      pay: 'abc',
      status: 'pending',
      sort: 'montant',
      direction: 'desc',
      per_page: '50',
      page: '2',
    })
  })
})
