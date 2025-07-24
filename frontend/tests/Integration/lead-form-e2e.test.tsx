import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import { LeadFormPage } from '@/pages/LeadForm'
import { apiClient } from '@/lib/api-client'

// Capture actual API calls
const apiCalls: { method: string; endpoint: string; payload: unknown }[] = []

// Mock the API client to capture calls
vi.mock('@/lib/api-client', () => ({
  apiClient: {
    createLead: vi.fn((data) => {
      // Capture what the form actually sends
      apiCalls.push({
        method: 'POST',
        endpoint: '/module',
        payload: data
      })
      
      // Simulate the transformation that SHOULD happen
      const transformedData = {
        data: {
          type: 'Leads',
          attributes: {
            // This is what SHOULD be sent
            first_name: data.firstName,
            last_name: data.lastName,
            email1: data.email, // NOT 'email'
            status: data.status
          }
        }
      }
      
      console.log('=== FORM SUBMISSION CAPTURED ===')
      console.log('Form sent:', data)
      console.log('Should transform to:', transformedData)
      
      return Promise.resolve({
        success: true,
        data: { id: '123', ...data }
      })
    }),
    
    getLead: vi.fn(() => Promise.resolve({
      success: true,
      data: {
        id: '123',
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        status: 'New'
      }
    }))
  }
}))

const renderWithProviders = (component: React.ReactElement) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false }
    }
  })
  
  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        {component}
      </BrowserRouter>
    </QueryClientProvider>
  )
}

describe('Lead Form E2E Test - What Actually Gets Sent', () => {
  beforeEach(() => {
    apiCalls.length = 0
    vi.clearAllMocks()
  })

  it('should submit form data and show what is actually sent to API', async () => {
    const user = userEvent.setup()
    
    renderWithProviders(<LeadFormPage />)
    
    // Fill out the form like a real user
    await user.type(screen.getByLabelText(/first name/i), 'Test')
    await user.type(screen.getByLabelText(/last name/i), 'User')
    await user.type(screen.getByLabelText(/email/i), 'test@example.com')
    
    // Find and click submit button
    const submitButton = screen.getByRole('button', { name: /create lead/i })
    await user.click(submitButton)
    
    // Wait for the API call
    await waitFor(() => {
      expect(apiClient.createLead).toHaveBeenCalled()
    })
    
    // Check what was actually sent
    expect(apiCalls).toHaveLength(1)
    const actualPayload = apiCalls[0]!.payload
    
    console.log('=== ACTUAL FORM SUBMISSION ===')
    console.log('Payload sent to apiClient.createLead:', actualPayload)
    
    // This is what the form sends - it should be camelCase
    expect(actualPayload).toMatchObject({
      firstName: 'Test',
      lastName: 'User', 
      email: 'test@example.com',
      status: 'New'
    })
    
    // The API client should transform this to snake_case
    // but we need to verify that actually happens
  })

  it('should show all fields that get sent including hidden/default ones', async () => {
    const user = userEvent.setup()
    
    renderWithProviders(<LeadFormPage />)
    
    // Fill minimum required fields
    await user.type(screen.getByLabelText(/first name/i), 'Jane')
    await user.type(screen.getByLabelText(/last name/i), 'Smith')
    await user.type(screen.getByLabelText(/email/i), 'jane@example.com')
    
    await user.click(screen.getByRole('button', { name: /create lead/i }))
    
    await waitFor(() => {
      expect(apiClient.createLead).toHaveBeenCalled()
    })
    
    const fullPayload = apiCalls[0]!.payload
    
    console.log('=== FULL PAYLOAD WITH ALL FIELDS ===')
    console.log(JSON.stringify(fullPayload, null, 2))
    
    // List ALL fields that get sent
    console.log('Fields sent:', Object.keys(fullPayload as Record<string, unknown>))
    
    // Check for unexpected fields
    const expectedFields = [
      'firstName', 'lastName', 'email', 'phone', 'mobile',
      'title', 'company', 'accountName', 'website', 'description',
      'status', 'source'
    ]
    
    const unexpectedFields = Object.keys(fullPayload as Record<string, unknown>).filter(
      field => !expectedFields.includes(field)
    )
    
    if (unexpectedFields.length > 0) {
      console.error('UNEXPECTED FIELDS FOUND:', unexpectedFields)
    }
  })
})