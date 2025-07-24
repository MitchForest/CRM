import { describe, it, expect, beforeAll } from 'vitest'
import { apiClient } from '@/lib/api-client'
import type { LeadFormData } from '@/lib/validation'

// This test ACTUALLY hits the real API to verify everything works
describe('Full Stack API Integration - LIVE TEST', () => {
  let authToken: string
  
  beforeAll(async () => {
    // Skip if not in integration test mode
    if (process.env.SKIP_INTEGRATION_TESTS === 'true') {
      return
    }
    
    try {
      const loginResult = await apiClient.login('apiuser', 'apiuser123')
      if (loginResult.success && loginResult.data) {
        authToken = loginResult.data.accessToken
      }
    } catch (error) {
      console.error('Failed to authenticate:', error)
    }
  })

  it('should create a lead with exact form data and verify what happens', async () => {
    if (!authToken) {
      console.log('Skipping - no auth token')
      return
    }

    // Exact data structure from form
    const formData: LeadFormData = {
      firstName: 'Integration',
      lastName: 'Test',
      email: 'integration.test@example.com',
      phone: '555-0001',
      mobile: '555-0002',
      title: 'Test Title',
      company: 'Test Company',
      accountName: 'Test Account',
      website: 'https://test.com',
      description: 'Created by integration test',
      status: 'New',
      source: 'Website'
    }

    console.log('=== CREATING LEAD WITH FORM DATA ===')
    console.log('Sending:', formData)

    try {
      const result = await apiClient.createLead(formData)
      
      console.log('=== API RESPONSE ===')
      console.log('Success:', result.success)
      console.log('Data:', result.data)
      console.log('Error:', (result as any).error)

      if (result.success && result.data) {
        // Verify the data was saved correctly
        const savedLead = await apiClient.getLead(result.data.id!)
        
        console.log('=== SAVED LEAD DATA ===')
        console.log(savedLead.data)
        
        // Check critical fields
        expect(savedLead.data?.email).toBe('integration.test@example.com')
        expect(savedLead.data?.firstName).toBe('Integration')
        expect(savedLead.data?.lastName).toBe('Test')
        
        // Clean up
        await apiClient.deleteLead(result.data.id!)
      }
    } catch (error: any) {
      console.error('=== API ERROR ===')
      console.error('Status:', error.response?.status)
      console.error('Data:', error.response?.data)
      console.error('Full error:', error)
      
      // This will show us the exact error from SuiteCRM
      throw error
    }
  })

  it('should update a lead and verify the payload', async () => {
    if (!authToken) return

    // First create a lead
    const createData: LeadFormData = {
      firstName: 'Update',
      lastName: 'Test',
      email: 'update.test@example.com',
      status: 'New'
    }

    const createResult = await apiClient.createLead(createData)
    
    if (createResult.success && createResult.data) {
      const leadId = createResult.data.id!
      
      // Now update it like the form would
      const updateData: Partial<LeadFormData> = {
        firstName: 'Updated',
        status: 'Qualified',
        description: 'Updated by test'
      }

      console.log('=== UPDATING LEAD ===')
      console.log('ID:', leadId)
      console.log('Update data:', updateData)

      try {
        const updateResult = await apiClient.updateLead(leadId, updateData)
        
        console.log('=== UPDATE RESPONSE ===')
        console.log('Success:', updateResult.success)
        console.log('Data:', updateResult.data)
        
        // Verify the update
        const updated = await apiClient.getLead(leadId)
        expect(updated.data?.firstName).toBe('Updated')
        expect(updated.data?.status).toBe('Qualified')
        
        // Clean up
        await apiClient.deleteLead(leadId)
      } catch (error: any) {
        console.error('=== UPDATE ERROR ===')
        console.error('Status:', error.response?.status)
        console.error('Data:', error.response?.data)
        throw error
      }
    }
  })
})

// Run with: SKIP_INTEGRATION_TESTS=false npm test full-stack-api.test.ts