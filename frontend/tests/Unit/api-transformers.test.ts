import { describe, it, expect } from 'vitest'
import {
  transformFromJsonApi,
  transformToJsonApi,
  extractPaginationMeta,
  transformJsonApiErrors,
  buildJsonApiFilters,
  buildJsonApiSort,
  buildJsonApiPagination
} from '@/lib/api-transformers'

describe('API Transformers', () => {
  describe('transformFromJsonApi', () => {
    it('should transform a JSON:API resource to application format', () => {
      const jsonApiResource = {
        type: 'contacts',
        id: '123',
        attributes: {
          first_name: 'John',
          last_name: 'Doe',
          email: 'john@example.com'
        },
        relationships: {
          account: {
            data: { type: 'accounts', id: '456' }
          }
        }
      }

      const result = transformFromJsonApi(jsonApiResource)

      expect(result).toEqual({
        id: '123',
        first_name: 'John',
        last_name: 'Doe',
        email: 'john@example.com',
        relationships: {
          account: { type: 'accounts', id: '456' }
        }
      })
    })
  })

  describe('transformToJsonApi', () => {
    it('should transform application data to JSON:API format', () => {
      const appData = {
        id: '123',
        first_name: 'John',
        last_name: 'Doe',
        email: 'john@example.com',
        relationships: {
          account: { type: 'accounts', id: '456' }
        }
      }

      const result = transformToJsonApi('contacts', appData)

      expect(result).toEqual({
        type: 'contacts',
        id: '123',
        attributes: {
          first_name: 'John',
          last_name: 'Doe',
          email: 'john@example.com'
        },
        relationships: {
          account: {
            data: { type: 'accounts', id: '456' }
          }
        }
      })
    })

    it('should handle data without id for creation', () => {
      const appData = {
        first_name: 'John',
        last_name: 'Doe'
      }

      const result = transformToJsonApi('contacts', appData, false)

      expect(result).toEqual({
        type: 'contacts',
        attributes: {
          first_name: 'John',
          last_name: 'Doe'
        }
      })
      expect(result.id).toBeUndefined()
    })
  })

  describe('extractPaginationMeta', () => {
    it('should extract pagination metadata from JSON:API response', () => {
      const document = {
        data: [],
        meta: {
          'page-number': 2,
          'page-size': 20,
          'total-pages': 5,
          'total-count': 100
        },
        links: {
          next: '/api/v8/module/Contacts?page[number]=3',
          prev: '/api/v8/module/Contacts?page[number]=1'
        }
      }

      const result = extractPaginationMeta(document)

      expect(result).toEqual({
        page: 2,
        pageSize: 20,
        totalPages: 5,
        totalCount: 100,
        hasNext: true,
        hasPrevious: true
      })
    })
  })

  describe('transformJsonApiErrors', () => {
    it('should transform JSON:API errors to application format', () => {
      const errors = [
        {
          status: '422',
          code: 'VALIDATION_ERROR',
          title: 'Validation Failed',
          detail: 'Email is required',
          source: { pointer: '/data/attributes/email' }
        }
      ]

      const result = transformJsonApiErrors(errors)

      expect(result).toEqual({
        message: 'Validation Failed',
        code: 'VALIDATION_ERROR',
        details: undefined
      })
    })
  })

  describe('buildJsonApiFilters', () => {
    it('should build JSON:API filter parameters', () => {
      const filters = {
        name: { operator: 'like', value: '%john%' },
        status: 'active',
        created: undefined
      }

      const result = buildJsonApiFilters(filters)

      expect(result).toEqual({
        'filter[name][like]': '%john%',
        'filter[status]': 'active'
      })
    })
  })

  describe('buildJsonApiSort', () => {
    it('should build JSON:API sort parameter', () => {
      expect(buildJsonApiSort('name', 'asc')).toBe('name')
      expect(buildJsonApiSort('created', 'desc')).toBe('-created')
      expect(buildJsonApiSort()).toBeUndefined()
    })
  })

  describe('buildJsonApiPagination', () => {
    it('should build JSON:API pagination parameters', () => {
      const result = buildJsonApiPagination(3, 25)

      expect(result).toEqual({
        'page[number]': '3',
        'page[size]': '25'
      })
    })
  })
})