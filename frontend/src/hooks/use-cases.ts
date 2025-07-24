import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { Case } from '@/types/api.generated'
import type { CasePriority } from '@/types/phase2.types'
import { getErrorMessage } from '@/lib/error-utils'

// Get paginated cases
export function useCases(
  page = 1, 
  limit = 20, 
  filters?: { 
    status?: string
    priority?: string
    search?: string 
  }
) {
  return useQuery({
    queryKey: ['cases', page, limit, filters],
    queryFn: async () => {
      return await apiClient.getCases({ 
        page, 
        pageSize: limit,
        ...filters 
      })
    },
  })
}

// Get single case
export function useCase(id: string) {
  return useQuery({
    queryKey: ['case', id],
    queryFn: async () => {
      return await apiClient.getCase(id)
    },
    enabled: !!id,
  })
}

// Get cases by account
export function useCasesByAccount(accountId: string) {
  return useQuery({
    queryKey: ['cases', 'by-account', accountId],
    queryFn: async () => {
      return await apiClient.getCases({ 
        pageSize: 100,
        // This would need backend support for account filtering
        // filter: { accountId }
      })
    },
    enabled: !!accountId,
  })
}

// Create case
export function useCreateCase() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Omit<Case, 'id'>) => {
      return await apiClient.createCase(data)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['cases'] })
      await queryClient.refetchQueries({ queryKey: ['cases'] })
      toast.success('Case created successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to create case'))
    },
  })
}

// Update case
export function useUpdateCase(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Partial<Case>) => {
      return await apiClient.updateCase(id, data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['case', id] })
      queryClient.invalidateQueries({ queryKey: ['cases'] })
      toast.success('Case updated successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to update case'))
    },
  })
}

// Resolve case
export function useResolveCase(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (resolution: string) => {
      return await apiClient.updateCase(id, {
        status: 'Closed',
        resolution,
      })
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['case', id] })
      queryClient.invalidateQueries({ queryKey: ['cases'] })
      toast.success('Case resolved successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to resolve case'))
    },
  })
}

// Delete case
export function useDeleteCase() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: string) => {
      return await apiClient.deleteCase(id)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cases'] })
      toast.success('Case deleted successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to delete case'))
    },
  })
}

// Get case metrics
export function useCaseMetrics() {
  return useQuery({
    queryKey: ['cases', 'metrics'],
    queryFn: async () => {
      const response = await apiClient.getCases({ pageSize: 1000 })
      const cases = response.data

      const openCases = cases.filter(c => c.status !== 'Closed').length
      const criticalCases = cases.filter(c => c.priority === 'High').length
      
      // Calculate average resolution time (would be better done on backend)
      const closedCases = cases.filter(c => c.status === 'Closed')
      let avgResolutionTime = 0
      
      if (closedCases.length > 0) {
        const totalTime = closedCases.reduce((sum, c) => {
          if (c.createdAt && c.updatedAt) {
            const created = new Date(c.createdAt).getTime()
            const updated = new Date(c.updatedAt).getTime()
            return sum + (updated - created)
          }
          return sum
        }, 0)
        
        avgResolutionTime = totalTime / closedCases.length / (1000 * 60 * 60 * 24) // Convert to days
      }

      // Cases by priority
      const casesByPriority = [
        { priority: 'P1' as CasePriority, count: cases.filter(c => c.priority === 'High').length },
        { priority: 'P2' as CasePriority, count: cases.filter(c => c.priority === 'Medium').length },
        { priority: 'P3' as CasePriority, count: cases.filter(c => c.priority === 'Low').length },
      ]

      return {
        openCases,
        criticalCases,
        avgResolutionTime,
        casesByPriority,
      }
    },
  })
}

// Get critical cases alert
export function useCriticalCases() {
  return useQuery({
    queryKey: ['cases', 'critical'],
    queryFn: async () => {
      const response = await apiClient.getCases({ 
        pageSize: 100,
        priority: 'High' // or 'P1' depending on backend
      })
      
      return response.data.filter(c => 
        c.status !== 'Closed' && 
        c.priority === 'High'
      )
    },
  })
}