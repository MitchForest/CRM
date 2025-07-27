import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import type { QueryParams } from '@/types/api.generated'
import type { Contact } from '@/types/api.generated'

export function useContacts(params?: QueryParams) {
  return useQuery({
    queryKey: ['contacts', params],
    queryFn: () => apiClient.getContacts(params),
  })
}

export function useContact(id: string) {
  return useQuery({
    queryKey: ['contact', id],
    queryFn: () => apiClient.getContact(id),
    enabled: !!id,
  })
}

export function useCreateContact() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: (data: Partial<ContactDB>) => apiClient.createContact(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
    },
  })
}

export function useUpdateContact() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<ContactDB> }) => 
      apiClient.updateContact(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['contact', id] })
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
    },
  })
}

export function useDeleteContact() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: (id: string) => apiClient.deleteContact(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
    },
  })
}

// Get contact activities - commented out (not part of Phase 1)
// export function useContactActivities(contactId: string, params?: QueryParams) {
//   return useQuery({
//     queryKey: ['contact-activities', contactId, params],
//     queryFn: () => apiClient.getContactActivities(contactId, params),
//     enabled: !!contactId,
//   })
// }