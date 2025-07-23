import axios, { type AxiosError, type AxiosInstance } from 'axios'
import type { 
  LoginRequest, 
  LoginResponse, 
  RefreshTokenRequest,
  RefreshTokenResponse,
  ApiResponse,
  ListResponse,
  QueryParams,
  Account,
  Contact,
  Lead,
  Task
} from '@/types/api.generated'
import {
  transformFromJsonApi,
  transformManyFromJsonApi,
  transformToJsonApiDocument,
  extractPaginationMeta,
  transformJsonApiErrors,
  buildJsonApiFilters,
  buildJsonApiSort,
  buildJsonApiPagination
} from './api-transformers'

// Get auth token from localStorage
const getStoredAuth = () => {
  const stored = localStorage.getItem('auth-storage')
  if (!stored) return null
  try {
    const parsed = JSON.parse(stored)
    return parsed.state
  } catch {
    return null
  }
}

class ApiClient {
  private client: AxiosInstance
  private refreshingToken: Promise<string> | null = null

  constructor() {
    this.client = axios.create({
      baseURL: '/api/v8', // Uses Vite proxy to forward to backend v8 API
      headers: {
        'Content-Type': 'application/vnd.api+json',
        'Accept': 'application/vnd.api+json',
      },
    })

    // Request interceptor
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
            localStorage.removeItem('auth-storage')
            window.location.href = '/login'
            return Promise.reject(refreshError)
          } finally {
            this.refreshingToken = null
          }
        }

        // Transform JSON:API errors to our format
        if (error.response?.data && typeof error.response.data === 'object') {
          const jsonApiError = error.response.data as Record<string, unknown>
          if (jsonApiError.errors && Array.isArray(jsonApiError.errors)) {
            const transformedErrorData = transformJsonApiErrors(jsonApiError.errors)
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

    const response = await this.client.post<ApiResponse<RefreshTokenResponse>>(
      '/token/refresh',
      { refreshToken: auth.refreshToken } as RefreshTokenRequest
    )
    
    if (!response.data.success || !response.data.data) {
      throw new Error('Failed to refresh token')
    }

    const { accessToken, refreshToken } = response.data.data
    
    // Update stored auth
    const stored = localStorage.getItem('auth-storage')
    if (stored) {
      const parsed = JSON.parse(stored)
      parsed.state.accessToken = accessToken
      parsed.state.refreshToken = refreshToken
      localStorage.setItem('auth-storage', JSON.stringify(parsed))
    }
    
    return accessToken
  }

  // Auth methods
  async login(username: string, password: string): Promise<ApiResponse<LoginResponse>> {
    const response = await this.client.post<ApiResponse<LoginResponse>>(
      '/login',
      { username, password } as LoginRequest
    )
    return response.data
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
    
    const response = await this.client.post('/module/Accounts', jsonApiData)
    
    return {
      data: transformFromJsonApi<Account>(response.data.data),
      success: true
    }
  }

  async updateAccount(id: string, data: Partial<Account>): Promise<ApiResponse<Account>> {
    const jsonApiData = transformToJsonApiDocument('Accounts', { ...data, id })
    
    const response = await this.client.patch(`/module/Accounts/${id}`, jsonApiData)
    
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
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    if (params?.search) {
      queryParams['filter[operator]'] = 'or'
      queryParams['filter[first_name][like]'] = `%${params.search}%`
      queryParams['filter[last_name][like]'] = `%${params.search}%`
      queryParams['filter[email][like]'] = `%${params.search}%`
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
    
    const response = await this.client.post('/module/Contacts', jsonApiData)
    
    return {
      data: transformFromJsonApi<Contact>(response.data.data),
      success: true
    }
  }

  async updateContact(id: string, data: Partial<Contact>): Promise<ApiResponse<Contact>> {
    const jsonApiData = transformToJsonApiDocument('Contacts', { ...data, id })
    
    const response = await this.client.patch(`/module/Contacts/${id}`, jsonApiData)
    
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
      ...buildJsonApiPagination(params?.page, params?.pageSize),
      ...(params?.sort ? { sort: buildJsonApiSort(params.sort[0]?.field, params.sort[0]?.direction) } : {})
    }
    
    if (params?.search) {
      queryParams['filter[operator]'] = 'or'
      queryParams['filter[first_name][like]'] = `%${params.search}%`
      queryParams['filter[last_name][like]'] = `%${params.search}%`
      queryParams['filter[email][like]'] = `%${params.search}%`
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
    
    const response = await this.client.post('/module/Leads', jsonApiData)
    
    return {
      data: transformFromJsonApi<Lead>(response.data.data),
      success: true
    }
  }

  async updateLead(id: string, data: Partial<Lead>): Promise<ApiResponse<Lead>> {
    const jsonApiData = transformToJsonApiDocument('Leads', { ...data, id })
    
    const response = await this.client.patch(`/module/Leads/${id}`, jsonApiData)
    
    return {
      data: transformFromJsonApi<Lead>(response.data.data),
      success: true
    }
  }

  async convertLead(id: string): Promise<ApiResponse<{ contactId: string }>> {
    // Lead conversion might need a custom endpoint or use the standard update
    // For now, we'll update the status to 'Converted'
    const jsonApiData = transformToJsonApiDocument('Leads', { id, status: 'Converted' })
    
    const response = await this.client.patch(`/module/Leads/${id}`, jsonApiData)
    
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
    
    const response = await this.client.post('/module/Tasks', jsonApiData)
    
    return {
      data: transformFromJsonApi<Task>(response.data.data),
      success: true
    }
  }

  async updateTask(id: string, data: Partial<Task>): Promise<ApiResponse<Task>> {
    const jsonApiData = transformToJsonApiDocument('Tasks', { ...data, id })
    
    const response = await this.client.patch(`/module/Tasks/${id}`, jsonApiData)
    
    return {
      data: transformFromJsonApi<Task>(response.data.data),
      success: true
    }
  }

  // Dashboard methods
  async getDashboardStats(): Promise<ApiResponse<{
    totalLeads: number
    totalAccounts: number
    newLeadsToday: number
    pipelineValue: number
  }>> {
    // Dashboard stats would typically require multiple API calls or a custom endpoint
    // For now, we'll implement a basic version using the list endpoints
    try {
      const today = new Date().toISOString().split('T')[0]
      
      const [leadsResponse, accountsResponse, todayLeadsResponse] = await Promise.all([
        this.client.get('/module/Leads', { params: { 'page[size]': 1 } }),
        this.client.get('/module/Accounts', { params: { 'page[size]': 1 } }),
        this.client.get('/module/Leads', { 
          params: { 
            'page[size]': 1,
            'filter[date_entered][gte]': today
          } 
        })
      ])
      
      return {
        data: {
          totalLeads: leadsResponse.data.meta?.['total-count'] || 0,
          totalAccounts: accountsResponse.data.meta?.['total-count'] || 0,
          newLeadsToday: todayLeadsResponse.data.meta?.['total-count'] || 0,
          pipelineValue: 0 // Would need to sum opportunity amounts
        },
        success: true
      }
    } catch {
      return {
        data: {
          totalLeads: 0,
          totalAccounts: 0,
          newLeadsToday: 0,
          pipelineValue: 0
        },
        success: false
      }
    }
  }
}

export const apiClient = new ApiClient()