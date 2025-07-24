import { describe, it, expect, beforeAll } from 'vitest'
import axios from 'axios'

// This test file validates our API schemas against the REAL backend
// Run with: RUN_BACKEND_TESTS=true npm test -- api-real-backend.test.ts

const API_BASE_URL = 'http://localhost:8080/custom/api'

describe('Real Backend API Integration', () => {
  let authToken: string
  let apiClient: ReturnType<typeof axios.create>

  beforeAll(async () => {
    // Skip these tests if not explicitly enabled
    if (!process.env.RUN_BACKEND_TESTS) {
      console.log('Skipping backend tests. Set RUN_BACKEND_TESTS=true to run.')
      return
    }

    // Create axios instance for testing
    apiClient = axios.create({
      baseURL: API_BASE_URL,
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json'
      }
    })

    // Login to get auth token
    console.log('Attempting to authenticate with backend...')
    try {
      const loginResponse = await apiClient.post('/auth/login', {
        username: 'admin',
        password: 'admin'
      })
      
      console.log('Login response:', loginResponse.data)
      authToken = loginResponse.data.data.accessToken
      
      // Set auth header for all subsequent requests
      apiClient.defaults.headers.common['Authorization'] = `Bearer ${authToken}`
      console.log('Authentication successful!')
    } catch (error: unknown) {
      const message = axios.isAxiosError(error) ? error.response?.data || error.message : String(error)
      console.error('Failed to authenticate:', message)
      throw new Error(`Backend authentication failed: ${message}`)
    }
  })

  describe('Contacts API', () => {
    it('GET /contacts should return properly formatted list', async () => {
      if (!authToken) return

      const response = await apiClient.get('/contacts', {
        params: { page: 1, limit: 5 }
      })

      // Check response structure
      expect(response.data).toHaveProperty('data')
      expect(response.data).toHaveProperty('pagination')
      expect(Array.isArray(response.data.data)).toBe(true)
      
      // Check pagination structure
      const { pagination } = response.data
      expect(pagination).toHaveProperty('page')
      expect(pagination).toHaveProperty('limit')
      expect(pagination).toHaveProperty('total')
      expect(pagination).toHaveProperty('pages')
      expect(pagination.page).toBe(1)
      expect(pagination.limit).toBe(5)

      // If we have contacts, validate their structure
      if (response.data.data.length > 0) {
        const contact = response.data.data[0]
        expect(contact).toHaveProperty('id')
        expect(contact).toHaveProperty('firstName')
        expect(contact).toHaveProperty('lastName')
        expect(contact).toHaveProperty('email')
        expect(contact).toHaveProperty('status')
        
        // Check status is valid enum
        expect(['Active', 'Inactive', 'Prospect']).toContain(contact.status)
      }

      console.log(`✓ Fetched ${response.data.data.length} contacts`)
    })

    it('POST /contacts should create a new contact', async () => {
      if (!authToken) return

      const newContact = {
        firstName: 'Test',
        lastName: `API-${Date.now()}`,
        email: `test-${Date.now()}@example.com`,
        status: 'Active',
        phone: '555-0123',
        company: 'Test Company'
      }

      const response = await apiClient.post('/contacts', newContact)

      // Check response structure
      expect(response.data).toHaveProperty('success')
      expect(response.data).toHaveProperty('data')
      expect(response.data.success).toBe(true)
      
      // Check created contact
      const created = response.data.data
      expect(created).toHaveProperty('id')
      expect(created.firstName).toBe(newContact.firstName)
      expect(created.lastName).toBe(newContact.lastName)
      expect(created.email).toBe(newContact.email)
      expect(created.status).toBe(newContact.status)

      console.log(`✓ Created contact with ID: ${created.id}`)

      // Get the created contact to verify
      const getResponse = await apiClient.get(`/contacts/${created.id}`)
      expect(getResponse.data.success).toBe(true)
      expect(getResponse.data.data.id).toBe(created.id)
    })
  })

  describe('Leads API', () => {
    it('GET /leads should return properly formatted list', async () => {
      if (!authToken) return

      const response = await apiClient.get('/leads', {
        params: { page: 1, limit: 5 }
      })

      // Check response structure
      expect(response.data).toHaveProperty('data')
      expect(response.data).toHaveProperty('pagination')
      expect(Array.isArray(response.data.data)).toBe(true)

      // If we have leads, validate their structure
      if (response.data.data.length > 0) {
        const lead = response.data.data[0]
        expect(lead).toHaveProperty('id')
        expect(lead).toHaveProperty('firstName')
        expect(lead).toHaveProperty('lastName')
        expect(lead).toHaveProperty('email')
        expect(lead).toHaveProperty('status')
        
        // Check status is valid enum
        expect(['New', 'Contacted', 'Qualified', 'Converted', 'Dead']).toContain(lead.status)
      }

      console.log(`✓ Fetched ${response.data.data.length} leads`)
    })

    it('POST /leads should create a new lead', async () => {
      if (!authToken) return

      const newLead = {
        firstName: 'Test',
        lastName: `Lead-${Date.now()}`,
        email: `lead-${Date.now()}@example.com`,
        status: 'New',
        source: 'API Test'
      }

      const response = await apiClient.post('/leads', newLead)

      expect(response.data).toHaveProperty('success')
      expect(response.data.success).toBe(true)
      expect(response.data.data).toHaveProperty('id')

      console.log(`✓ Created lead with ID: ${response.data.data.id}`)
    })

    it('POST /leads/:id/convert should convert lead to contact', async () => {
      if (!authToken) return

      // First create a lead to convert
      const newLead = {
        firstName: 'Convert',
        lastName: `Test-${Date.now()}`,
        email: `convert-${Date.now()}@example.com`,
        status: 'Qualified'
      }

      const createResponse = await apiClient.post('/leads', newLead)
      const leadId = createResponse.data.data.id

      // Convert the lead
      const convertResponse = await apiClient.post(`/leads/${leadId}/convert`, {
        createOpportunity: false
      })

      expect(convertResponse.data).toHaveProperty('success')
      expect(convertResponse.data.success).toBe(true)
      expect(convertResponse.data.data).toHaveProperty('contactId')

      console.log(`✓ Converted lead ${leadId} to contact ${convertResponse.data.data.contactId}`)
    })
  })

  describe('Activities API', () => {
    it('GET /activities should return activity list', async () => {
      if (!authToken) return

      const response = await apiClient.get('/activities', {
        params: { page: 1, limit: 10 }
      })

      expect(response.data).toHaveProperty('data')
      expect(Array.isArray(response.data.data)).toBe(true)

      // If we have activities, validate their structure
      if (response.data.data.length > 0) {
        const activity = response.data.data[0]
        expect(activity).toHaveProperty('id')
        expect(activity).toHaveProperty('type')
        expect(activity).toHaveProperty('subject')
        expect(activity).toHaveProperty('date')
        
        // Check type is valid enum
        expect(['Call', 'Meeting', 'Email', 'Task', 'Note']).toContain(activity.type)
      }

      console.log(`✓ Fetched ${response.data.data.length} activities`)
    })
  })

  describe('Error Handling', () => {
    it('should return proper error for non-existent resource', async () => {
      if (!authToken) return

      try {
        await apiClient.get('/contacts/non-existent-id')
        throw new Error('Should have thrown an error')
      } catch (error: unknown) {
        expect((error as any).response.status).toBe(404)
        expect((error as any).response.data).toHaveProperty('success')
        expect((error as any).response.data.success).toBe(false)
        expect((error as any).response.data).toHaveProperty('error')
      }
    })

    it('should return validation error for invalid data', async () => {
      if (!authToken) return

      try {
        await apiClient.post('/contacts', {
          // Missing required fields
          firstName: 'Test'
        })
        throw new Error('Should have thrown an error')
      } catch (error: unknown) {
        expect((error as any).response.status).toBe(400)
        expect((error as any).response.data).toHaveProperty('success')
        expect((error as any).response.data.success).toBe(false)
        expect((error as any).response.data).toHaveProperty('error')
      }
    })
  })

  describe('Authentication', () => {
    it('should reject requests without auth token', async () => {
      if (!authToken) return

      const unauthClient = axios.create({
        baseURL: API_BASE_URL
      })

      try {
        await unauthClient.get('/contacts')
        throw new Error('Should have thrown an error')
      } catch (error: unknown) {
        expect((error as any).response.status).toBe(401)
      }
    })

    it('should refresh token when needed', async () => {
      if (!authToken) return

      // This is a placeholder - would need to wait for token to expire
      // or manually expire it to test refresh logic
      expect(authToken).toBeTruthy()
    })
  })
})