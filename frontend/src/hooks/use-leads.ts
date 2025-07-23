import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { Lead, Activity, ListResponse } from '@/types/api.generated'

// Get paginated leads
export function useLeads(page = 1, limit = 10, filters?: Record<string, any>) {
  return useQuery({
    queryKey: ['leads', page, limit, filters],
    queryFn: async () => {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: limit.toString(),
        ...filters,
      })
      const response = await apiClient.get<ListResponse<Lead>>(`/leads?${params}`)
      return response.data
    },
  })
}

// Get single lead
export function useLead(id: string) {
  return useQuery({
    queryKey: ['lead', id],
    queryFn: async () => {
      const response = await apiClient.get<{ success: boolean; data: Lead }>(`/leads/${id}`)
      return response.data
    },
    enabled: !!id,
  })
}

// Get lead activities
export function useLeadActivities(leadId: string) {
  return useQuery({
    queryKey: ['lead', leadId, 'activities'],
    queryFn: async () => {
      const response = await apiClient.get<{ success: boolean; data: Activity[] }>(
        `/leads/${leadId}/activities`
      )
      return response.data
    },
    enabled: !!leadId,
  })
}

// Create lead
export function useCreateLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Omit<Lead, 'id'>) => {
      const response = await apiClient.post<{ success: boolean; data: Lead }>('/leads', data)
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead created successfully')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to create lead')
    },
  })
}

// Update lead
export function useUpdateLead(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Partial<Lead>) => {
      const response = await apiClient.put<{ success: boolean; data: Lead }>(`/leads/${id}`, data)
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead', id] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead updated successfully')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to update lead')
    },
  })
}

// Delete lead
export function useDeleteLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: string) => {
      const response = await apiClient.delete(`/leads/${id}`)
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead deleted successfully')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to delete lead')
    },
  })
}

// Convert lead to contact
export function useConvertLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ 
      leadId, 
      createOpportunity = false,
      opportunityData,
    }: { 
      leadId: string; 
      createOpportunity?: boolean;
      opportunityData?: {
        name: string;
        amount: number;
        closeDate: string;
        salesStage: string;
      };
    }) => {
      const response = await apiClient.post(`/leads/${leadId}/convert`, {
        createOpportunity,
        opportunityData,
      })
      return response.data
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['lead', variables.leadId] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
      if (variables.createOpportunity) {
        queryClient.invalidateQueries({ queryKey: ['opportunities'] })
      }
      toast.success('Lead converted successfully')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to convert lead')
    },
  })
}