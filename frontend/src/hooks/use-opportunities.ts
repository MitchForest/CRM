import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import type { Opportunity, QueryParams } from '@/types/api.generated'

export function useOpportunities(params?: QueryParams) {
  return useQuery({
    queryKey: ['opportunities', params],
    queryFn: () => apiClient.getOpportunities(params),
  })
}

export function useOpportunity(id: string) {
  return useQuery({
    queryKey: ['opportunity', id],
    queryFn: () => apiClient.getOpportunity(id),
    enabled: !!id,
  })
}

export function useCreateOpportunity() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: (data: Partial<Opportunity>) => apiClient.createOpportunity(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}

export function useUpdateOpportunity() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<Opportunity> }) => 
      apiClient.updateOpportunity(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['opportunity', id] })
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}

// Note: Delete opportunity and get opportunity activities endpoints 
// are not yet implemented in the backend API