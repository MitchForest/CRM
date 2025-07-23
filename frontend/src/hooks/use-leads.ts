import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { Lead } from '@/types/api.generated'
import { getErrorMessage } from '@/lib/error-utils'

// Get paginated leads
export function useLeads(page = 1, limit = 10, filters?: Record<string, string | number>) {
  return useQuery({
    queryKey: ['leads', page, limit, filters],
    queryFn: async () => {
      return await apiClient.getLeads({ 
        page, 
        pageSize: limit,
        ...filters 
      })
    },
  })
}

// Get single lead
export function useLead(id: string) {
  return useQuery({
    queryKey: ['lead', id],
    queryFn: async () => {
      return await apiClient.getLead(id)
    },
    enabled: !!id,
  })
}

// Get lead activities - commented out (not part of Phase 1)
// export function useLeadActivities(leadId: string) {
//   return useQuery({
//     queryKey: ['lead', leadId, 'activities'],
//     queryFn: async () => {
//       const response = await apiClient.get<{ success: boolean; data: Activity[] }>(
//         `/leads/${leadId}/activities`
//       )
//       return response.data
//     },
//     enabled: !!leadId,
//   })
// }

// Create lead
export function useCreateLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Omit<Lead, 'id'>) => {
      return await apiClient.createLead(data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead created successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to create lead'))
    },
  })
}

// Update lead
export function useUpdateLead(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Partial<Lead>) => {
      return await apiClient.updateLead(id, data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead', id] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead updated successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to update lead'))
    },
  })
}

// Delete lead
export function useDeleteLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: string) => {
      return await apiClient.deleteLead(id)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast.success('Lead deleted successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to delete lead'))
    },
  })
}

// Convert lead to contact
export function useConvertLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (leadId: string) => {
      const response = await apiClient.convertLead(leadId)
      return response.data
    },
    onSuccess: (_, leadId) => {
      queryClient.invalidateQueries({ queryKey: ['lead', leadId] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
      toast.success('Lead converted successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to convert lead'))
    },
  })
}