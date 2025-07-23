import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { TypeSafeApiClient } from '@/lib/api-client-v2'
import { 
  ContactSchema, 
  LeadSchema, 
  ListResponseSchema,
  ApiResponseSchema 
} from '@/lib/api-schemas'

// This test file validates that our frontend schemas match the actual backend responses
// Run with: npm test -- api-backend.test.ts --run

describe('Backend API Schema Validation', () => {
  let client: TypeSafeApiClient
  let authToken: string

  beforeAll(async () => {
    // Skip these tests if not in CI or explicitly enabled
    if (!process.env.RUN_BACKEND_TESTS && !process.env.CI) {
      console.log('Skipping backend tests. Set RUN_BACKEND_TESTS=true to run.')
      return
    }

    client = new TypeSafeApiClient()

    // Login to get auth token
    try {
      const loginResponse = await client.request('/auth/login', {
        username: 'admin',
        password: 'admin'
      })
      authToken = loginResponse.data.accessToken
    } catch (error) {
      console.error('Failed to authenticate for backend tests:', error)
      throw error
    }
  })

  describe('Contacts Endpoint', () => {
    it('GET /contacts should match ListResponse<Contact> schema', async () => {
      if (!authToken) return

      const response = await client.request('/contacts', {
        page: '1',
        limit: '5'
      })

      // Validate response structure
      expect(response).toHaveProperty('data')
      expect(response).toHaveProperty('pagination')
      expect(Array.isArray(response.data)).toBe(true)
      
      // Validate pagination
      expect(response.pagination).toHaveProperty('page')
      expect(response.pagination).toHaveProperty('limit')
      expect(response.pagination).toHaveProperty('total')
      expect(response.pagination).toHaveProperty('pages')

      // Validate each contact matches schema
      response.data.forEach(contact => {
        expect(() => ContactSchema.parse(contact)).not.toThrow()
      })

      // Validate entire response
      const schema = ListResponseSchema(ContactSchema)
      expect(() => schema.parse(response)).not.toThrow()
    })

    it('GET /contacts/:id should match ApiResponse<Contact> schema', async () => {
      if (!authToken) return

      // First get a contact ID
      const listResponse = await client.request('/contacts', {
        page: '1',
        limit: '1'
      })

      if (listResponse.data.length === 0) {
        console.log('No contacts found to test GET /contacts/:id')
        return
      }

      const contactId = listResponse.data[0].id
      const response = await client.request('/contacts/:id', undefined, { id: contactId })

      // Validate response structure
      expect(response).toHaveProperty('success')
      expect(response).toHaveProperty('data')
      expect(response.success).toBe(true)

      // Validate contact data
      expect(() => ContactSchema.parse(response.data)).not.toThrow()

      // Validate entire response
      const schema = ApiResponseSchema(ContactSchema)
      expect(() => schema.parse(response)).not.toThrow()
    })

    it('POST /contacts should validate request and response schemas', async () => {
      if (!authToken) return

      const newContact = {
        firstName: 'Test',
        lastName: `User${Date.now()}`,
        email: `test${Date.now()}@example.com`,
        status: 'Active' as const,
        phone: '555-0123',
        company: 'Test Company'
      }

      // This should not throw - request is validated
      const response = await client.request('/contacts/create', newContact)

      // Validate response
      expect(response.success).toBe(true)
      expect(response.data).toBeDefined()
      expect(response.data?.email).toBe(newContact.email)

      // Validate response schema
      const schema = ApiResponseSchema(ContactSchema)
      expect(() => schema.parse(response)).not.toThrow()
    })
  })

  describe('Leads Endpoint', () => {
    it('GET /leads should match ListResponse<Lead> schema', async () => {
      if (!authToken) return

      const response = await client.request('/leads', {
        page: '1',
        limit: '5'
      })

      // Validate response structure
      expect(response).toHaveProperty('data')
      expect(response).toHaveProperty('pagination')
      expect(Array.isArray(response.data)).toBe(true)

      // Validate each lead matches schema
      response.data.forEach(lead => {
        expect(() => LeadSchema.parse(lead)).not.toThrow()
      })

      // Validate entire response
      const schema = ListResponseSchema(LeadSchema)
      expect(() => schema.parse(response)).not.toThrow()
    })

    it('Lead conversion endpoint should validate schemas', async () => {
      if (!authToken) return

      // First create a lead to convert
      const newLead = {
        firstName: 'Convert',
        lastName: `Test${Date.now()}`,
        email: `convert${Date.now()}@example.com`,
        status: 'Qualified' as const
      }

      const createResponse = await client.request('/leads/create', newLead)
      expect(createResponse.success).toBe(true)

      const leadId = createResponse.data?.id
      if (!leadId) return

      // Test conversion without opportunity
      const convertResponse = await client.request('/leads/:id/convert', {
        createOpportunity: false
      }, { id: leadId })

      expect(convertResponse.success).toBe(true)
      expect(convertResponse.data).toHaveProperty('contactId')
      expect(typeof convertResponse.data?.contactId).toBe('string')
    })
  })

  describe('Activities Endpoint', () => {
    it('GET /activities should return valid activity data', async () => {
      if (!authToken) return

      const response = await client.request('/activities', {
        page: '1',
        limit: '10'
      })

      expect(response).toHaveProperty('data')
      expect(response).toHaveProperty('pagination')
      expect(Array.isArray(response.data)).toBe(true)

      // Each activity should have required fields
      response.data.forEach(activity => {
        expect(activity).toHaveProperty('type')
        expect(activity).toHaveProperty('subject')
        expect(activity).toHaveProperty('date')
        expect(['Call', 'Meeting', 'Email', 'Task', 'Note']).toContain(activity.type)
      })
    })

    it('GET /activities/upcoming should return array of activities', async () => {
      if (!authToken) return

      const response = await client.request('/activities/upcoming')

      expect(response).toHaveProperty('success')
      expect(response).toHaveProperty('data')
      expect(Array.isArray(response.data)).toBe(true)
    })
  })

  describe('Error Response Validation', () => {
    it('should validate error responses match schema', async () => {
      if (!authToken) return

      try {
        // Try to get non-existent contact
        await client.request('/contacts/:id', undefined, { id: 'non-existent-id' })
      } catch (error) {
        const errorResponse = error.response?.data
        
        // Backend should return proper error structure
        expect(errorResponse).toHaveProperty('success')
        expect(errorResponse.success).toBe(false)
        expect(errorResponse).toHaveProperty('error')
        expect(errorResponse.error).toHaveProperty('message')
      }
    })

    it('should validate request validation errors', async () => {
      if (!authToken) return

      try {
        // Send invalid contact data
        await client.request('/contacts/create', {
          firstName: 'Test',
          // Missing required fields
        } as never)
      } catch (error) {
        // Should fail on client-side validation first
        expect(error.message).toContain('Invalid request data')
      }
    })
  })

  afterAll(async () => {
    // Cleanup if needed
  })
})