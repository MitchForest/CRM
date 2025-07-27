import axios, { type AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'

// Extend AxiosRequestConfig to include retry flag
declare module 'axios' {
  export interface AxiosRequestConfig {
    _retry?: boolean
  }
}
import type { 
  LoginResponse, 
  ApiResponse,
  ListResponse,
  QueryParams,
  DashboardMetrics,
  PipelineData,
  ActivityMetrics,
  CaseMetrics,
  TimelineResponse
} from '@/types/api.types'

import type {
  LeadDB,
  ContactDB,
  OpportunityDB,
  CaseDB,
  AccountDB,
  TaskDB,
  CallDB,
  MeetingDB,
  NoteDB
} from '@/types/database.types'

// Extended login response to handle dual authentication
// interface ExtendedLoginResponse extends LoginResponse {
//   suiteOAuthToken?: string | null
// }
// JSON:API utilities removed - using direct REST API
import { getStoredAuth, setStoredAuth, clearStoredAuth } from '@/stores/auth-store'

class ApiClient {
  private client: AxiosInstance
  private customClient: AxiosInstance // For Phase 2 custom API
  // @ts-expect-error - Used in interceptors
  private customApiToken: string | null = null

  // Unused method - kept for potential future use
  // private createPagination(page: number, pageSize: number, totalCount: number) {
  //   const totalPages = Math.ceil(totalCount / pageSize)
  //   return {
  //     page,
  //     pageSize,
  //     totalPages,
  //     totalCount,
  //     hasNext: page < totalPages,
  //     hasPrevious: page > 1
  //   }
  // }

  constructor() {
    // SuiteCRM V8 API client
    this.client = axios.create({
      baseURL: '/Api/V8', // Use relative URL to work with Vite proxy
      headers: {
        'Content-Type': 'application/vnd.api+json',
        'Accept': 'application/vnd.api+json',
      },
    })

    // Custom API client for Phase 2 features
    this.customClient = axios.create({
      baseURL: '/api', // Correct backend API base URL
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    })
    
    // Initialize tokens from storage on construction
    this.initializeFromStorage()

    // Request interceptor for V8 API
    this.client.interceptors.request.use(
      (config) => {
        const auth = getStoredAuth()
        if (auth?.accessToken) {
          config.headers.Authorization = `Bearer ${auth.accessToken}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Request interceptor for Custom API
    this.customClient.interceptors.request.use(
      (config) => {
        // Get auth from store
        const auth = getStoredAuth()
        if (auth?.accessToken) {
          // Always set the token if we have one
          this.customApiToken = auth.accessToken
          config.headers.Authorization = `Bearer ${auth.accessToken}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Response interceptor for custom API - DEMO MODE
    this.customClient.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        // In demo mode, just log errors but don't redirect
        console.log('API Error (ignored in demo):', error.response?.status, error.config?.url)
        return Promise.reject(error)
      }
    )

    // Response interceptor for V8 API - DEMO MODE
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        // In demo mode, just log errors but don't redirect
        console.log('V8 API Error (ignored in demo):', error.response?.status, error.config?.url)
        
        // Return empty data for failed requests
        if (error.config?.url?.includes('/module/')) {
          return {
            data: {
              data: [],
              meta: {
                'total-pages': 1,
                'records-on-this-page': 0
              }
            }
          }
        }
        
        return Promise.reject(error)
      }
    )
  }
  
  private initializeFromStorage() {
    try {
      const auth = getStoredAuth()
      if (auth?.accessToken && auth.accessToken.includes('.')) {
        // This is a JWT token from custom API
        this.customApiToken = auth.accessToken
      }
    } catch {
      console.error('Failed to initialize auth from storage')
    }
  }

  // Unused method - kept for potential future use
  // private async refreshAccessToken(): Promise<string> {
  //   const auth = getStoredAuth()
  //   if (!auth?.refreshToken) {
  //     throw new Error('No refresh token')
  //   }
  //   
  //   // Check if this is a JWT refresh token (custom API)
  //   if (auth.refreshToken.includes('.')) {
  //     // Refresh custom API token
  //     try {
  //       const response = await this.customClient.post('/auth/refresh', {
  //         refreshToken: auth.refreshToken
  //       })
  //       
  //       if (response.data?.accessToken) {
  //         const newAccessToken = response.data.accessToken
  //         this.customApiToken = newAccessToken
  //         
  //         // Update stored auth
  //         setStoredAuth({
  //           accessToken: newAccessToken,
  //           refreshToken: auth.refreshToken,
  //           user: auth.user
  //         })
  //         
  //         return newAccessToken
  //       }
  //       throw new Error('No access token in refresh response')
  //     } catch {
  //       console.error('Failed to refresh custom API token:', error)
  //       throw error
  //     }
  //   } else {
  //     // Fallback to OAuth refresh for legacy tokens
  //     const params = new URLSearchParams()
  //     params.append('grant_type', 'refresh_token')
  //     params.append('client_id', 'suitecrm_client')
  //     params.append('client_secret', 'secret123')
  //     params.append('refresh_token', auth.refreshToken)
  //     
  //     const response = await axios.post(
  //       '/Api/access_token', // Use relative URL to work with Vite proxy
  //       params,
  //       {
  //         headers: {
  //           'Content-Type': 'application/x-www-form-urlencoded'
  //         }
  //       }
  //     )
  //     
  //     if (!response.data.access_token) {
  //       throw new Error('Failed to refresh token')
  //     }
  //
  //     const accessToken = response.data.access_token
  //     const refreshToken = response.data.refresh_token || auth.refreshToken
  //     
  //     // Update stored auth using the helper function
  //     setStoredAuth({
  //       accessToken,
  //       refreshToken,
  //       user: auth.user
  //     })
  //     
  //     return accessToken
  //   }
  // }


  // Auth methods
  async login(username: string, password: string): Promise<ApiResponse<LoginResponse>> {
    try {
      const response = await this.customClient.post('/auth/login', {
        email: username, // API expects email field
        password
      })
      
      const data = response.data?.data || response.data
      
      if (data?.access_token) {
        // Store auth data
        this.customApiToken = data.access_token
        setStoredAuth({
          accessToken: data.access_token,
          refreshToken: data.refresh_token,
          user: data.user
        })
        
        return {
          success: true,
          data: {
            accessToken: data.access_token,
            refreshToken: data.refresh_token,
            // expiresIn: data.expiresIn || 900, // Not included in LoginResponse type
            // tokenType: data.tokenType || 'Bearer',
            user: data.user
          }
        }
      }
      
      throw new Error('Invalid response from login')
    } catch (error) {
      console.error('Login error:', error)
      return {
        success: false,
        error: {
          error: error instanceof Error ? error.message : 'Login failed',
          code: 'LOGIN_FAILED',
          details: { message: error instanceof Error ? error.message : 'Unknown error' }
        }
      }
    }
  }

  async logout(): Promise<void> {
    try {
      await this.customClient.post('/auth/logout')
    } catch {
      console.error('Logout error')
    } finally {
      // Always clear local auth state
      this.customApiToken = null
      clearStoredAuth()
    }
  }


  // Account methods
  async getAccounts(params?: QueryParams): Promise<ListResponse<AccountDB>> {
    try {
      const response = await this.customClient.get('/crm/accounts', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch accounts')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getAccount(id: string): Promise<ApiResponse<AccountDB>> {
    try {
      const response = await this.customClient.get(`/crm/accounts/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createAccount(data: Partial<AccountDB>): Promise<ApiResponse<AccountDB>> {
    try {
      const response = await this.customClient.post('/crm/accounts', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateAccount(id: string, data: Partial<AccountDB>): Promise<ApiResponse<AccountDB>> {
    try {
      const response = await this.customClient.put(`/crm/accounts/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteAccount(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/accounts/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  // Contact methods
  async getContacts(params?: QueryParams): Promise<ListResponse<ContactDB>> {
    try {
      const response = await this.customClient.get('/crm/contacts', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch contacts')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getContact(id: string): Promise<ApiResponse<ContactDB>> {
    try {
      const response = await this.customClient.get(`/crm/contacts/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createContact(data: Partial<ContactDB>): Promise<ApiResponse<ContactDB>> {
    try {
      const response = await this.customClient.post('/crm/contacts', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateContact(id: string, data: Partial<ContactDB>): Promise<ApiResponse<ContactDB>> {
    try {
      const response = await this.customClient.put(`/crm/contacts/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteContact(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/contacts/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }


  // Lead methods
  async getLeads(params?: QueryParams): Promise<ListResponse<LeadDB>> {
    try {
      const response = await this.customClient.get('/crm/leads', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch leads')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getLead(id: string): Promise<ApiResponse<LeadDB>> {
    try {
      const response = await this.customClient.get(`/crm/leads/${id}`)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to fetch lead')
      return {
        success: false,
        error: {
          error: 'Failed to fetch lead',
          code: 'LEAD_ERROR',
          details: { message: 'Unable to load lead data' }
        }
      }
    }
  }

  async getLeadTracking(id: string): Promise<ApiResponse<any>> {
    try {
      const response = await this.customClient.get(`/crm/leads/${id}/tracking`)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to fetch lead tracking data')
      return {
        success: false,
        error: {
          error: 'Failed to fetch tracking data',
          code: 'TRACKING_ERROR',
          details: { message: 'Unable to load tracking data' }
        }
      }
    }
  }

  async createLead(data: Partial<LeadDB>): Promise<ApiResponse<LeadDB>> {
    try {
      const response = await this.customClient.post('/crm/leads', data)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to create lead')
      return {
        success: false,
        error: {
          error: 'Failed to create lead',
          code: 'LEAD_CREATE_ERROR',
          details: { message: 'Unable to create lead' }
        }
      }
    }
  }

  async updateLead(id: string, data: Partial<LeadDB>): Promise<ApiResponse<LeadDB>> {
    try {
      const response = await this.customClient.put(`/crm/leads/${id}`, data)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to update lead')
      return {
        success: false,
        error: {
          error: 'Failed to update lead',
          code: 'LEAD_UPDATE_ERROR',
          details: { message: 'Unable to update lead' }
        }
      }
    }
  }

  async convertLead(id: string): Promise<ApiResponse<{ contactId: string }>> {
    try {
      const response = await this.customClient.post(`/crm/leads/${id}/convert`)
      return {
        data: { contactId: response.data.contactId || '' },
        success: true
      }
    } catch {
      console.error('Failed to convert lead')
      return {
        success: false,
        error: {
          error: 'Failed to convert lead',
          code: 'LEAD_CONVERT_ERROR',
          details: { message: 'Unable to convert lead' }
        }
      }
    }
  }

  async deleteLead(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/leads/${id}`)
      return {
        success: true
      }
    } catch {
      console.error('Failed to delete lead')
      return {
        success: false,
        error: {
          error: 'Failed to delete lead',
          code: 'LEAD_DELETE_ERROR',
          details: { message: 'Unable to delete lead' }
        }
      }
    }
  }

  async getLeadTimeline(leadId: string, params?: { limit?: number; offset?: number }): Promise<ApiResponse<TimelineResponse>> {
    try {
      const queryParams = new URLSearchParams()
      if (params?.limit) queryParams.append('limit', params.limit.toString())
      if (params?.offset) queryParams.append('offset', params.offset.toString())
      
      const url = `/crm/leads/${leadId}/timeline${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      const response = await this.customClient.get(url)
      
      return {
        success: true,
        data: response.data.data
      }
    } catch (error) {
      console.error('Failed to get lead timeline:', error)
      return {
        success: false,
        error: {
          error: 'Failed to get timeline',
          code: 'TIMELINE_ERROR',
          details: { message: error instanceof Error ? error.message : 'Unknown error' }
        }
      }
    }
  }

  // Task methods
  async getTasks(params?: QueryParams): Promise<ListResponse<TaskDB>> {
    try {
      const response = await this.customClient.get('/crm/tasks', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch tasks')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getTask(id: string): Promise<ApiResponse<TaskDB>> {
    try {
      const response = await this.customClient.get(`/crm/tasks/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createTask(data: Partial<TaskDB>): Promise<ApiResponse<TaskDB>> {
    try {
      const response = await this.customClient.post('/crm/tasks', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateTask(id: string, data: Partial<TaskDB>): Promise<ApiResponse<TaskDB>> {
    try {
      const response = await this.customClient.put(`/crm/tasks/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteTask(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/tasks/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  // Opportunity methods
  async getOpportunities(params?: QueryParams): Promise<ListResponse<OpportunityDB>> {
    try {
      const response = await this.customClient.get('/crm/opportunities', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch opportunities')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getOpportunity(id: string): Promise<ApiResponse<OpportunityDB>> {
    try {
      const response = await this.customClient.get(`/crm/opportunities/${id}`)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to fetch opportunity')
      return {
        success: false,
        error: {
          error: 'Failed to fetch opportunity',
          code: 'OPP_ERROR',
          details: { message: 'Unable to load opportunity data' }
        }
      }
    }
  }

  async createOpportunity(data: Partial<OpportunityDB>): Promise<ApiResponse<OpportunityDB>> {
    try {
      const response = await this.customClient.post('/crm/opportunities', data)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to create opportunity')
      return {
        success: false,
        error: {
          error: 'Failed to create opportunity',
          code: 'OPP_CREATE_ERROR',
          details: { message: 'Unable to create opportunity' }
        }
      }
    }
  }

  async updateOpportunity(id: string, data: Partial<OpportunityDB>): Promise<ApiResponse<OpportunityDB>> {
    try {
      const response = await this.customClient.put(`/crm/opportunities/${id}`, data)
      return {
        data: response.data.data,
        success: true
      }
    } catch {
      console.error('Failed to update opportunity')
      return {
        success: false,
        error: {
          error: 'Failed to update opportunity',
          code: 'OPP_UPDATE_ERROR',
          details: { message: 'Unable to update opportunity' }
        }
      }
    }
  }

  async deleteOpportunity(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/opportunities/${id}`)
      return {
        success: true
      }
    } catch {
      console.error('Failed to delete opportunity')
      return {
        success: false,
        error: {
          error: 'Failed to delete opportunity',
          code: 'OPP_DELETE_ERROR',
          details: { message: 'Unable to delete opportunity' }
        }
      }
    }
  }

  async updateOpportunityStage(id: string, stage: string): Promise<ApiResponse<OpportunityDB>> {
    return this.updateOpportunity(id, { sales_stage: stage as OpportunityDB['sales_stage'] })
  }

  // Call methods
  async getCalls(params?: QueryParams): Promise<ListResponse<CallDB>> {
    try {
      const response = await this.customClient.get('/crm/calls', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch calls')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getCall(id: string): Promise<ApiResponse<CallDB>> {
    try {
      const response = await this.customClient.get(`/crm/calls/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createCall(data: Partial<CallDB>): Promise<ApiResponse<CallDB>> {
    try {
      const response = await this.customClient.post('/crm/calls', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateCall(id: string, data: Partial<CallDB>): Promise<ApiResponse<CallDB>> {
    try {
      const response = await this.customClient.put(`/crm/calls/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteCall(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/calls/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  // Meeting methods
  async getMeetings(params?: QueryParams): Promise<ListResponse<MeetingDB>> {
    try {
      const response = await this.customClient.get('/crm/meetings', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch meetings')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getMeeting(id: string): Promise<ApiResponse<MeetingDB>> {
    try {
      const response = await this.customClient.get(`/crm/meetings/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createMeeting(data: Partial<MeetingDB>): Promise<ApiResponse<MeetingDB>> {
    try {
      const response = await this.customClient.post('/crm/meetings', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateMeeting(id: string, data: Partial<MeetingDB>): Promise<ApiResponse<MeetingDB>> {
    try {
      const response = await this.customClient.put(`/crm/meetings/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteMeeting(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/meetings/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  // Note methods
  async getNotes(params?: QueryParams): Promise<ListResponse<NoteDB>> {
    try {
      const response = await this.customClient.get('/crm/notes', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch notes')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getNote(id: string): Promise<ApiResponse<NoteDB>> {
    try {
      const response = await this.customClient.get(`/crm/notes/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createNote(data: Partial<NoteDB>): Promise<ApiResponse<NoteDB>> {
    try {
      const response = await this.customClient.post('/crm/notes', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateNote(id: string, data: Partial<NoteDB>): Promise<ApiResponse<NoteDB>> {
    try {
      const response = await this.customClient.put(`/crm/notes/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteNote(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/notes/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  // Case methods
  async getCases(params?: QueryParams & { status?: string; priority?: string }): Promise<ListResponse<CaseDB>> {
    try {
      const response = await this.customClient.get('/crm/cases', { 
        params: {
          page: params?.page || 1,
          limit: params?.limit || 10,
          search: params?.search,
          status: params?.status,
          priority: params?.priority
        }
      })
      
      return {
        data: response.data.data || [],
        pagination: response.data.pagination || {
          page: 1,
          limit: 10,
          totalPages: 1,
          total: response.data.data?.length || 0
        }
      }
    } catch {
      console.error('Failed to fetch cases')
      return {
        data: [],
        pagination: {
          page: 1,
          limit: 10,
          totalPages: 0,
          total: 0
        }
      }
    }
  }

  async getCase(id: string): Promise<ApiResponse<CaseDB>> {
    try {
      const response = await this.customClient.get(`/crm/cases/${id}`)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async createCase(data: Partial<CaseDB>): Promise<ApiResponse<CaseDB>> {
    try {
      const response = await this.customClient.post('/crm/cases', data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async updateCase(id: string, data: Partial<CaseDB>): Promise<ApiResponse<CaseDB>> {
    try {
      const response = await this.customClient.put(`/crm/cases/${id}`, data)
      return { data: response.data.data, success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  async deleteCase(id: string): Promise<ApiResponse<void>> {
    try {
      await this.customClient.delete(`/crm/cases/${id}`)
      return { success: true }
    } catch {
      return { success: false, error: { error: 'Failed', code: 'ERROR', details: {} } }
    }
  }

  // Dashboard methods - Phase 2 Custom API
  async getDashboardMetrics(): Promise<ApiResponse<DashboardMetrics>> {
    try {
      const response = await this.customClient.get('/crm/dashboard/metrics')
      return {
        success: true,
        data: response.data.data
      }
    } catch {
      console.error('Failed to fetch dashboard metrics')
      return {
        success: false,
        error: {
          error: 'Failed to fetch dashboard metrics',
          code: 'DASHBOARD_ERROR',
          details: { message: 'Unable to load dashboard data' }
        }
      }
    }
  }

  async getPipelineData(): Promise<ApiResponse<PipelineData>> {
    try {
      const response = await this.customClient.get('/crm/dashboard/pipeline')
      return {
        success: true,
        data: response.data.data
      }
    } catch {
      console.error('Failed to fetch pipeline data')
      return {
        success: false,
        error: {
          error: 'Failed to fetch pipeline data',
          code: 'PIPELINE_ERROR',
          details: { message: 'Unable to load pipeline data' }
        }
      }
    }
  }

  async getActivityMetrics(): Promise<ApiResponse<ActivityMetrics>> {
    try {
      const response = await this.customClient.get('/crm/dashboard/activities')
      return {
        success: true,
        data: response.data.data
      }
    } catch {
      console.error('Failed to fetch activity metrics')
      return {
        success: false,
        error: {
          error: 'Failed to fetch activity metrics',
          code: 'ACTIVITY_ERROR',
          details: { message: 'Unable to load activity data' }
        }
      }
    }
  }

  async getCaseMetrics(): Promise<ApiResponse<CaseMetrics>> {
    try {
      const response = await this.customClient.get('/crm/dashboard/cases')
      return {
        success: true,
        data: response.data.data
      }
    } catch {
      console.error('Failed to fetch case metrics')
      return {
        success: false,
        error: {
          error: 'Failed to fetch case metrics',
          code: 'CASE_ERROR',
          details: { message: 'Unable to load case data' }
        }
      }
    }
  }

  // Phase 3 Custom API methods
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async customGet(url: string, config?: AxiosRequestConfig): Promise<any> {
    const response = await this.customClient.get(url, config)
    return response.data
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async customPost(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<any> {
    const response = await this.customClient.post(url, data, config)
    return response.data
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async customPut(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<any> {
    const response = await this.customClient.put(url, data, config)
    return response.data
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async customDelete(url: string, config?: AxiosRequestConfig): Promise<any> {
    const response = await this.customClient.delete(url, config)
    return response.data
  }

  // Public endpoints (no auth required)
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async publicGet(url: string, config?: AxiosRequestConfig): Promise<any> {
    // Remove auth header for public endpoints
    const publicConfig = { ...config, headers: { ...config?.headers, Authorization: undefined } }
    const response = await this.customClient.get(url, publicConfig)
    return response.data
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  async publicPost(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<any> {
    // Remove auth header for public endpoints
    const publicConfig = { ...config, headers: { ...config?.headers, Authorization: undefined } }
    const response = await this.customClient.post(url, data, publicConfig)
    return response.data
  }

  // Public API methods (no authentication required)
  async submitPublicLead(data: {
    first_name: string
    last_name: string
    email: string
    phone?: string
    company?: string
    message?: string
    form_source?: string
    visitor_id?: string
    session_id?: string
  }): Promise<ApiResponse<{ lead_id: string; is_new: boolean }>> {
    try {
      // Filter out undefined and null values
      const filteredData = Object.entries(data).reduce((acc, [key, value]) => {
        if (value !== undefined && value !== null) {
          acc[key] = value;
        }
        return acc;
      }, {} as any);
      
      const response = await this.publicPost('/public/capture/lead', filteredData)
      // Backend already returns { success: true, data: {...} }
      if (response.success && response.data) {
        return {
          success: true,
          data: response.data
        }
      }
      // Fallback for unexpected response structure
      return {
        success: true,
        data: response
      }
    } catch (error) {
      console.error('Failed to submit public lead:', error)
      return {
        success: false,
        error: {
          error: 'Failed to submit form',
          code: 'FORM_SUBMIT_ERROR',
          details: { message: error instanceof Error ? error.message : 'Unknown error' }
        }
      }
    }
  }

  async requestPublicDemo(data: {
    first_name: string
    last_name: string
    email: string
    phone?: string
    company: string
    company_size?: string
    demo_date: string
    demo_time: string
    timezone?: string
    message?: string
    visitor_id?: string
    session_id?: string
  }): Promise<ApiResponse<{ lead_id: string; meeting_id: string; scheduled_time: string }>> {
    try {
      // Filter out undefined and null values
      const filteredData = Object.entries(data).reduce((acc, [key, value]) => {
        if (value !== undefined && value !== null) {
          acc[key] = value;
        }
        return acc;
      }, {} as any);
      
      const response = await this.publicPost('/public/demo-request', filteredData)
      // Backend already returns { success: true, data: {...} }
      if (response.success && response.data) {
        return {
          success: true,
          data: response.data
        }
      }
      // Fallback for unexpected response structure
      return {
        success: true,
        data: response
      }
    } catch (error) {
      console.error('Failed to request demo:', error)
      return {
        success: false,
        error: {
          error: 'Failed to schedule demo',
          code: 'DEMO_REQUEST_ERROR',
          details: { message: error instanceof Error ? error.message : 'Unknown error' }
        }
      }
    }
  }

  async submitPublicForm(formId: string, data: any): Promise<ApiResponse<any>> {
    try {
      const response = await this.publicPost(`/public/forms/${formId}/submit`, data)
      return {
        success: true,
        data: response.data
      }
    } catch (error) {
      console.error('Failed to submit form:', error)
      return {
        success: false,
        error: {
          error: 'Failed to submit form',
          code: 'FORM_SUBMIT_ERROR',
          details: { message: error instanceof Error ? error.message : 'Unknown error' }
        }
      }
    }
  }

  // Legacy method for backward compatibility
  async getDashboardStats(): Promise<ApiResponse<DashboardMetrics>> {
    const response = await this.getDashboardMetrics()
    if (!response.success || !response.data) {
      return {
        success: false,
        data: {
          total_leads: 0,
          total_accounts: 0,
          new_leads_today: 0,
          pipeline_value: 0
        }
      }
    }
    
    return {
      success: true,
      data: {
        total_leads: response.data.total_leads,
        total_accounts: response.data.total_accounts,
        new_leads_today: response.data.new_leads_today,
        pipeline_value: response.data.pipeline_value
      }
    }
  }
}

export const apiClient = new ApiClient()