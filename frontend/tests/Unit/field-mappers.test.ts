import { 
  mapSuiteCRMToFrontend, 
  mapFrontendToSuiteCRM,
  toCamelCase,
  toSnakeCase,
  isSuiteCRMRecord,
  isFrontendRecord
} from '../field-mappers'

describe('Field Mappers', () => {
  describe('mapSuiteCRMToFrontend', () => {
    it('should map email1 to email', () => {
      const suiteCRMData = {
        id: '123',
        first_name: 'John',
        last_name: 'Doe',
        email1: 'john.doe@example.com',
        phone_work: '555-1234',
        date_entered: '2023-01-01',
        date_modified: '2023-01-02'
      }

      const result = mapSuiteCRMToFrontend(suiteCRMData)

      expect(result).toEqual({
        id: '123',
        firstName: 'John',
        lastName: 'Doe',
        email: 'john.doe@example.com',
        phoneWork: '555-1234',
        dateEntered: '2023-01-01',
        dateModified: '2023-01-02'
      })
    })

    it('should map AI custom fields correctly', () => {
      const suiteCRMData = {
        id: '123',
        ai_score: 85,
        ai_score_date: '2023-01-01T10:00:00Z',
        ai_insights: 'High potential lead'
      }

      const result = mapSuiteCRMToFrontend(suiteCRMData)

      expect(result).toEqual({
        id: '123',
        aiScore: 85,
        aiScoreDate: '2023-01-01T10:00:00Z',
        aiInsights: 'High potential lead'
      })
    })

    it('should map account custom fields correctly', () => {
      const suiteCRMData = {
        id: '456',
        name: 'Test Company',
        health_score: 90,
        mrr: 5000,
        last_activity: '2023-01-15T14:30:00Z'
      }

      const result = mapSuiteCRMToFrontend(suiteCRMData)

      expect(result).toEqual({
        id: '456',
        name: 'Test Company',
        healthScore: 90,
        mrr: 5000,
        lastActivity: '2023-01-15T14:30:00Z'
      })
    })

    it('should handle nested objects', () => {
      const suiteCRMData = {
        id: '123',
        attributes: {
          first_name: 'Jane',
          email1: 'jane@example.com'
        }
      }

      const result = mapSuiteCRMToFrontend(suiteCRMData)

      expect(result).toEqual({
        id: '123',
        attributes: {
          firstName: 'Jane',
          email: 'jane@example.com'
        }
      })
    })

    it('should handle arrays', () => {
      const suiteCRMData = [
        { id: '1', email1: 'user1@example.com' },
        { id: '2', email1: 'user2@example.com' }
      ]

      const result = mapSuiteCRMToFrontend(suiteCRMData)

      expect(result).toEqual([
        { id: '1', email: 'user1@example.com' },
        { id: '2', email: 'user2@example.com' }
      ])
    })

    it('should handle null and undefined', () => {
      expect(mapSuiteCRMToFrontend(null)).toBeNull()
      expect(mapSuiteCRMToFrontend(undefined)).toBeUndefined()
    })
  })

  describe('mapFrontendToSuiteCRM', () => {
    it('should map email to email1', () => {
      const frontendData = {
        id: '123',
        firstName: 'John',
        lastName: 'Doe',
        email: 'john.doe@example.com',
        phoneWork: '555-1234'
      }

      const result = mapFrontendToSuiteCRM(frontendData)

      expect(result).toEqual({
        id: '123',
        first_name: 'John',
        last_name: 'Doe',
        email1: 'john.doe@example.com',
        phone_work: '555-1234'
      })
    })

    it('should map AI fields correctly', () => {
      const frontendData = {
        id: '123',
        aiScore: 85,
        aiScoreDate: '2023-01-01T10:00:00Z',
        aiInsights: 'High potential lead'
      }

      const result = mapFrontendToSuiteCRM(frontendData)

      expect(result).toEqual({
        id: '123',
        ai_score: 85,
        ai_score_date: '2023-01-01T10:00:00Z',
        ai_insights: 'High potential lead'
      })
    })

    it('should map lead specific fields', () => {
      const frontendData = {
        leadSource: 'Website',
        leadSourceDescription: 'Contact form',
        statusDescription: 'Qualified lead',
        accountName: 'ABC Corp',
        opportunityAmount: 50000
      }

      const result = mapFrontendToSuiteCRM(frontendData)

      expect(result).toEqual({
        lead_source: 'Website',
        lead_source_description: 'Contact form',
        status_description: 'Qualified lead',
        account_name: 'ABC Corp',
        opportunity_amount: 50000
      })
    })
  })

  describe('toCamelCase', () => {
    it('should convert snake_case to camelCase', () => {
      expect(toCamelCase({ first_name: 'John' })).toEqual({ firstName: 'John' })
      expect(toCamelCase({ phone_work: '555-1234' })).toEqual({ phoneWork: '555-1234' })
      expect(toCamelCase({ lead_source_description: 'Test' })).toEqual({ leadSourceDescription: 'Test' })
    })

    it('should not affect already camelCase fields', () => {
      expect(toCamelCase({ firstName: 'John' })).toEqual({ firstName: 'John' })
      expect(toCamelCase({ id: '123' })).toEqual({ id: '123' })
    })
  })

  describe('toSnakeCase', () => {
    it('should convert camelCase to snake_case', () => {
      expect(toSnakeCase({ firstName: 'John' })).toEqual({ first_name: 'John' })
      expect(toSnakeCase({ phoneWork: '555-1234' })).toEqual({ phone_work: '555-1234' })
      expect(toSnakeCase({ leadSourceDescription: 'Test' })).toEqual({ lead_source_description: 'Test' })
    })

    it('should handle already snake_case fields', () => {
      expect(toSnakeCase({ first_name: 'John' })).toEqual({ first_name: 'John' })
      expect(toSnakeCase({ id: '123' })).toEqual({ id: '123' })
    })
  })

  describe('Type Guards', () => {
    describe('isSuiteCRMRecord', () => {
      it('should return true for valid SuiteCRM records', () => {
        const record = {
          id: '123',
          date_entered: '2023-01-01',
          date_modified: '2023-01-02',
          first_name: 'John'
        }

        expect(isSuiteCRMRecord(record)).toBe(true)
      })

      it('should return false for invalid records', () => {
        expect(isSuiteCRMRecord(null)).toBe(false)
        expect(isSuiteCRMRecord(undefined)).toBe(false)
        expect(isSuiteCRMRecord({})).toBe(false)
        expect(isSuiteCRMRecord({ id: '123' })).toBe(false)
        expect(isSuiteCRMRecord({ id: 123, date_entered: '2023', date_modified: '2023' })).toBe(false)
      })
    })

    describe('isFrontendRecord', () => {
      it('should return true for valid frontend records', () => {
        const record = {
          id: '123',
          dateEntered: '2023-01-01',
          dateModified: '2023-01-02',
          firstName: 'John'
        }

        expect(isFrontendRecord(record)).toBe(true)
      })

      it('should return false for invalid records', () => {
        expect(isFrontendRecord(null)).toBe(false)
        expect(isFrontendRecord(undefined)).toBe(false)
        expect(isFrontendRecord({})).toBe(false)
        expect(isFrontendRecord({ id: '123' })).toBe(false)
      })
    })
  })

  describe('Complex transformations', () => {
    it('should handle deeply nested structures', () => {
      const suiteCRMData = {
        id: '123',
        attributes: {
          first_name: 'John',
          email1: 'john@example.com',
          relationships: {
            account_data: {
              health_score: 85,
              last_activity: '2023-01-01'
            }
          }
        },
        meta_data: {
          created_by: 'admin',
          ai_score: 90
        }
      }

      const result = mapSuiteCRMToFrontend(suiteCRMData)

      expect(result).toEqual({
        id: '123',
        attributes: {
          firstName: 'John',
          email: 'john@example.com',
          relationships: {
            accountData: {
              healthScore: 85,
              lastActivity: '2023-01-01'
            }
          }
        },
        metaData: {
          createdBy: 'admin',
          aiScore: 90
        }
      })
    })

    it('should preserve non-object values', () => {
      const data = {
        string_field: 'value',
        number_field: 123,
        boolean_field: true,
        null_field: null,
        array_field: ['a', 'b', 'c']
      }

      const camelResult = toCamelCase(data)
      expect(camelResult).toEqual({
        stringField: 'value',
        numberField: 123,
        booleanField: true,
        nullField: null,
        arrayField: ['a', 'b', 'c']
      })

      const snakeResult = toSnakeCase(camelResult)
      expect(snakeResult).toEqual(data)
    })
  })
})