import { describe, it, expect, beforeEach, vi } from 'vitest'
import { apiClient } from '@/lib/api-client'
import { transformToJsonApiDocument } from '@/lib/api-transformers'
import { leadSchema, type LeadFormData } from '@/lib/validation'
import type { Lead } from '@/types/api.generated'

// Mock axios to capture actual requests
let mockAxiosPost: any
let mockAxiosPatch: any

vi.mock('axios', () => {
  mockAxiosPost = vi.fn()
  mockAxiosPatch = vi.fn()
  
  return {
    default: {
      create: () => ({
        post: mockAxiosPost,
        patch: mockAxiosPatch,
        get: vi.fn(),
        delete: vi.fn(),
        interceptors: {
          request: { use: vi.fn() },
          response: { use: vi.fn() }
        }
      }),
      post: vi.fn()
    }
  }
})

describe('Form to API Integration - REAL Tests', () => {
  beforeEach(() => {
    mockAxiosPost.mockClear()
    mockAxiosPatch.mockClear()
  })

  describe('Lead Form Submission', () => {
    it('should send correct payload when creating a lead from form data', async () => {
      // This is EXACTLY what the form would submit
      const formData: LeadFormData = {
        firstName: 'John',
        lastName: 'Doe',
        email: 'john.doe@example.com',
        phone: '555-1234',
        mobile: '555-5678',
        title: 'CEO',
        company: 'Test Corp',
        accountName: 'Test Account',
        website: 'https://example.com',
        description: 'Test lead',
        status: 'New',
        source: 'Website'
      }

      // Validate form data like the form would
      const validatedData = leadSchema.parse(formData)

      // Mock successful response
      mockAxiosPost.mockResolvedValueOnce({
        data: {
          data: {
            type: 'Lead',
            id: '123',
            attributes: {
              first_name: 'John',
              last_name: 'Doe',
              email1: 'john.doe@example.com'
            }
          }
        }
      })

      // Call the API like the mutation would
      await apiClient.createLead(validatedData)

      // Verify the EXACT payload sent to the API
      expect(mockAxiosPost).toHaveBeenCalledTimes(1)
      const [endpoint, payload] = mockAxiosPost.mock.calls[0]
      
      console.log('=== CREATE LEAD - ACTUAL PAYLOAD SENT ===')
      console.log(JSON.stringify(payload, null, 2))

      expect(endpoint).toBe('/module')
      expect(payload).toEqual({
        data: {
          type: 'Leads',
          attributes: {
            first_name: 'John',
            last_name: 'Doe',
            email1: 'john.doe@example.com', // NOT email!
            phone_work: '555-1234',
            phone_mobile: '555-5678',
            title: 'CEO',
            company: 'Test Corp',
            account_name: 'Test Account',
            website: 'https://example.com',
            description: 'Test lead',
            status: 'New',
            lead_source: 'Website'
          }
        }
      })

      // Verify critical field mappings
      expect(payload.data.attributes.email1).toBe('john.doe@example.com')
      expect(payload.data.attributes.email).toBeUndefined() // Should NOT have 'email'
      expect(payload.data.attributes.firstName).toBeUndefined() // Should NOT have camelCase
    })

    it('should send correct payload when updating a lead', async () => {
      const existingLead: Lead = {
        id: '123',
        firstName: 'John',
        lastName: 'Doe',
        email: 'john.doe@example.com',
        status: 'New'
      }

      // Simulate form edit - user changes status
      const updatedFormData: LeadFormData = {
        ...existingLead,
        status: 'Qualified',
        description: 'Updated description'
      }

      mockAxiosPatch.mockResolvedValueOnce({
        data: {
          data: {
            type: 'Lead',
            id: '123',
            attributes: {}
          }
        }
      })

      // Call update like the form would
      await apiClient.updateLead(existingLead.id!, updatedFormData)

      // Verify the EXACT payload sent
      expect(mockAxiosPatch).toHaveBeenCalledTimes(1)
      const [endpoint, payload] = mockAxiosPatch.mock.calls[0]

      console.log('=== UPDATE LEAD - ACTUAL PAYLOAD SENT ===')
      console.log(JSON.stringify(payload, null, 2))

      expect(endpoint).toBe('/module')
      expect(payload.data.type).toBe('Leads')
      expect(payload.data.id).toBe('123')
      expect(payload.data.attributes.status).toBe('Qualified')
      expect(payload.data.attributes.description).toBe('Updated description')
    })

    it('should handle optional fields correctly', async () => {
      // Minimal form data - what happens with empty optional fields?
      const minimalFormData: LeadFormData = {
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@example.com',
        status: 'New'
        // All other fields are optional and might be undefined
      }

      mockAxiosPost.mockResolvedValueOnce({
        data: { data: { type: 'Lead', id: '456', attributes: {} } }
      })

      await apiClient.createLead(minimalFormData)

      const [, payload] = mockAxiosPost.mock.calls[0]

      console.log('=== MINIMAL LEAD - PAYLOAD WITH OPTIONAL FIELDS ===')
      console.log(JSON.stringify(payload, null, 2))

      // Should only have the fields that were provided
      expect(payload.data.attributes.first_name).toBe('Jane')
      expect(payload.data.attributes.last_name).toBe('Smith')
      expect(payload.data.attributes.email1).toBe('jane@example.com')
      expect(payload.data.attributes.status).toBe('New')

      // Optional fields should not be sent if not provided
      expect(payload.data.attributes.phone_work).toBeUndefined()
      expect(payload.data.attributes.company).toBeUndefined()
    })

    it('should show why the API returns 400 Bad Request', async () => {
      // Test with data that would cause 400 error
      const formData = {
        firstName: 'Test',
        lastName: 'User',
        email: 'test@example.com',
        status: 'New'
      } as LeadFormData

      // Let's see what actually gets transformed
      const jsonApiPayload = transformToJsonApiDocument('Leads', formData, false)
      
      console.log('=== DEBUGGING 400 ERROR ===')
      console.log('Form data:', formData)
      console.log('Transformed payload:', JSON.stringify(jsonApiPayload, null, 2))

      // Check if camelCase is leaking through
      const hasInvalidFields = Object.keys(jsonApiPayload.data.attributes).some(
        key => key.includes('firstName') || key.includes('lastName') || key === 'email'
      )

      if (hasInvalidFields) {
        console.error('ERROR: CamelCase fields found in payload!')
        console.error('Invalid fields:', Object.keys(jsonApiPayload.data.attributes))
      }

      expect(hasInvalidFields).toBe(false)
    })
  })

  describe('Field Transformation Verification', () => {
    it('should verify all field mappings work correctly', () => {
      const allFields: Partial<Lead> = {
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        phone: '111-1111',
        mobile: '222-2222',
        phoneWork: '333-3333',
        aiScore: 85,
        aiScoreDate: '2023-01-01',
        aiInsights: 'High value',
        accountName: 'Acme Corp',
        leadSource: 'Website'
      }

      const result = transformToJsonApiDocument('Leads', allFields, false)
      
      console.log('=== ALL FIELD MAPPINGS ===')
      console.log('Input:', allFields)
      console.log('Output:', JSON.stringify(result, null, 2))

      const attrs = result.data.attributes

      // Verify ALL mappings
      expect(attrs.first_name).toBe('John')
      expect(attrs.last_name).toBe('Doe')
      expect(attrs.email1).toBe('john@example.com') // Critical!
      expect(attrs.phone).toBe('111-1111')
      expect(attrs.mobile).toBe('222-2222')
      expect(attrs.phone_work).toBe('333-3333')
      expect(attrs.ai_score).toBe(85)
      expect(attrs.ai_score_date).toBe('2023-01-01')
      expect(attrs.ai_insights).toBe('High value')
      expect(attrs.account_name).toBe('Acme Corp')
      expect(attrs.lead_source).toBe('Website')

      // Verify no camelCase leaked through
      expect(attrs.firstName).toBeUndefined()
      expect(attrs.email).toBeUndefined()
      expect(attrs.aiScore).toBeUndefined()
    })
  })
})

// Run with: npm test form-to-api.test.ts