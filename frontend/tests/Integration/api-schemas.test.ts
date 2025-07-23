import { describe, it, expect } from 'vitest'
import {
  ContactSchema,
  LeadSchema,
  OpportunitySchema,
  TaskSchema,
  ActivitySchema,
  ApiResponseSchema,
  ListResponseSchema,
  ApiEndpointSchemas
} from '@/lib/api-schemas'

describe('API Schemas Integration Tests', () => {
  describe('Entity Schemas', () => {
    it('should validate all required fields for Contact', () => {
      const validContact = {
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        status: 'Active' as const
      }

      const result = ContactSchema.parse(validContact)
      expect(result).toMatchObject(validContact)
    })

    it('should validate all required fields for Lead', () => {
      const validLead = {
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@example.com',
        status: 'New' as const
      }

      const result = LeadSchema.parse(validLead)
      expect(result).toMatchObject(validLead)
    })

    it('should validate all required fields for Opportunity', () => {
      const validOpportunity = {
        name: 'Big Deal',
        amount: 50000,
        salesStage: 'Prospecting',
        closeDate: '2024-12-31'
      }

      const result = OpportunitySchema.parse(validOpportunity)
      expect(result).toMatchObject(validOpportunity)
    })

    it('should validate all required fields for Task', () => {
      const validTask = {
        name: 'Follow up',
        status: 'Not Started' as const,
        priority: 'High' as const
      }

      const result = TaskSchema.parse(validTask)
      expect(result).toMatchObject(validTask)
    })

    it('should validate Activity with all types', () => {
      const activityTypes = ['Call', 'Meeting', 'Email', 'Task', 'Note'] as const

      activityTypes.forEach(type => {
        const activity = {
          type,
          subject: `Test ${type}`,
          date: '2024-01-01T10:00:00Z'
        }

        expect(() => ActivitySchema.parse(activity)).not.toThrow()
      })
    })
  })

  describe('Response Schemas', () => {
    it('should validate successful API response', () => {
      const schema = ApiResponseSchema(ContactSchema)
      
      const successResponse = {
        success: true,
        data: {
          id: '123',
          firstName: 'John',
          lastName: 'Doe',
          email: 'john@example.com',
          status: 'Active'
        }
      }

      const result = schema.parse(successResponse)
      expect(result.success).toBe(true)
      expect(result.data).toBeDefined()
    })

    it('should validate error API response', () => {
      const schema = ApiResponseSchema(ContactSchema)
      
      const errorResponse = {
        success: false,
        error: {
          error: 'VALIDATION_ERROR',
          message: 'Invalid input data'
        }
      }

      const result = schema.parse(errorResponse)
      expect(result.success).toBe(false)
      expect(result.error).toBeDefined()
    })

    it('should validate list response with pagination', () => {
      const schema = ListResponseSchema(ContactSchema)
      
      const listResponse = {
        data: [
          {
            id: '1',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@example.com',
            status: 'Active'
          },
          {
            id: '2',
            firstName: 'Jane',
            lastName: 'Smith',
            email: 'jane@example.com',
            status: 'Inactive'
          }
        ],
        pagination: {
          page: 1,
          limit: 10,
          total: 2,
          pages: 1
        }
      }

      const result = schema.parse(listResponse)
      expect(result.data).toHaveLength(2)
      expect(result.pagination.total).toBe(2)
    })
  })

  describe('Endpoint Schemas', () => {
    it('should have schemas for all documented endpoints', () => {
      const endpoints = [
        '/auth/login',
        '/auth/refresh',
        '/contacts',
        '/contacts/:id',
        '/leads',
        '/leads/:id',
        '/opportunities',
        '/tasks',
        '/activities'
      ]

      endpoints.forEach(endpoint => {
        expect(ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas]).toBeDefined()
        expect(ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas].method).toBeDefined()
        expect(ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas].request).toBeDefined()
        expect(ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas].response).toBeDefined()
      })
    })

    it('should validate login endpoint schemas', () => {
      const { request, response } = ApiEndpointSchemas['/auth/login']

      // Valid login request
      const validRequest = {
        username: 'testuser',
        password: 'password123'
      }
      expect(() => request.parse(validRequest)).not.toThrow()

      // Valid login response
      const validResponse = {
        success: true,
        data: {
          accessToken: 'token',
          refreshToken: 'refresh',
          expiresIn: 3600,
          user: {
            id: '1',
            username: 'testuser',
            email: 'test@example.com',
            firstName: 'Test',
            lastName: 'User',
            role: 'user',
            isActive: true
          }
        }
      }
      expect(() => response.parse(validResponse)).not.toThrow()
    })

    it('should validate lead conversion endpoint', () => {
      const { request, response } = ApiEndpointSchemas['/leads/:id/convert']

      // With opportunity
      const withOpp = {
        createOpportunity: true,
        opportunityData: {
          name: 'New Opportunity',
          amount: 10000,
          closeDate: '2024-12-31',
          salesStage: 'Qualification'
        }
      }
      expect(() => request.parse(withOpp)).not.toThrow()

      // Without opportunity
      const withoutOpp = {
        createOpportunity: false
      }
      expect(() => request.parse(withoutOpp)).not.toThrow()

      // Valid response
      const validResponse = {
        success: true,
        data: {
          contactId: '123',
          opportunityId: '456'
        }
      }
      expect(() => response.parse(validResponse)).not.toThrow()
    })
  })

  describe('Edge Cases and Validation Rules', () => {
    it('should reject invalid email formats', () => {
      const invalidEmails = [
        'notanemail',
        '@example.com',
        'user@',
        'user@.com',
        'user..name@example.com'
      ]

      invalidEmails.forEach(email => {
        expect(() => ContactSchema.parse({
          firstName: 'Test',
          lastName: 'User',
          email,
          status: 'Active'
        })).toThrow()
      })
    })

    it('should reject invalid enum values', () => {
      // Invalid contact status
      expect(() => ContactSchema.parse({
        firstName: 'Test',
        lastName: 'User',
        email: 'test@example.com',
        status: 'InvalidStatus'
      })).toThrow()

      // Invalid lead status
      expect(() => LeadSchema.parse({
        firstName: 'Test',
        lastName: 'User',
        email: 'test@example.com',
        status: 'InvalidStatus'
      })).toThrow()

      // Invalid task priority
      expect(() => TaskSchema.parse({
        name: 'Task',
        status: 'Not Started',
        priority: 'InvalidPriority'
      })).toThrow()
    })

    it('should handle optional fields correctly', () => {
      const minimalContact = {
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        status: 'Active' as const
      }

      const result = ContactSchema.parse(minimalContact)
      expect(result.phone).toBeUndefined()
      expect(result.mobile).toBeUndefined()
      expect(result.company).toBeUndefined()
    })

    it('should validate probability range for opportunities', () => {
      const opportunity = {
        name: 'Deal',
        amount: 1000,
        salesStage: 'Closing',
        closeDate: '2024-12-31'
      }

      // Valid probabilities
      expect(() => OpportunitySchema.parse({ ...opportunity, probability: 0 })).not.toThrow()
      expect(() => OpportunitySchema.parse({ ...opportunity, probability: 50 })).not.toThrow()
      expect(() => OpportunitySchema.parse({ ...opportunity, probability: 100 })).not.toThrow()

      // Invalid probabilities
      expect(() => OpportunitySchema.parse({ ...opportunity, probability: -1 })).toThrow()
      expect(() => OpportunitySchema.parse({ ...opportunity, probability: 101 })).toThrow()
    })
  })
})