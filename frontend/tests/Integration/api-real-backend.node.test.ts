// @vitest-environment node
import { describe, it, expect, beforeAll } from 'vitest'
import axios from 'axios'

// This test file validates our API schemas against the REAL backend
// Run with: RUN_BACKEND_TESTS=true npm test -- api-real-backend.node.test.ts

const API_BASE_URL = 'http://localhost:8080/custom/api'

describe('Real Backend API Integration (Node Environment)', () => {
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
      
      console.log('Login response status:', loginResponse.status)
      console.log('Login response data:', JSON.stringify(loginResponse.data, null, 2))
      
      if (!loginResponse.data?.data?.accessToken) {
        throw new Error('No access token in response')
      }
      
      authToken = loginResponse.data.data.accessToken
      
      // Set auth header for all subsequent requests
      apiClient.defaults.headers.common['Authorization'] = `Bearer ${authToken}`
      console.log('Authentication successful! Token:', authToken.substring(0, 20) + '...')
    } catch (error) {
      console.error('Failed to authenticate:')
      console.error('Error message:', error instanceof Error ? error.message : String(error))
      console.error('Error response:', error.response?.data)
      console.error('Error status:', error.response?.status)
      throw new Error(`Backend authentication failed: ${error.message}`)
    }
  })

  describe('Contacts API', () => {
    it('GET /contacts should return properly formatted list', async () => {
      if (!process.env.RUN_BACKEND_TESTS) return
      
      try {
        const response = await apiClient.get('/contacts', {
          params: { page: 1, limit: 5 }
        })

        console.log('Contacts response:', JSON.stringify(response.data, null, 2))

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

        console.log(`✓ Fetched ${response.data.data.length} contacts`)
      } catch (error) {
        console.error('Contacts API error:', axios.isAxiosError(error) ? error.response?.data : error)
        throw error
      }
    })

    it('POST /contacts should create a new contact', async () => {
      if (!process.env.RUN_BACKEND_TESTS) return

      const newContact = {
        firstName: 'Test',
        lastName: `API-${Date.now()}`,
        email: `test-${Date.now()}@example.com`,
        status: 'Active',
        phone: '555-0123',
        company: 'Test Company'
      }

      console.log('Creating contact:', newContact)

      try {
        const response = await apiClient.post('/contacts', newContact)
        
        console.log('Create contact response:', JSON.stringify(response.data, null, 2))

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

        console.log(`✓ Created contact with ID: ${created.id}`)
      } catch (error) {
        console.error('Create contact error:', axios.isAxiosError(error) ? error.response?.data : error)
        throw error
      }
    })
  })

  describe('Leads API', () => {
    it('GET /leads should return properly formatted list', async () => {
      if (!process.env.RUN_BACKEND_TESTS) return

      try {
        const response = await apiClient.get('/leads', {
          params: { page: 1, limit: 5 }
        })

        console.log('Leads response:', JSON.stringify(response.data, null, 2))

        // Check response structure
        expect(response.data).toHaveProperty('data')
        expect(response.data).toHaveProperty('pagination')
        expect(Array.isArray(response.data.data)).toBe(true)

        console.log(`✓ Fetched ${response.data.data.length} leads`)
      } catch (error) {
        console.error('Leads API error:', axios.isAxiosError(error) ? error.response?.data : error)
        throw error
      }
    })
  })

  describe('Error Handling', () => {
    it('should return 401 for requests without auth token', async () => {
      if (!process.env.RUN_BACKEND_TESTS) return

      const unauthClient = axios.create({
        baseURL: API_BASE_URL
      })

      try {
        await unauthClient.get('/contacts')
        expect.fail('Should have thrown an error')
      } catch (error) {
        console.log('Unauth error status:', axios.isAxiosError(error) ? error.response?.status : 'unknown')
        expect(axios.isAxiosError(error) && error.response?.status).toBe(401)
      }
    })
  })
})