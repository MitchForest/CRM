import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { PermissionGuard } from '@/components/auth/PermissionGuard'
import { usePermissions } from '@/hooks/use-permissions'

// Mock the usePermissions hook
vi.mock('@/hooks/use-permissions')

describe('PermissionGuard', () => {
  const mockUsePermissions = vi.mocked(usePermissions)

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders children when permission is granted', () => {
    mockUsePermissions.mockReturnValue({
      hasPermission: vi.fn().mockReturnValue(true),
      canView: vi.fn(),
      canCreate: vi.fn(),
      canEdit: vi.fn(),
      canDelete: vi.fn(),
      userRole: 'admin',
    })

    render(
      <PermissionGuard module="Leads" action="create">
        <div>Protected Content</div>
      </PermissionGuard>
    )

    expect(screen.getByText('Protected Content')).toBeInTheDocument()
    expect(mockUsePermissions().hasPermission).toHaveBeenCalledWith('Leads', 'create')
  })

  it('renders fallback when permission is denied', () => {
    mockUsePermissions.mockReturnValue({
      hasPermission: vi.fn().mockReturnValue(false),
      canView: vi.fn(),
      canCreate: vi.fn(),
      canEdit: vi.fn(),
      canDelete: vi.fn(),
      userRole: 'sales_rep',
    })

    render(
      <PermissionGuard 
        module="Cases" 
        action="delete"
        fallback={<div>Access Denied</div>}
      >
        <div>Protected Content</div>
      </PermissionGuard>
    )

    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument()
    expect(screen.getByText('Access Denied')).toBeInTheDocument()
    expect(mockUsePermissions().hasPermission).toHaveBeenCalledWith('Cases', 'delete')
  })

  it('renders nothing when permission is denied and no fallback', () => {
    mockUsePermissions.mockReturnValue({
      hasPermission: vi.fn().mockReturnValue(false),
      canView: vi.fn(),
      canCreate: vi.fn(),
      canEdit: vi.fn(),
      canDelete: vi.fn(),
      userRole: 'customer_success',
    })

    const { container } = render(
      <PermissionGuard module="Opportunities" action="delete">
        <div>Protected Content</div>
      </PermissionGuard>
    )

    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument()
    expect(container.firstChild).toBeNull()
  })

  it('checks different permission combinations', () => {
    const mockHasPermission = vi.fn()
      .mockReturnValueOnce(true)  // First call
      .mockReturnValueOnce(false) // Second call
      .mockReturnValueOnce(true)  // Third call

    mockUsePermissions.mockReturnValue({
      hasPermission: mockHasPermission,
      canView: vi.fn(),
      canCreate: vi.fn(),
      canEdit: vi.fn(),
      canDelete: vi.fn(),
      userRole: 'sales_rep',
    })

    // First render - permission granted
    const { rerender } = render(
      <PermissionGuard module="Leads" action="view">
        <div>Can View Leads</div>
      </PermissionGuard>
    )
    expect(screen.getByText('Can View Leads')).toBeInTheDocument()

    // Second render - permission denied
    rerender(
      <PermissionGuard module="Leads" action="delete">
        <div>Can Delete Leads</div>
      </PermissionGuard>
    )
    expect(screen.queryByText('Can Delete Leads')).not.toBeInTheDocument()

    // Third render - permission granted again
    rerender(
      <PermissionGuard module="Activities" action="create">
        <div>Can Create Activities</div>
      </PermissionGuard>
    )
    expect(screen.getByText('Can Create Activities')).toBeInTheDocument()

    // Verify all calls
    expect(mockHasPermission).toHaveBeenCalledTimes(3)
    expect(mockHasPermission).toHaveBeenNthCalledWith(1, 'Leads', 'view')
    expect(mockHasPermission).toHaveBeenNthCalledWith(2, 'Leads', 'delete')
    expect(mockHasPermission).toHaveBeenNthCalledWith(3, 'Activities', 'create')
  })

  it('works with complex children', () => {
    mockUsePermissions.mockReturnValue({
      hasPermission: vi.fn().mockReturnValue(true),
      canView: vi.fn(),
      canCreate: vi.fn(),
      canEdit: vi.fn(),
      canDelete: vi.fn(),
      userRole: 'admin',
    })

    render(
      <PermissionGuard module="Accounts" action="edit">
        <div>
          <h1>Edit Account</h1>
          <button>Save</button>
          <span>Complex nested content</span>
        </div>
      </PermissionGuard>
    )

    expect(screen.getByText('Edit Account')).toBeInTheDocument()
    expect(screen.getByText('Save')).toBeInTheDocument()
    expect(screen.getByText('Complex nested content')).toBeInTheDocument()
  })

  it('works with React fragments', () => {
    mockUsePermissions.mockReturnValue({
      hasPermission: vi.fn().mockReturnValue(true),
      canView: vi.fn(),
      canCreate: vi.fn(),
      canEdit: vi.fn(),
      canDelete: vi.fn(),
      userRole: 'admin',
    })

    render(
      <PermissionGuard module="Opportunities" action="create">
        <>
          <div>First element</div>
          <div>Second element</div>
        </>
      </PermissionGuard>
    )

    expect(screen.getByText('First element')).toBeInTheDocument()
    expect(screen.getByText('Second element')).toBeInTheDocument()
  })
})