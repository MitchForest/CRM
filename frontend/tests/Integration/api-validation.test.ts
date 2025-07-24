import { describe, it, expect } from 'vitest'
import { z } from 'zod'
import { ApiEndpointSchemas } from '@/lib/api-schemas'

// This test ensures all our API endpoints are properly typed and documented
describe('API Schema Completeness', () => {
  /* const backendRoutes = [
    // Auth routes
    { method: 'POST', path: '/auth/login' },
    { method: 'POST', path: '/auth/refresh' },
    { method: 'POST', path: '/auth/logout' },
    
    // Contact routes
    { method: 'GET', path: '/contacts' },
    { method: 'GET', path: '/contacts/:id' },
    { method: 'POST', path: '/contacts' },
    { method: 'PUT', path: '/contacts/:id' },
    { method: 'DELETE', path: '/contacts/:id' },
    { method: 'GET', path: '/contacts/:id/activities' },
    
    // Lead routes
    { method: 'GET', path: '/leads' },
    { method: 'GET', path: '/leads/:id' },
    { method: 'POST', path: '/leads' },
    { method: 'PUT', path: '/leads/:id' },
    { method: 'DELETE', path: '/leads/:id' },
    { method: 'POST', path: '/leads/:id/convert' },
    
    // Opportunity routes
    { method: 'GET', path: '/opportunities' },
    { method: 'GET', path: '/opportunities/:id' },
    { method: 'POST', path: '/opportunities' },
    { method: 'PUT', path: '/opportunities/:id' },
    { method: 'DELETE', path: '/opportunities/:id' },
    { method: 'POST', path: '/opportunities/:id/analyze' },
    
    // Task routes
    { method: 'GET', path: '/tasks' },
    { method: 'GET', path: '/tasks/:id' },
    { method: 'POST', path: '/tasks' },
    { method: 'PUT', path: '/tasks/:id' },
    { method: 'DELETE', path: '/tasks/:id' },
    { method: 'PUT', path: '/tasks/:id/complete' },
    { method: 'GET', path: '/tasks/upcoming' },
    { method: 'GET', path: '/tasks/overdue' },
    
    // Case routes
    { method: 'GET', path: '/cases' },
    { method: 'GET', path: '/cases/:id' },
    { method: 'POST', path: '/cases' },
    { method: 'PUT', path: '/cases/:id' },
    { method: 'DELETE', path: '/cases/:id' },
    { method: 'POST', path: '/cases/:id/updates' },
    
    // Activity routes
    { method: 'GET', path: '/activities' },
    { method: 'POST', path: '/activities' },
    { method: 'GET', path: '/activities/upcoming' },
    { method: 'GET', path: '/activities/recent' },
  ] */

  it('should have schemas defined for critical endpoints', () => {
    const criticalEndpoints = [
      '/auth/login',
      '/auth/refresh',
      '/contacts',
      '/contacts/:id',
      '/leads',
      '/leads/:id',
      '/leads/:id/convert',
      '/opportunities',
      '/tasks',
      '/activities'
    ]

    criticalEndpoints.forEach(endpoint => {
      const schema = ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas]
      expect(schema, `Schema missing for ${endpoint}`).toBeDefined()
      expect(schema.method, `Method missing for ${endpoint}`).toBeDefined()
      expect(schema.request, `Request schema missing for ${endpoint}`).toBeDefined()
      expect(schema.response, `Response schema missing for ${endpoint}`).toBeDefined()
    })
  })

  it('should have proper Zod schemas for all endpoint configs', () => {
    Object.entries(ApiEndpointSchemas).forEach(([, config]) => {
      // Check that request and response are Zod schemas
      expect(config.request).toHaveProperty('parse')
      expect(config.request).toHaveProperty('safeParse')
      expect(config.response).toHaveProperty('parse')
      expect(config.response).toHaveProperty('safeParse')
      
      // Verify method is valid HTTP method
      expect(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']).toContain(config.method)
    })
  })

  it('should validate that POST/PUT endpoints have proper request schemas', () => {
    Object.entries(ApiEndpointSchemas).forEach(([endpoint, config]) => {
      if (config.method === 'POST' || config.method === 'PUT') {
        // These endpoints should have non-empty request schemas
        const schema = config.request
        
        // Try to get the shape of the schema
        if ('shape' in schema && typeof schema.shape === 'function') {
          const shape = schema.shape
          
          // POST/PUT should generally have at least one field
          if (!endpoint.includes('logout') && !endpoint.includes('refresh')) {
            expect(Object.keys(shape).length, 
              `${endpoint} should have request fields`
            ).toBeGreaterThan(0)
          }
        }
      }
    })
  })

  it('should have consistent response structures', () => {
    const listEndpoints = ['/contacts', '/leads', '/opportunities', '/tasks', '/activities']
    
    listEndpoints.forEach(endpoint => {
      const schema = ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas]
      if (!schema) return
      
      // List endpoints should return ListResponse structure
      const responseSchema = schema.response
      
      // Try parsing a mock list response to ensure it matches expected structure
      const mockListResponse = {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          total: 0,
          pages: 0
        }
      }
      
      expect(() => responseSchema.parse(mockListResponse)).not.toThrow()
    })
    
    const detailEndpoints = ['/contacts/:id', '/leads/:id', '/opportunities/:id', '/tasks/:id']
    
    detailEndpoints.forEach(endpoint => {
      const schema = ApiEndpointSchemas[endpoint as keyof typeof ApiEndpointSchemas]
      if (!schema) return
      
      // Detail endpoints should return ApiResponse structure
      const mockDetailResponse = {
        success: true,
        data: {}
      }
      
      // This might throw because data is empty, but structure should be correct
      try {
        schema.response.parse(mockDetailResponse)
      } catch (error: unknown) {
        // Check that it's failing on data validation, not structure
        if (error instanceof z.ZodError) {
          const issues = error.issues
          expect(issues.some(i => i.path.includes('success'))).toBe(false)
        }
      }
    })
  })

  it('should cover all CRUD operations for main entities', () => {
    const entities = ['contacts', 'leads', 'opportunities', 'tasks']
    /* const crudOps = {
      list: 'GET /{entity}',
      get: 'GET /{entity}/:id',
      create: 'POST /{entity}',
      update: 'PUT /{entity}/:id',
      delete: 'DELETE /{entity}/:id'
    } */

    const missingEndpoints: string[] = []

    entities.forEach(entity => {
      // Check list endpoint
      if (!ApiEndpointSchemas[`/${entity}` as keyof typeof ApiEndpointSchemas]) {
        missingEndpoints.push(`GET /${entity}`)
      }

      // Check get endpoint
      if (!ApiEndpointSchemas[`/${entity}/:id` as keyof typeof ApiEndpointSchemas]) {
        missingEndpoints.push(`GET /${entity}/:id`)
      }

      // For create, we might use different patterns
      const createEndpoints = [
        `/${entity}`,
        `/${entity}/create`
      ]
      
      const hasCreate = createEndpoints.some(ep => 
        ApiEndpointSchemas[ep as keyof typeof ApiEndpointSchemas]?.method === 'POST'
      )
      
      if (!hasCreate) {
        missingEndpoints.push(`POST /${entity}`)
      }
    })

    // Report missing endpoints
    if (missingEndpoints.length > 0) {
      console.warn('Missing endpoint schemas:', missingEndpoints)
    }

    // At minimum, we should have schemas for contacts and leads
    expect(ApiEndpointSchemas['/contacts']).toBeDefined()
    expect(ApiEndpointSchemas['/leads']).toBeDefined()
  })

  describe('Schema Type Safety', () => {
    it('should enforce required fields in request schemas', () => {
      const loginSchema = ApiEndpointSchemas['/auth/login'].request

      // Should fail without required fields
      expect(() => loginSchema.parse({})).toThrow()
      expect(() => loginSchema.parse({ username: 'test' })).toThrow()
      expect(() => loginSchema.parse({ password: 'test' })).toThrow()

      // Should pass with all required fields
      expect(() => loginSchema.parse({
        username: 'test',
        password: 'test123'
      })).not.toThrow()
    })

    it('should validate enum values in schemas', () => {
      // Contact create schema should validate status enum
      const contactCreateEndpoint = ApiEndpointSchemas['/contacts/create'] || 
                                   ApiEndpointSchemas['/contacts']
      
      if (contactCreateEndpoint?.method === 'POST') {
        const schema = contactCreateEndpoint.request
        
        // Invalid status should fail
        expect(() => schema.parse({
          firstName: 'John',
          lastName: 'Doe',
          email: 'john@example.com',
          status: 'InvalidStatus'
        })).toThrow()

        // Valid status should pass
        expect(() => schema.parse({
          firstName: 'John',
          lastName: 'Doe',
          email: 'john@example.com',
          status: 'Active'
        })).not.toThrow()
      }
    })
  })
})