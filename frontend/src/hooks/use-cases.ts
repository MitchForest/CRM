import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { CaseDB } from '@/types/database.types'

export type CasePriority = 'High' | 'Medium' | 'Low'
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
        limit,
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
        limit: 100,
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
    mutationFn: async (data: Omit<CaseDB, 'id' | 'date_entered' | 'date_modified'>) => {
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
    mutationFn: async (data: Partial<CaseDB>) => {
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
        status: 'closed',
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
      const response = await apiClient.getCases({ limit: 1000 })
      const cases = response.data

      const openCases = cases.filter(c => c.status !== 'closed').length
      const criticalCases = cases.filter(c => c.priority === 'P1').length
      
      // Calculate average resolution time (would be better done on backend)
      const closedCases = cases.filter(c => c.status === 'closed')
      let avgResolutionTime = 0
      
      if (closedCases.length > 0) {
        const totalTime = closedCases.reduce((sum, c) => {
          if (c.date_entered && c.date_modified) {
            const created = new Date(c.date_entered).getTime()
            const updated = new Date(c.date_modified).getTime()
            return sum + (updated - created)
          }
          return sum
        }, 0)
        
        avgResolutionTime = totalTime / closedCases.length / (1000 * 60 * 60 * 24) // Convert to days
      }

      // Cases by priority
      const casesByPriority = [
        { priority: 'P1' as CasePriority, count: cases.filter(c => c.priority === 'P1').length },
        { priority: 'P2' as CasePriority, count: cases.filter(c => c.priority === 'P2').length },
        { priority: 'P3' as CasePriority, count: cases.filter(c => c.priority === 'P3').length },
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
        limit: 100,
        priority: 'P1'
      })
      
      return response.data.filter(c => 
        c.status !== 'closed' && 
        c.priority === 'P1'
      )
    },
  })
}