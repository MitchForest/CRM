import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { OpportunityDB } from '@/types/database.types'
import { getErrorMessage } from '@/lib/error-utils'

// Get paginated opportunities
export function useOpportunities(page = 1, limit = 50, filters?: Record<string, string | number>) {
  return useQuery({
    queryKey: ['opportunities', page, limit, filters],
    queryFn: async () => {
      return await apiClient.getOpportunities({ 
        page, 
        limit,
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
export function useOpportunitiesByStage(stage?: OpportunityDB['sales_stage']) {
  return useQuery({
    queryKey: ['opportunities', 'by-stage', stage],
    queryFn: async () => {
      const params = stage ? { sales_stage: stage } : {}
      return await apiClient.getOpportunities({ 
        limit: 100, // Get more for pipeline view
        ...params
      })
    },
  })
}

// Create opportunity
export function useCreateOpportunity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Omit<OpportunityDB, 'id' | 'date_entered' | 'date_modified'>) => {
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
    mutationFn: async (data: Partial<OpportunityDB>) => {
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
    onMutate: async ({ id, stage }) => {
      // Cancel any outgoing refetches
      await queryClient.cancelQueries({ queryKey: ['opportunities', 'pipeline'] })

      // Snapshot the previous value
      const previousData = queryClient.getQueryData(['opportunities', 'pipeline'])

      // Optimistically update to the new value
      queryClient.setQueryData(['opportunities', 'pipeline'], (old: unknown) => {
        if (!old) return old
        const typedOld = old as { opportunities: OpportunityDB[] }
        
        const updatedOpportunities = typedOld.opportunities.map((opp: OpportunityDB) =>
          opp.id === id ? { ...opp, sales_stage: stage as OpportunityDB['sales_stage'] } : opp
        )
        
        // Rebuild the byStage grouping
        const opportunitiesByStage = updatedOpportunities.reduce((acc: Record<string, OpportunityDB[]>, opp: OpportunityDB) => {
          const stageKey = opp.sales_stage || 'qualification'
          if (!acc[stageKey]) {
            acc[stageKey] = []
          }
          acc[stageKey].push(opp)
          return acc
        }, {})
        
        return {
          ...old,
          opportunities: updatedOpportunities,
          byStage: opportunitiesByStage
        }
      })

      // Return a context object with the snapshotted value
      return { previousData }
    },
    onError: (error: unknown, _, context) => {
      // If the mutation fails, use the context returned from onMutate to roll back
      if (context?.previousData) {
        queryClient.setQueryData(['opportunities', 'pipeline'], context.previousData)
      }
      toast.error(getErrorMessage(error, 'Failed to update opportunity stage'))
    },
    onSettled: () => {
      // Always refetch after error or success
      queryClient.invalidateQueries({ queryKey: ['opportunities', 'pipeline'] })
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
        limit: 200, // Get more for complete pipeline view
      })
      
      
      // Group by stage for pipeline
      const opportunitiesByStage = response.data.reduce((acc, opp) => {
        const stage = opp.sales_stage || 'qualification'
        if (!acc[stage]) {
          acc[stage] = []
        }
        acc[stage].push(opp)
        return acc
      }, {} as Record<string, OpportunityDB[]>)
      
      return {
        opportunities: response.data,
        byStage: opportunitiesByStage,
        total: response.pagination?.total || 0
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
  
  const totalValue = data.opportunities.reduce((sum, opp) => {
    const amount = typeof opp.amount === 'string' ? parseFloat(opp.amount) : (opp.amount || 0)
    return sum + (isNaN(amount) || !isFinite(amount) ? 0 : amount)
  }, 0)
  
  const weightedValue = data.opportunities.reduce((sum, opp) => {
    const amount = typeof opp.amount === 'string' ? parseFloat(opp.amount) : (opp.amount || 0)
    const probability = typeof opp.probability === 'string' ? parseFloat(opp.probability) : (opp.probability || 0)
    const validAmount = isNaN(amount) || !isFinite(amount) ? 0 : amount
    const validProbability = isNaN(probability) || !isFinite(probability) ? 0 : probability
    return sum + (validAmount * validProbability / 100)
  }, 0)
  const averageDealSize = data.opportunities.length > 0 
    ? totalValue / data.opportunities.length 
    : 0
    
  const stageMetrics = Object.entries(data.byStage).map(([stage, opps]) => ({
    stage,
    count: opps.length,
    value: opps.reduce((sum, opp) => {
      const amount = typeof opp.amount === 'string' ? parseFloat(opp.amount) : (opp.amount || 0)
      return sum + (isNaN(amount) || !isFinite(amount) ? 0 : amount)
    }, 0)
  }))
  
  return {
    totalValue,
    weightedValue,
    averageDealSize,
    totalCount: data.opportunities.length,
    stageMetrics
  }
}