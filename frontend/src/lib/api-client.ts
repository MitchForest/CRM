import axios, { type AxiosError, type AxiosInstance } from 'axios'
import type { 
  LoginResponse, 
  ApiResponse,
  ListResponse,
  QueryParams,
  Account,
  Contact,
  Lead,
  Task,
  Opportunity,
  Call,
  Meeting,
  Note,
  Case
} from '@/types/api.generated'
import type {
  DashboardMetrics,
  PipelineData,
  ActivityMetrics,
  CaseMetrics
} from '@/types/phase2.types'
import {
  transformFromJsonApi,
  transformManyFromJsonApi,
  transformToJsonApiDocument,
  extractPaginationMeta,
  transformJsonApiErrors,
  buildJsonApiFilters,
  buildJsonApiSort,
  buildJsonApiPagination,
  type JsonApiError
} from './api-transformers'
import { getStoredAuth, setStoredAuth, clearStoredAuth } from '@/stores/auth-store'

class ApiClient {
  private client: AxiosInstance
  private customClient: AxiosInstance // For Phase 2 custom API
  private refreshingToken: Promise<string> | null = null
  private customApiToken: string | null = null

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
      baseURL: '/api', // Use relative URL to work with Vite proxy
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    })

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
        if (this.customApiToken) {
          config.headers.Authorization = `Bearer ${this.customApiToken}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Response interceptor
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        const originalRequest = error.config

        if (error.response?.status === 401 && originalRequest) {
          // Token expired, try to refresh
          if (!this.refreshingToken) {
            this.refreshingToken = this.refreshToken()
          }

          try {
            const newToken = await this.refreshingToken
            originalRequest.headers.Authorization = `Bearer ${newToken}`
            return this.client(originalRequest)
          } catch (refreshError) {
            // Refresh failed, logout
            clearStoredAuth()
            window.location.href = '/login'
            return Promise.reject(refreshError)
          } finally {
            this.refreshingToken = null
          }
        }

        // Transform JSON:API errors to our format
        if (error.response?.data && typeof error.response.data === 'object') {
          console.error('API Error Response:', {
            status: error.response.status,
            data: error.response.data,
            headers: error.response.headers
          })
          const jsonApiError = error.response.data as Record<string, unknown>
          if (jsonApiError['errors'] && Array.isArray(jsonApiError['errors'])) {
            const transformedErrorData = transformJsonApiErrors(jsonApiError['errors'] as JsonApiError[])
            const transformedError = {
              ...error,
              response: {
                ...error.response,
                data: {
                  error: transformedErrorData.message,
                  code: transformedErrorData.code,
                  details: transformedErrorData.details
                }
              }
            }
            return Promise.reject(transformedError)
          }
        }

        return Promise.reject(error)
      }
    )
  }

  private async refreshToken(): Promise<string> {
    const auth = getStoredAuth()
    if (!auth?.refreshToken) {
      throw new Error('No refresh token')
    }

    // SuiteCRM v8 uses OAuth2 refresh grant
    const params = new URLSearchParams()
    params.append('grant_type', 'refresh_token')
    params.append('client_id', 'suitecrm_client')
    params.append('client_secret', 'secret123')
    params.append('refresh_token', auth.refreshToken)
    
    const response = await axios.post(
      '/Api/access_token', // Use relative URL to work with Vite proxy
      params,
      {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      }
    )
    
    if (!response.data.access_token) {
      throw new Error('Failed to refresh token')
    }

    const accessToken = response.data.access_token
    const refreshToken = response.data.refresh_token || auth.refreshToken
    
    // Update stored auth using the helper function
    setStoredAuth({
      accessToken,
      refreshToken,
      user: auth.user
    })
    
    return accessToken
  }

  // Initialize custom API authentication
  private async initCustomApiAuth(username: string, password: string): Promise<void> {
    try {
      const response = await this.customClient.post('/auth/login', {
        username,
        password
      })
      
      if (response.data?.accessToken) {
        this.customApiToken = response.data.accessToken
      }
    } catch (error) {
      console.error('Failed to authenticate with custom API:', error)
    }
  }

  // Auth methods
  async login(username: string, password: string): Promise<ApiResponse<LoginResponse>> {
    // SuiteCRM v8 uses OAuth2 password grant at /Api/access_token
    const params = new URLSearchParams()
    params.append('grant_type', 'password')
    params.append('client_id', 'suitecrm_client')
    params.append('client_secret', 'secret123')
    params.append('username', username)
    params.append('password', password)
    
    try {
      const response = await axios.post(
        '/Api/access_token', // Use relative URL to work with Vite proxy
        params,
        {
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          }
        }
      )
      
      // Also authenticate with custom API for Phase 2 features
      await this.initCustomApiAuth(username, password)
      
      // Transform OAuth2 response to our format
      return {
        success: true,
        data: {
          accessToken: response.data.access_token,
          refreshToken: response.data.refresh_token,
          expiresIn: response.data.expires_in,
          tokenType: response.data.token_type || 'Bearer',
          user: {
            id: response.data.user_id || 'apiuser',
            username: username,
            email: response.data.email || `${username}@example.com`,
            firstName: response.data.first_name || username,
            lastName: response.data.last_name || 'User'
          }
        }
      }
    } catch (error) {
      console.error('Login error:', error)
      if (axios.isAxiosError(error)) {
        return {
          success: false,
          error: {
            error: error.response?.data?.error || 'Login failed',
            code: error.response?.status?.toString() || 'UNKNOWN',
            details: error.response?.data || { message: 'Authentication failed' }
          }
        }
      }
      return {
        success: false,
        error: {
          error: 'Login failed',
          code: 'UNKNOWN',
          details: { message: 'An unexpected error occurred' }
        }
      }
    }
  }

  async logout(): Promise<void> {
    await this.client.post('/logout')
  }


  // Account methods
  async getAccounts(params?: QueryParams): Promise<ListResponse<Account>> {
    const queryParams = {
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.search ? buildJsonApiFilters({ name: { operator: 'like', value: `%${params.search}%` } }) : {}),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    const response = await this.client.get('/module/Accounts', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Account>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getAccount(id: string): Promise<ApiResponse<Account>> {
    const response = await this.client.get(`/module/Accounts/${id}`)
    
    return {
      data: transformFromJsonApi<Account>(response.data.data),
      success: true
    }
  }

  async createAccount(data: Partial<Account>): Promise<ApiResponse<Account>> {
    const jsonApiData = transformToJsonApiDocument('Accounts', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Account>(response.data.data),
      success: true
    }
  }

  async updateAccount(id: string, data: Partial<Account>): Promise<ApiResponse<Account>> {
    const jsonApiData = transformToJsonApiDocument('Accounts', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Account>(response.data.data),
      success: true
    }
  }

  async deleteAccount(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Accounts/${id}`)
    
    return {
      success: true
    }
  }

  // Contact methods
  async getContacts(params?: QueryParams): Promise<ListResponse<Contact>> {
    const queryParams: Record<string, string> = {
      ...buildJsonApiPagination(params?.page, params?.pageSize)
    }
    
    const sortParam = params?.sort ? buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) : undefined
    if (sortParam) {
      queryParams['sort'] = sortParam
    }
    
    if (params?.search) {
      queryParams['filter[operator]'] = 'or'
      queryParams['filter[first_name][like]'] = `%${params.search}%`
      queryParams['filter[last_name][like]'] = `%${params.search}%`
      queryParams['filter[email1][like]'] = `%${params.search}%`
    }
    
    const response = await this.client.get('/module/Contacts', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Contact>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getContact(id: string): Promise<ApiResponse<Contact>> {
    const response = await this.client.get(`/module/Contacts/${id}`)
    
    return {
      data: transformFromJsonApi<Contact>(response.data.data),
      success: true
    }
  }

  async createContact(data: Partial<Contact>): Promise<ApiResponse<Contact>> {
    const jsonApiData = transformToJsonApiDocument('Contacts', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Contact>(response.data.data),
      success: true
    }
  }

  async updateContact(id: string, data: Partial<Contact>): Promise<ApiResponse<Contact>> {
    const jsonApiData = transformToJsonApiDocument('Contacts', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Contact>(response.data.data),
      success: true
    }
  }

  async deleteContact(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Contacts/${id}`)
    
    return {
      success: true
    }
  }


  // Lead methods
  async getLeads(params?: QueryParams): Promise<ListResponse<Lead>> {
    const queryParams: Record<string, string> = {
      ...buildJsonApiPagination(params?.page, params?.pageSize)
    }
    
    const sortParam = params?.sort ? buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) : undefined
    if (sortParam) {
      queryParams['sort'] = sortParam
    }
    
    if (params?.search) {
      queryParams['filter[operator]'] = 'or'
      queryParams['filter[first_name][like]'] = `%${params.search}%`
      queryParams['filter[last_name][like]'] = `%${params.search}%`
      queryParams['filter[email1][like]'] = `%${params.search}%`
    }
    
    const response = await this.client.get('/module/Leads', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Lead>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getLead(id: string): Promise<ApiResponse<Lead>> {
    const response = await this.client.get(`/module/Leads/${id}`)
    
    return {
      data: transformFromJsonApi<Lead>(response.data.data),
      success: true
    }
  }

  async createLead(data: Partial<Lead>): Promise<ApiResponse<Lead>> {
    const jsonApiData = transformToJsonApiDocument('Leads', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Lead>(response.data.data),
      success: true
    }
  }

  async updateLead(id: string, data: Partial<Lead>): Promise<ApiResponse<Lead>> {
    const jsonApiData = transformToJsonApiDocument('Leads', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Lead>(response.data.data),
      success: true
    }
  }

  async convertLead(id: string): Promise<ApiResponse<{ contactId: string }>> {
    // Lead conversion might need a custom endpoint or use the standard update
    // For now, we'll update the status to 'Converted'
    const jsonApiData = transformToJsonApiDocument('Leads', { id, status: 'Converted' })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    // In a real implementation, you'd need to handle the contact creation
    // and relationship linking according to SuiteCRM's lead conversion logic
    return {
      data: { contactId: response.data.data?.relationships?.contact?.data?.id || '' },
      success: true
    }
  }

  async deleteLead(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Leads/${id}`)
    
    return {
      success: true
    }
  }

  // Task methods
  async getTasks(params?: QueryParams): Promise<ListResponse<Task>> {
    const queryParams = {
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.search ? buildJsonApiFilters({ name: { operator: 'like', value: `%${params.search}%` } }) : {}),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    const response = await this.client.get('/module/Tasks', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Task>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getTask(id: string): Promise<ApiResponse<Task>> {
    const response = await this.client.get(`/module/Tasks/${id}`)
    
    return {
      data: transformFromJsonApi<Task>(response.data.data),
      success: true
    }
  }

  async createTask(data: Partial<Task>): Promise<ApiResponse<Task>> {
    const jsonApiData = transformToJsonApiDocument('Tasks', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Task>(response.data.data),
      success: true
    }
  }

  async updateTask(id: string, data: Partial<Task>): Promise<ApiResponse<Task>> {
    const jsonApiData = transformToJsonApiDocument('Tasks', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Task>(response.data.data),
      success: true
    }
  }

  // Opportunity methods
  async getOpportunities(params?: QueryParams): Promise<ListResponse<Opportunity>> {
    const queryParams = {
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.search ? buildJsonApiFilters({ name: { operator: 'like', value: `%${params.search}%` } }) : {}),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    const response = await this.client.get('/module/Opportunities', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Opportunity>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getOpportunity(id: string): Promise<ApiResponse<Opportunity>> {
    const response = await this.client.get(`/module/Opportunities/${id}`)
    
    return {
      data: transformFromJsonApi<Opportunity>(response.data.data),
      success: true
    }
  }

  async createOpportunity(data: Partial<Opportunity>): Promise<ApiResponse<Opportunity>> {
    const jsonApiData = transformToJsonApiDocument('Opportunities', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Opportunity>(response.data.data),
      success: true
    }
  }

  async updateOpportunity(id: string, data: Partial<Opportunity>): Promise<ApiResponse<Opportunity>> {
    const jsonApiData = transformToJsonApiDocument('Opportunities', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Opportunity>(response.data.data),
      success: true
    }
  }

  async deleteOpportunity(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Opportunities/${id}`)
    
    return {
      success: true
    }
  }

  async updateOpportunityStage(id: string, stage: string): Promise<ApiResponse<Opportunity>> {
    return this.updateOpportunity(id, { salesStage: stage })
  }

  // Call methods
  async getCalls(params?: QueryParams): Promise<ListResponse<Call>> {
    const queryParams = {
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.search ? buildJsonApiFilters({ name: { operator: 'like', value: `%${params.search}%` } }) : {}),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    const response = await this.client.get('/module/Calls', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Call>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getCall(id: string): Promise<ApiResponse<Call>> {
    const response = await this.client.get(`/module/Calls/${id}`)
    
    return {
      data: transformFromJsonApi<Call>(response.data.data),
      success: true
    }
  }

  async createCall(data: Partial<Call>): Promise<ApiResponse<Call>> {
    const jsonApiData = transformToJsonApiDocument('Calls', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Call>(response.data.data),
      success: true
    }
  }

  async updateCall(id: string, data: Partial<Call>): Promise<ApiResponse<Call>> {
    const jsonApiData = transformToJsonApiDocument('Calls', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Call>(response.data.data),
      success: true
    }
  }

  async deleteCall(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Calls/${id}`)
    
    return {
      success: true
    }
  }

  // Meeting methods
  async getMeetings(params?: QueryParams): Promise<ListResponse<Meeting>> {
    const queryParams = {
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.search ? buildJsonApiFilters({ name: { operator: 'like', value: `%${params.search}%` } }) : {}),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    const response = await this.client.get('/module/Meetings', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Meeting>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getMeeting(id: string): Promise<ApiResponse<Meeting>> {
    const response = await this.client.get(`/module/Meetings/${id}`)
    
    return {
      data: transformFromJsonApi<Meeting>(response.data.data),
      success: true
    }
  }

  async createMeeting(data: Partial<Meeting>): Promise<ApiResponse<Meeting>> {
    const jsonApiData = transformToJsonApiDocument('Meetings', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Meeting>(response.data.data),
      success: true
    }
  }

  async updateMeeting(id: string, data: Partial<Meeting>): Promise<ApiResponse<Meeting>> {
    const jsonApiData = transformToJsonApiDocument('Meetings', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Meeting>(response.data.data),
      success: true
    }
  }

  async deleteMeeting(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Meetings/${id}`)
    
    return {
      success: true
    }
  }

  // Note methods
  async getNotes(params?: QueryParams): Promise<ListResponse<Note>> {
    const queryParams = {
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.search ? buildJsonApiFilters({ name: { operator: 'like', value: `%${params.search}%` } }) : {}),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    const response = await this.client.get('/module/Notes', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Note>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getNote(id: string): Promise<ApiResponse<Note>> {
    const response = await this.client.get(`/module/Notes/${id}`)
    
    return {
      data: transformFromJsonApi<Note>(response.data.data),
      success: true
    }
  }

  async createNote(data: Partial<Note>): Promise<ApiResponse<Note>> {
    const jsonApiData = transformToJsonApiDocument('Notes', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Note>(response.data.data),
      success: true
    }
  }

  async updateNote(id: string, data: Partial<Note>): Promise<ApiResponse<Note>> {
    const jsonApiData = transformToJsonApiDocument('Notes', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Note>(response.data.data),
      success: true
    }
  }

  async deleteNote(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Notes/${id}`)
    
    return {
      success: true
    }
  }

  // Case methods
  async getCases(params?: QueryParams & { status?: string; priority?: string }): Promise<ListResponse<Case>> {
    const queryParams: Record<string, string> = {
      ...buildJsonApiPagination(params?.page, params?.pageSize)
    }
    
    // SuiteCRM V8 API requires operator for each filter
    if (params?.status) {
      queryParams['filter[status][eq]'] = params.status
    }
    
    if (params?.priority) {
      queryParams['filter[priority][eq]'] = params.priority
    }
    
    if (params?.search) {
      queryParams['filter[name][like]'] = `%${params.search}%`
    }
    
    if (params?.sort) {
      queryParams['sort'] = buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) || ''
    }
    
    const response = await this.client.get('/module/Cases', { params: queryParams })
    
    return {
      data: transformManyFromJsonApi<Case>(response.data.data || []),
      pagination: extractPaginationMeta(response.data)
    }
  }

  async getCase(id: string): Promise<ApiResponse<Case>> {
    const response = await this.client.get(`/module/Cases/${id}`)
    
    return {
      data: transformFromJsonApi<Case>(response.data.data),
      success: true
    }
  }

  async createCase(data: Partial<Case>): Promise<ApiResponse<Case>> {
    const jsonApiData = transformToJsonApiDocument('Cases', data, false)
    
    const response = await this.client.post('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Case>(response.data.data),
      success: true
    }
  }

  async updateCase(id: string, data: Partial<Case>): Promise<ApiResponse<Case>> {
    const jsonApiData = transformToJsonApiDocument('Cases', { ...data, id })
    
    const response = await this.client.patch('/module', jsonApiData)
    
    return {
      data: transformFromJsonApi<Case>(response.data.data),
      success: true
    }
  }

  async deleteCase(id: string): Promise<ApiResponse<void>> {
    await this.client.delete(`/module/Cases/${id}`)
    
    return {
      success: true
    }
  }

  // Dashboard methods - Phase 2 Custom API
  async getDashboardMetrics(): Promise<ApiResponse<DashboardMetrics>> {
    try {
      const response = await this.customClient.get('/dashboard/metrics')
      return {
        success: true,
        data: response.data.data
      }
    } catch (error) {
      console.error('Failed to fetch dashboard metrics:', error)
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
      const response = await this.customClient.get('/dashboard/pipeline')
      return {
        success: true,
        data: response.data.data
      }
    } catch (error) {
      console.error('Failed to fetch pipeline data:', error)
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
      const response = await this.customClient.get('/dashboard/activities')
      return {
        success: true,
        data: response.data.data
      }
    } catch (error) {
      console.error('Failed to fetch activity metrics:', error)
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
      const response = await this.customClient.get('/dashboard/cases')
      return {
        success: true,
        data: response.data.data
      }
    } catch (error) {
      console.error('Failed to fetch case metrics:', error)
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

  // Legacy method for backward compatibility
  async getDashboardStats(): Promise<ApiResponse<{
    totalLeads: number
    totalAccounts: number
    newLeadsToday: number
    pipelineValue: number
  }>> {
    const response = await this.getDashboardMetrics()
    if (!response.success || !response.data) {
      return {
        success: false,
        data: {
          totalLeads: 0,
          totalAccounts: 0,
          newLeadsToday: 0,
          pipelineValue: 0
        }
      }
    }
    
    return {
      success: true,
      data: {
        totalLeads: response.data.totalLeads,
        totalAccounts: response.data.totalAccounts,
        newLeadsToday: response.data.newLeadsToday,
        pipelineValue: response.data.pipelineValue
      }
    }
  }
}

export const apiClient = new ApiClient()