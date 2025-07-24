import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import { usePermissions } from '@/hooks/use-permissions'
import { useAuthStore } from '@/stores/auth-store'

// Mock the auth store
vi.mock('@/stores/auth-store')

describe('usePermissions', () => {
  const mockUseAuthStore = vi.mocked(useAuthStore)

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('admin role', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          email: 'admin@test.com',
          role: 'admin',
        },
      } as any)
    })

    it('grants all permissions for admin', () => {
      const { result } = renderHook(() => usePermissions())

      // Check all modules and actions
      const modules = ['Leads', 'Accounts', 'Opportunities', 'Cases', 'Activities'] as const
      const actions = ['view', 'create', 'edit', 'delete'] as const

      modules.forEach(module => {
        actions.forEach(action => {
          expect(result.current.hasPermission(module, action)).toBe(true)
        })
      })
    })

    it('returns correct helper methods for admin', () => {
      const { result } = renderHook(() => usePermissions())

      expect(result.current.canView('Leads')).toBe(true)
      expect(result.current.canCreate('Opportunities')).toBe(true)
      expect(result.current.canEdit('Cases')).toBe(true)
      expect(result.current.canDelete('Activities')).toBe(true)
    })

    it('returns admin as user role', () => {
      const { result } = renderHook(() => usePermissions())
      expect(result.current.userRole).toBe('admin')
    })
  })

  describe('sales_rep role', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '2',
          email: 'sales@test.com',
          role: 'sales_rep',
        },
      } as any)
    })

    it('grants appropriate permissions for sales rep', () => {
      const { result } = renderHook(() => usePermissions())

      // Sales reps can view, create, edit Leads
      expect(result.current.hasPermission('Leads', 'view')).toBe(true)
      expect(result.current.hasPermission('Leads', 'create')).toBe(true)
      expect(result.current.hasPermission('Leads', 'edit')).toBe(true)
      expect(result.current.hasPermission('Leads', 'delete')).toBe(false)

      // Sales reps can only view Cases
      expect(result.current.hasPermission('Cases', 'view')).toBe(true)
      expect(result.current.hasPermission('Cases', 'create')).toBe(false)
      expect(result.current.hasPermission('Cases', 'edit')).toBe(false)
      expect(result.current.hasPermission('Cases', 'delete')).toBe(false)

      // Sales reps have full access to Opportunities except delete
      expect(result.current.hasPermission('Opportunities', 'view')).toBe(true)
      expect(result.current.hasPermission('Opportunities', 'create')).toBe(true)
      expect(result.current.hasPermission('Opportunities', 'edit')).toBe(true)
      expect(result.current.hasPermission('Opportunities', 'delete')).toBe(false)
    })

    it('returns sales_rep as user role', () => {
      const { result } = renderHook(() => usePermissions())
      expect(result.current.userRole).toBe('sales_rep')
    })
  })

  describe('customer_success role', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '3',
          email: 'cs@test.com',
          role: 'customer_success',
        },
      } as any)
    })

    it('grants appropriate permissions for customer success', () => {
      const { result } = renderHook(() => usePermissions())

      // CS can only view Leads
      expect(result.current.hasPermission('Leads', 'view')).toBe(true)
      expect(result.current.hasPermission('Leads', 'create')).toBe(false)
      expect(result.current.hasPermission('Leads', 'edit')).toBe(false)
      expect(result.current.hasPermission('Leads', 'delete')).toBe(false)

      // CS can view and edit Accounts
      expect(result.current.hasPermission('Accounts', 'view')).toBe(true)
      expect(result.current.hasPermission('Accounts', 'create')).toBe(false)
      expect(result.current.hasPermission('Accounts', 'edit')).toBe(true)
      expect(result.current.hasPermission('Accounts', 'delete')).toBe(false)

      // CS has full access to Cases except delete
      expect(result.current.hasPermission('Cases', 'view')).toBe(true)
      expect(result.current.hasPermission('Cases', 'create')).toBe(true)
      expect(result.current.hasPermission('Cases', 'edit')).toBe(true)
      expect(result.current.hasPermission('Cases', 'delete')).toBe(false)
    })
  })

  describe('default behavior', () => {
    it('defaults to sales_rep role when user has no role', () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '4',
          email: 'user@test.com',
          // role is undefined
        },
      } as any)

      const { result } = renderHook(() => usePermissions())
      expect(result.current.userRole).toBe('sales_rep')
      
      // Should have sales_rep permissions
      expect(result.current.canCreate('Leads')).toBe(true)
      expect(result.current.canDelete('Leads')).toBe(false)
    })

    it('defaults to sales_rep role when no user', () => {
      mockUseAuthStore.mockReturnValue({
        user: null,
      } as any)

      const { result } = renderHook(() => usePermissions())
      expect(result.current.userRole).toBe('sales_rep')
    })
  })

  describe('unknown role', () => {
    it('denies all permissions for unknown roles', () => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '5',
          email: 'unknown@test.com',
          role: 'unknown_role',
        },
      } as any)

      const { result } = renderHook(() => usePermissions())

      // All permissions should be denied
      const modules = ['Leads', 'Accounts', 'Opportunities', 'Cases', 'Activities'] as const
      const actions = ['view', 'create', 'edit', 'delete'] as const

      modules.forEach(module => {
        actions.forEach(action => {
          expect(result.current.hasPermission(module, action)).toBe(false)
        })
      })
    })
  })

  describe('helper methods', () => {
    beforeEach(() => {
      mockUseAuthStore.mockReturnValue({
        user: {
          id: '1',
          email: 'sales@test.com',
          role: 'sales_rep',
        },
      } as any)
    })

    it('canView checks view permission', () => {
      const { result } = renderHook(() => usePermissions())
      
      expect(result.current.canView('Leads')).toBe(true)
      expect(result.current.canView('Cases')).toBe(true)
    })

    it('canCreate checks create permission', () => {
      const { result } = renderHook(() => usePermissions())
      
      expect(result.current.canCreate('Leads')).toBe(true)
      expect(result.current.canCreate('Cases')).toBe(false)
    })

    it('canEdit checks edit permission', () => {
      const { result } = renderHook(() => usePermissions())
      
      expect(result.current.canEdit('Opportunities')).toBe(true)
      expect(result.current.canEdit('Cases')).toBe(false)
    })

    it('canDelete checks delete permission', () => {
      const { result } = renderHook(() => usePermissions())
      
      expect(result.current.canDelete('Leads')).toBe(false)
      expect(result.current.canDelete('Activities')).toBe(false)
    })
  })
})