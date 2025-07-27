import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import type { ActivityMetrics } from '@/types/api.types'
import { useAuthStore } from '@/stores/auth-store'

// Get dashboard metrics from custom API
export function useDashboardMetrics() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-metrics'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getDashboardMetrics()
      if (response.success && response.data) {
        // Return data as-is (already in the correct format)
        return response.data
      }
      return response
    },
  })
}

// Legacy wrapper for backward compatibility
export function useDashboardStats() {
  return useDashboardMetrics()
}

// Get pipeline data from custom API
export function usePipelineData() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-pipeline'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getPipelineData()
      if (!response.success || !response.data) return []
      
      // The API returns an array of pipeline data directly
      return response.data
    },
  })
}

// Get activity metrics from custom API
export function useActivityMetrics() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-activities'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getActivityMetrics()
      if (response.success && response.data) {
        // Return data as-is
        return response.data
      }
      return response
    },
  })
}

// Get case metrics from custom API
export function useCaseMetrics() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-cases'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getCaseMetrics()
      if (response.success && response.data) {
        // Return data as-is
        return response.data
      }
      return response
    },
  })
}

// Get recent activities
export function useRecentActivities() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-recent-activities'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getActivityMetrics()
      if (!response.success || !response.data) return []
      
      // Return upcoming activities transformed for dashboard
      const rawData = response.data as ActivityMetrics
      return (rawData.upcoming_activities || []).slice(0, 10).map((activity) => ({
        id: activity.id,
        type: activity.type,
        name: activity.name,
        description: `${activity.type} - ${activity.related_to}`,
        date: activity.date_start,
        icon: activity.type === 'Call' ? 'Phone' : 
              activity.type === 'Meeting' ? 'Calendar' : 
              activity.type === 'Note' ? 'FileText' : 'CheckCircle2',
      }))
    },
  })
}

// Combine all dashboard data
export function useDashboardData() {
  const metrics = useDashboardMetrics()
  const pipeline = usePipelineData()
  const activityMetrics = useActivityMetrics()
  const caseMetrics = useCaseMetrics()
  const recentActivities = useRecentActivities()
  
  return {
    stats: metrics, // Keep as 'stats' for backward compatibility
    metrics,
    pipeline,
    activityMetrics,
    caseMetrics,
    recentActivities,
    isLoading: metrics.isLoading || pipeline.isLoading || activityMetrics.isLoading || caseMetrics.isLoading,
  }
}