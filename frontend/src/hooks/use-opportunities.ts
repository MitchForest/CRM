import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { Opportunity } from '@/types/api.generated'
import type { OpportunityStage } from '@/types/phase2.types'
import { getErrorMessage } from '@/lib/error-utils'

// Get paginated opportunities
export function useOpportunities(page = 1, limit = 50, filters?: Record<string, string | number>) {
  return useQuery({
    queryKey: ['opportunities', page, limit, filters],
    queryFn: async () => {
      return await apiClient.getOpportunities({ 
        page, 
        pageSize: limit,
        ...filters 
      })
    },
  })
}

// Get single opportunity
export function useOpportunity(id: string) {
  return useQuery({
    queryKey: ['opportunity', id],
    queryFn: async () => {
      return await apiClient.getOpportunity(id)
    },
    enabled: !!id,
  })
}

// Get opportunities by stage
export function useOpportunitiesByStage(stage?: OpportunityStage) {
  return useQuery({
    queryKey: ['opportunities', 'by-stage', stage],
    queryFn: async () => {
      const params = stage ? { salesStage: stage } : {}
      return await apiClient.getOpportunities({ 
        pageSize: 100, // Get more for pipeline view
        ...params
      })
    },
  })
}

// Create opportunity
export function useCreateOpportunity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Omit<Opportunity, 'id'>) => {
      return await apiClient.createOpportunity(data)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['opportunities'] })
      await queryClient.refetchQueries({ queryKey: ['opportunities'] })
      toast.success('Opportunity created successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to create opportunity'))
    },
  })
}

// Update opportunity
export function useUpdateOpportunity(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Partial<Opportunity>) => {
      return await apiClient.updateOpportunity(id, data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunity', id] })
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
      toast.success('Opportunity updated successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to update opportunity'))
    },
  })
}

// Update opportunity stage (for drag-and-drop)
export function useUpdateOpportunityStage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, stage }: { id: string; stage: string }) => {
      return await apiClient.updateOpportunityStage(id, stage)
    },
    onSuccess: (_, variables) => {
      // Invalidate both specific opportunity and list queries
      queryClient.invalidateQueries({ queryKey: ['opportunity', variables.id] })
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
      // Don't show toast for drag-drop operations to avoid spam
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to update opportunity stage'))
    },
  })
}

// Delete opportunity
export function useDeleteOpportunity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: string) => {
      return await apiClient.deleteOpportunity(id)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
      toast.success('Opportunity deleted successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to delete opportunity'))
    },
  })
}

// Get opportunities for pipeline view
export function useOpportunitiesPipeline() {
  return useQuery({
    queryKey: ['opportunities', 'pipeline'],
    queryFn: async () => {
      // Get all opportunities for pipeline view
      const response = await apiClient.getOpportunities({ 
        pageSize: 200, // Get more for complete pipeline view
      })
      
      // Group by stage for pipeline
      const opportunitiesByStage = response.data.reduce((acc, opp) => {
        const stage = opp.salesStage || 'Qualification'
        if (!acc[stage]) {
          acc[stage] = []
        }
        acc[stage].push(opp)
        return acc
      }, {} as Record<string, Opportunity[]>)
      
      return {
        opportunities: response.data,
        byStage: opportunitiesByStage,
        total: response.pagination?.totalCount || 0
      }
    },
  })
}

// Calculate pipeline metrics
export function usePipelineMetrics() {
  const { data } = useOpportunitiesPipeline()
  
  if (!data) {
    return {
      totalValue: 0,
      weightedValue: 0,
      averageDealSize: 0,
      totalCount: 0,
      stageMetrics: []
    }
  }
  
  const totalValue = data.opportunities.reduce((sum, opp) => sum + (opp.amount || 0), 0)
  const weightedValue = data.opportunities.reduce((sum, opp) => 
    sum + ((opp.amount || 0) * (opp.probability || 0) / 100), 0
  )
  const averageDealSize = data.opportunities.length > 0 
    ? totalValue / data.opportunities.length 
    : 0
    
  const stageMetrics = Object.entries(data.byStage).map(([stage, opps]) => ({
    stage,
    count: opps.length,
    value: opps.reduce((sum, opp) => sum + (opp.amount || 0), 0)
  }))
  
  return {
    totalValue,
    weightedValue,
    averageDealSize,
    totalCount: data.opportunities.length,
    stageMetrics
  }
}