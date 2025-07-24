import axios, { type AxiosInstance, type AxiosError } from 'axios'
import { z } from 'zod'
import { getStoredAuth, setStoredAuth, clearStoredAuth } from '@/stores/auth-store'
import { ApiEndpointSchemas, type ApiEndpoint, type ApiRequest, type ApiResponse } from './api-schemas'

export class TypeSafeApiClient {
  private client: AxiosInstance
  private refreshPromise: Promise<string> | null = null

  constructor(baseURL = import.meta.env['VITE_API_URL'] || 'http://localhost:8080/api') {
    this.client = axios.create({
      baseURL,
      timeout: 30000,
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
        const originalRequest = error.config as AxiosError['config'] & { _retry?: boolean }

        if (error.response?.status === 401 && !originalRequest._retry) {
          originalRequest._retry = true

          try {
            const newToken = await this.refreshToken()
            originalRequest.headers.Authorization = `Bearer ${newToken}`
            return this.client(originalRequest)
          } catch (refreshError) {
            clearStoredAuth()
            window.location.href = '/login'
            return Promise.reject(refreshError)
          }
        }

        return Promise.reject(error)
      }
    )
  }

  private async refreshToken(): Promise<string> {
    if (this.refreshPromise) {
      return this.refreshPromise
    }

    this.refreshPromise = (async () => {
      const auth = getStoredAuth()
      if (!auth?.refreshToken) {
        throw new Error('No refresh token')
      }

      const response = await this.client.post('/auth/refresh', {
        refreshToken: auth.refreshToken,
      })

      const { accessToken, expiresIn } = response.data.data
      setStoredAuth({
        accessToken,
        refreshToken: auth.refreshToken,
        expiresAt: Date.now() + (expiresIn * 1000),
        ...(auth.user && { user: auth.user })
      })

      return accessToken
    })()

    try {
      const token = await this.refreshPromise
      return token
    } finally {
      this.refreshPromise = null
    }
  }

  // Type-safe request method
  async request<T extends ApiEndpoint>(
    endpoint: T,
    data?: ApiRequest<T>,
    params?: Record<string, string | number>
  ): Promise<ApiResponse<T>> {
    const config = ApiEndpointSchemas[endpoint]
    
    // Validate request data
    if (config.request && data) {
      try {
        config.request.parse(data)
      } catch (error) {
        if (error instanceof z.ZodError) {
          console.error('Request validation failed:', error.issues)
          const messages = error.issues.map((e) => `${e.path.join('.')}: ${e.message}`).join(', ')
          throw new Error(`Invalid request data: ${messages}`)
        }
      }
    }

    // Build the actual URL (replace :id params)
    let url = endpoint as string
    if (params?.['id']) {
      url = url.replace(':id', String(params['id']))
    }

    // Make the request
    let response
    const method = config.method as 'GET' | 'POST' | 'PUT'
    switch (method) {
      case 'GET':
        response = await this.client.get(url, { params: data })
        break
      case 'POST':
        response = await this.client.post(url, data)
        break
      case 'PUT':
        response = await this.client.put(url, data)
        break
      default: {
        const exhaustiveCheck: never = method
        throw new Error(`Unsupported method: ${exhaustiveCheck}`)
      }
    }

    // Validate response
    try {
      const validatedResponse = config.response.parse(response.data)
      return validatedResponse as ApiResponse<T>
    } catch (error) {
      if (error instanceof z.ZodError) {
        console.error('Response validation failed:', error.issues)
        console.error('Response data:', response.data)
        const messages = error.issues.map((e) => `${e.path.join('.')}: ${e.message}`).join(', ')
        throw new Error(`Invalid response data: ${messages}`)
      }
      throw error
    }
  }

  // Convenience methods
  async get<T extends ApiEndpoint>(
    endpoint: T,
    params?: ApiRequest<T>
  ): Promise<ApiResponse<T>> {
    return this.request(endpoint, params)
  }

  async post<T extends ApiEndpoint>(
    endpoint: T,
    data: ApiRequest<T>
  ): Promise<ApiResponse<T>> {
    return this.request(endpoint, data)
  }

  async put<T extends ApiEndpoint>(
    endpoint: T,
    data: ApiRequest<T>,
    params?: { id: string }
  ): Promise<ApiResponse<T>> {
    return this.request(endpoint, data, params)
  }

  async delete<T extends ApiEndpoint>(
    endpoint: T,
    params: { id: string }
  ): Promise<ApiResponse<T>> {
    return this.request(endpoint, undefined, params)
  }
}

// Export singleton instance
export const typeSafeApiClient = new TypeSafeApiClient()