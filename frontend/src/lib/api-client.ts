import axios, { AxiosError, AxiosInstance } from 'axios'
import type { 
  LoginRequest, 
  LoginResponse, 
  RefreshTokenRequest,
  RefreshTokenResponse,
  ApiResponse,
  ListResponse,
  QueryParams,
  Contact,
  Lead,
  Opportunity,
  Task,
  Case,
  Quote,
  Email,
  Call,
  Meeting,
  Note,
  Activity
} from '@/types/api.generated'

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
      baseURL: '/api', // Uses Vite proxy to forward to backend
      headers: {
        'Content-Type': 'application/json',
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
      '/auth/refresh',
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
      '/auth/login',
      { username, password } as LoginRequest
    )
    return response.data
  }

  async logout(): Promise<void> {
    await this.client.post('/auth/logout')
  }

  // Contact methods
  async getContacts(params?: QueryParams): Promise<ListResponse<Contact>> {
    const response = await this.client.get<ListResponse<Contact>>('/contacts', { params })
    return response.data
  }

  async getContact(id: string): Promise<ApiResponse<Contact>> {
    const response = await this.client.get<ApiResponse<Contact>>(`/contacts/${id}`)
    return response.data
  }

  async createContact(data: Partial<Contact>): Promise<ApiResponse<Contact>> {
    const response = await this.client.post<ApiResponse<Contact>>('/contacts', data)
    return response.data
  }

  async updateContact(id: string, data: Partial<Contact>): Promise<ApiResponse<Contact>> {
    const response = await this.client.put<ApiResponse<Contact>>(`/contacts/${id}`, data)
    return response.data
  }

  async deleteContact(id: string): Promise<ApiResponse<void>> {
    const response = await this.client.delete<ApiResponse<void>>(`/contacts/${id}`)
    return response.data
  }

  async getContactActivities(id: string, params?: QueryParams): Promise<ListResponse<Activity>> {
    const response = await this.client.get<ListResponse<Activity>>(`/contacts/${id}/activities`, { params })
    return response.data
  }

  // Lead methods
  async getLeads(params?: QueryParams): Promise<ListResponse<Lead>> {
    const response = await this.client.get<ListResponse<Lead>>('/leads', { params })
    return response.data
  }

  async getLead(id: string): Promise<ApiResponse<Lead>> {
    const response = await this.client.get<ApiResponse<Lead>>(`/leads/${id}`)
    return response.data
  }

  async createLead(data: Partial<Lead>): Promise<ApiResponse<Lead>> {
    const response = await this.client.post<ApiResponse<Lead>>('/leads', data)
    return response.data
  }

  async updateLead(id: string, data: Partial<Lead>): Promise<ApiResponse<Lead>> {
    const response = await this.client.put<ApiResponse<Lead>>(`/leads/${id}`, data)
    return response.data
  }

  async convertLead(id: string, data?: { createOpportunity?: boolean; opportunityName?: string }): Promise<ApiResponse<{ contactId: string; opportunityId?: string }>> {
    const response = await this.client.post<ApiResponse<{ contactId: string; opportunityId?: string }>>(`/leads/${id}/convert`, data)
    return response.data
  }

  // Opportunity methods
  async getOpportunities(params?: QueryParams): Promise<ListResponse<Opportunity>> {
    const response = await this.client.get<ListResponse<Opportunity>>('/opportunities', { params })
    return response.data
  }

  async getOpportunity(id: string): Promise<ApiResponse<Opportunity>> {
    const response = await this.client.get<ApiResponse<Opportunity>>(`/opportunities/${id}`)
    return response.data
  }

  async createOpportunity(data: Partial<Opportunity>): Promise<ApiResponse<Opportunity>> {
    const response = await this.client.post<ApiResponse<Opportunity>>('/opportunities', data)
    return response.data
  }

  async updateOpportunity(id: string, data: Partial<Opportunity>): Promise<ApiResponse<Opportunity>> {
    const response = await this.client.put<ApiResponse<Opportunity>>(`/opportunities/${id}`, data)
    return response.data
  }

  // Task methods
  async getTasks(params?: QueryParams): Promise<ListResponse<Task>> {
    const response = await this.client.get<ListResponse<Task>>('/tasks', { params })
    return response.data
  }

  async getTask(id: string): Promise<ApiResponse<Task>> {
    const response = await this.client.get<ApiResponse<Task>>(`/tasks/${id}`)
    return response.data
  }

  async createTask(data: Partial<Task>): Promise<ApiResponse<Task>> {
    const response = await this.client.post<ApiResponse<Task>>('/tasks', data)
    return response.data
  }

  async updateTask(id: string, data: Partial<Task>): Promise<ApiResponse<Task>> {
    const response = await this.client.put<ApiResponse<Task>>(`/tasks/${id}`, data)
    return response.data
  }

  // Activity methods (unified timeline)
  async getActivities(params?: QueryParams): Promise<ListResponse<Activity>> {
    const response = await this.client.get<ListResponse<Activity>>('/activities', { params })
    return response.data
  }

  // Dashboard methods
  async getDashboardStats(): Promise<ApiResponse<{
    totalContacts: number
    activeTrials: number
    monthlyRevenue: number
    conversionRate: number
    recentActivities: Activity[]
    pipeline: Array<{
      stage: string
      count: number
      value: number
    }>
  }>> {
    const response = await this.client.get<ApiResponse<any>>('/dashboard/stats')
    return response.data
  }
}

export const apiClient = new ApiClient()