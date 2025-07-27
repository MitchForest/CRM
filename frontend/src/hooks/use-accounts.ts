import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { AccountDB } from '@/types/database.types'
import { getErrorMessage } from '@/lib/error-utils'

// Get paginated accounts
export function useAccounts(options?: { page?: number; limit?: number } | number, limit = 10, filters?: Record<string, string | number>) {
  // Handle both object and separate parameters
  let page = 1
  let pageSize = limit
  
  if (typeof options === 'object' && options !== null) {
    page = options.page || 1
    pageSize = options.limit || 10
  } else if (typeof options === 'number') {
    page = options
  }
  
  return useQuery({
    queryKey: ['accounts', page, limit, filters],
    queryFn: async () => {
      return await apiClient.getAccounts({ 
        page, 
        limit: pageSize,
        ...filters 
      })
    },
  })
}

// Get single account
export function useAccount(id: string) {
  return useQuery({
    queryKey: ['account', id],
    queryFn: async () => {
      return await apiClient.getAccount(id)
    },
    enabled: !!id,
  })
}

// Create account
export function useCreateAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Omit<AccountDB, 'id' | 'date_entered' | 'date_modified'>) => {
      return await apiClient.createAccount(data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['accounts'] })
      toast.success('Account created successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to create account'))
    },
  })
}

// Update account
export function useUpdateAccount(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (data: Partial<AccountDB>) => {
      return await apiClient.updateAccount(id, data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['account', id] })
      queryClient.invalidateQueries({ queryKey: ['accounts'] })
      toast.success('Account updated successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to update account'))
    },
  })
}

// Delete account
export function useDeleteAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: string) => {
      return await apiClient.deleteAccount(id)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['accounts'] })
      toast.success('Account deleted successfully')
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, 'Failed to delete account'))
    },
  })
}