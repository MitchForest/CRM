import { describe, it, expect, beforeEach, vi } from 'vitest'
import { TypeSafeApiClient } from '@/lib/api-client-v2'
import axios from 'axios'

// Mock axios
vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() }
      }
    }))
  }
}))

// Mock auth store
vi.mock('@/stores/auth-store', () => ({
  getStoredAuth: vi.fn(),
  setStoredAuth: vi.fn(),
  clearStoredAuth: vi.fn()
}))

describe('TypeSafeApiClient', () => {
  let client: TypeSafeApiClient
  let mockAxiosInstance: {
    get: ReturnType<typeof vi.fn>
    post: ReturnType<typeof vi.fn>
    put: ReturnType<typeof vi.fn>
    delete: ReturnType<typeof vi.fn>
    interceptors: {
      request: { use: ReturnType<typeof vi.fn> }
      response: { use: ReturnType<typeof vi.fn> }
    }
  }

  beforeEach(() => {
    mockAxiosInstance = {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() }
      }
    }

    const axiosCreate = vi.mocked(axios.create)
    axiosCreate.mockReturnValue(mockAxiosInstance as any)
    client = new TypeSafeApiClient()
  })

  describe('Type Safety', () => {
    it('should validate request data against schema', async () => {
      const invalidData = {
        firstName: 'John',
        // Missing required fields: lastName, email
      }

      await expect(
        client.request('/contacts/create', invalidData as never)
      ).rejects.toThrow('Invalid request data')
    })

    it('should validate response data against schema', async () => {
      const invalidResponse = {
        data: [
          {
            id: '123',
            firstName: 'John',
            // Missing required fields
          }
        ],
        // Missing pagination
      }

      mockAxiosInstance.get.mockResolvedValue({ data: invalidResponse })

      await expect(
        client.request('/contacts')
      ).rejects.toThrow('Invalid response data')
    })
  })

  describe('Contacts API', () => {
    it('should fetch contacts with proper typing', async () => {
      const mockResponse = {
        data: [
          {
            id: '123',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@example.com',
            status: 'Active',
            createdAt: '2024-01-01T00:00:00Z',
            updatedAt: '2024-01-01T00:00:00Z'
          }
        ],
        pagination: {
          page: 1,
          limit: 10,
          total: 1,
          pages: 1
        }
      }

      mockAxiosInstance.get.mockResolvedValue({ data: mockResponse })

      const result = await client.request('/contacts', {
        page: '1',
        limit: '10'
      })

      expect(result).toEqual(mockResponse)
      expect(mockAxiosInstance.get).toHaveBeenCalledWith('/contacts', {
        params: { page: '1', limit: '10' }
      })
    })

    it('should create contact with validation', async () => {
      const newContact = {
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@example.com',
        status: 'Active' as const
      }

      const mockResponse = {
        success: true,
        data: {
          ...newContact,
          id: '456',
          createdAt: '2024-01-01T00:00:00Z',
          updatedAt: '2024-01-01T00:00:00Z'
        }
      }

      mockAxiosInstance.post.mockResolvedValue({ data: mockResponse })

      const result = await client.request('/contacts/create', newContact)

      expect(result).toEqual(mockResponse)
      expect(mockAxiosInstance.post).toHaveBeenCalledWith('/contacts/create', newContact)
    })
  })

  describe('Leads API', () => {
    it('should convert lead with proper validation', async () => {
      const convertData = {
        createOpportunity: true,
        opportunityData: {
          name: 'New Deal',
          amount: 10000,
          closeDate: '2024-12-31',
          salesStage: 'Prospecting'
        }
      }

      const mockResponse = {
        success: true,
        data: {
          contactId: '789',
          opportunityId: '101'
        }
      }

      mockAxiosInstance.post.mockResolvedValue({ data: mockResponse })

      const result = await client.request('/leads/:id/convert', convertData, { id: '123' })

      expect(result).toEqual(mockResponse)
      expect(mockAxiosInstance.post).toHaveBeenCalledWith('/leads/123/convert', convertData)
    })
  })

  describe('Error Handling', () => {
    it('should handle 401 errors and refresh token', async () => {
      const error = {
        response: { status: 401 },
        config: {}
      }

      mockAxiosInstance.get.mockRejectedValueOnce(error)
      mockAxiosInstance.post.mockResolvedValueOnce({
        data: {
          success: true,
          data: {
            accessToken: 'new-token',
            expiresIn: 3600
          }
        }
      })

      // Mock localStorage
      const mockAuth = {
        accessToken: 'old-token',
        refreshToken: 'refresh-token',
        user: { id: '1' }
      }
      
      vi.spyOn(Storage.prototype, 'getItem').mockReturnValue(JSON.stringify(mockAuth))
      vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {})

      // This should trigger token refresh
      try {
        await client.request('/contacts')
      } catch (error: unknown) {
        // Expected to fail after refresh attempt
      }

      expect(mockAxiosInstance.post).toHaveBeenCalledWith('/auth/refresh', {
        refreshToken: 'refresh-token'
      })
    })
  })
})

// TODO: Re-enable these tests when schemas are properly exported
// describe('API Schema Validation', () => {
//   describe('Contact Schema', () => {
//     it('should validate valid contact data', () => {
//       const validContact = {
//         id: '123',
//         firstName: 'John',
//         lastName: 'Doe',
//         email: 'john@example.com',
//         status: 'Active' as const,
//         createdAt: '2024-01-01T00:00:00Z',
//         updatedAt: '2024-01-01T00:00:00Z'
//       }

//       expect(() => ContactSchema.parse(validContact)).not.toThrow()
//     })

//     it('should reject invalid email', () => {
//       const invalidContact = {
//         firstName: 'John',
//         lastName: 'Doe',
//         email: 'not-an-email',
//         status: 'Active'
//       }

//       expect(() => ContactSchema.parse(invalidContact)).toThrow()
//     })

//     it('should reject invalid status', () => {
//       const invalidContact = {
//         firstName: 'John',
//         lastName: 'Doe',
//         email: 'john@example.com',
//         status: 'InvalidStatus'
//       }

//       expect(() => ContactSchema.parse(invalidContact)).toThrow()
//     })
//   })

//   describe('Lead Schema', () => {
//     it('should validate lead conversion data', () => {
//       const conversionSchema = ApiEndpointSchemas['/leads/:id/convert'].request

//       const validData = {
//         createOpportunity: true,
//         opportunityData: {
//           name: 'Deal',
//           amount: 5000,
//           closeDate: '2024-12-31',
//           salesStage: 'Qualification'
//         }
//       }

//       expect(() => conversionSchema.parse(validData)).not.toThrow()
//     })

//     it('should allow conversion without opportunity', () => {
//       const conversionSchema = ApiEndpointSchemas['/leads/:id/convert'].request

//       const validData = {
//         createOpportunity: false
//       }

//       expect(() => conversionSchema.parse(validData)).not.toThrow()
//     })
//   })

//   describe('Pagination Schema', () => {
//     it('should validate list response format', () => {
//       const schema = ListResponseSchema(ContactSchema)

//       const validResponse = {
//         data: [
//           {
//             id: '123',
//             firstName: 'John',
//             lastName: 'Doe',
//             email: 'john@example.com',
//             status: 'Active',
//             createdAt: '2024-01-01T00:00:00Z',
//             updatedAt: '2024-01-01T00:00:00Z'
//           }
//         ],
//         pagination: {
//           page: 1,
//           limit: 10,
//           total: 100,
//           pages: 10
//         }
//       }

//       expect(() => schema.parse(validResponse)).not.toThrow()
//     })
//   })
// })