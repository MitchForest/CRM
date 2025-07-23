import { useQuery, useMutation, useQueryClient, UseQueryOptions, UseMutationOptions } from '@tanstack/react-query'
import { typeSafeApiClient } from '@/lib/api-client-v2'
import { ApiEndpoint, ApiRequest, ApiResponse } from '@/lib/api-schemas'
import { toast } from 'sonner'

// Generic query hook for type-safe API calls
export function useTypeSafeQuery<T extends ApiEndpoint>(
  endpoint: T,
  params?: ApiRequest<T>,
  options?: Omit<UseQueryOptions<ApiResponse<T>, Error>, 'queryKey' | 'queryFn'>
) {
  return useQuery({
    queryKey: [endpoint, params],
    queryFn: () => typeSafeApiClient.get(endpoint, params),
    ...options,
  })
}

// Generic mutation hook for type-safe API calls
export function useTypeSafeMutation<T extends ApiEndpoint>(
  endpoint: T,
  options?: UseMutationOptions<ApiResponse<T>, Error, ApiRequest<T>>
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: ApiRequest<T>) => typeSafeApiClient.post(endpoint, data),
    onSuccess: (data, variables, context) => {
      // Invalidate related queries
      queryClient.invalidateQueries({ queryKey: [endpoint] })
      
      if (options?.onSuccess) {
        options.onSuccess(data, variables, context)
      }
    },
    onError: (error, variables, context) => {
      console.error('Mutation error:', error)
      toast.error(error.message || 'An error occurred')
      
      if (options?.onError) {
        options.onError(error, variables, context)
      }
    },
    ...options,
  })
}

// Specific hooks for common operations
export function useContacts(page = 1, limit = 10, filters?: { search?: string; status?: string }) {
  return useTypeSafeQuery('/contacts', {
    page: page.toString(),
    limit: limit.toString(),
    ...filters,
  })
}

export function useContact(id: string) {
  return useTypeSafeQuery('/contacts/:id', undefined, {
    enabled: !!id,
  })
}

export function useCreateContact() {
  return useTypeSafeMutation('/contacts/create', {
    onSuccess: () => {
      toast.success('Contact created successfully')
    },
  })
}

export function useLeads(page = 1, limit = 10, filters?: { search?: string; status?: string }) {
  return useTypeSafeQuery('/leads', {
    page: page.toString(),
    limit: limit.toString(),
    ...filters,
  })
}

export function useLead(id: string) {
  return useTypeSafeQuery('/leads/:id', undefined, {
    enabled: !!id,
  })
}

export function useCreateLead() {
  return useTypeSafeMutation('/leads/create', {
    onSuccess: () => {
      toast.success('Lead created successfully')
    },
  })
}

export function useConvertLead() {
  return useTypeSafeMutation('/leads/:id/convert', {
    onSuccess: () => {
      toast.success('Lead converted successfully')
    },
  })
}

export function useActivities(filters?: { type?: string; page?: string; limit?: string }) {
  return useTypeSafeQuery('/activities', filters)
}

export function useUpcomingActivities() {
  return useTypeSafeQuery('/activities/upcoming')
}

export function useRecentActivities(limit = 10) {
  return useTypeSafeQuery('/activities/recent', {
    limit: limit.toString(),
  })
}