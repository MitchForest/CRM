import { useAuthStore } from '@/stores/auth-store'

type Module = 'Leads' | 'Accounts' | 'Opportunities' | 'Cases' | 'Activities' | 'Contacts'
type Action = 'view' | 'create' | 'edit' | 'delete'

// Define role permissions
const rolePermissions: Record<string, Record<Module, Action[]>> = {
  admin: {
    Leads: ['view', 'create', 'edit', 'delete'],
    Accounts: ['view', 'create', 'edit', 'delete'],
    Contacts: ['view', 'create', 'edit', 'delete'],
    Opportunities: ['view', 'create', 'edit', 'delete'],
    Cases: ['view', 'create', 'edit', 'delete'],
    Activities: ['view', 'create', 'edit', 'delete'],
  },
  sales_manager: {
    Leads: ['view', 'create', 'edit', 'delete'],
    Accounts: ['view', 'create', 'edit', 'delete'],
    Contacts: ['view', 'create', 'edit', 'delete'],
    Opportunities: ['view', 'create', 'edit', 'delete'],
    Cases: ['view', 'create'],
    Activities: ['view', 'create', 'edit', 'delete'],
  },
  sales_rep: {
    Leads: ['view', 'create', 'edit'],
    Accounts: ['view', 'create', 'edit'],
    Contacts: ['view', 'create', 'edit'],
    Opportunities: ['view', 'create', 'edit'],
    Cases: ['view'],
    Activities: ['view', 'create', 'edit'],
  },
  customer_success: {
    Leads: ['view'],
    Accounts: ['view', 'edit'],
    Contacts: ['view', 'edit'],
    Opportunities: ['view'],
    Cases: ['view', 'create', 'edit'],
    Activities: ['view', 'create', 'edit'],
  },
  support_agent: {
    Leads: ['view'],
    Accounts: ['view'],
    Contacts: ['view'],
    Opportunities: [],
    Cases: ['view', 'create', 'edit'],
    Activities: ['view', 'create', 'edit'],
  },
  viewer: {
    Leads: ['view'],
    Accounts: ['view'],
    Contacts: ['view'],
    Opportunities: ['view'],
    Cases: ['view'],
    Activities: ['view'],
  },
}

export function usePermissions() {
  const user = useAuthStore((state) => state.user)
  const userRole = user?.role || 'sales_rep' // Default to sales_rep if no role

  const hasPermission = (module: Module, action: Action): boolean => {
    const permissions = rolePermissions[userRole]
    if (!permissions) return false
    
    const modulePermissions = permissions[module]
    if (!modulePermissions) return false
    
    return modulePermissions.includes(action)
  }

  const canView = (module: Module) => hasPermission(module, 'view')
  const canCreate = (module: Module) => hasPermission(module, 'create')
  const canEdit = (module: Module) => hasPermission(module, 'edit')
  const canDelete = (module: Module) => hasPermission(module, 'delete')

  // Check if user has any permission for a module
  const hasModuleAccess = (module: Module): boolean => {
    const permissions = rolePermissions[userRole]
    if (!permissions) return false
    
    const modulePermissions = permissions[module]
    return modulePermissions && modulePermissions.length > 0
  }

  // Get all accessible modules
  const getAccessibleModules = (): Module[] => {
    const permissions = rolePermissions[userRole]
    if (!permissions) return []
    
    return Object.keys(permissions).filter(
      (module) => permissions[module as Module].length > 0
    ) as Module[]
  }

  return {
    hasPermission,
    canView,
    canCreate,
    canEdit,
    canDelete,
    hasModuleAccess,
    getAccessibleModules,
    userRole,
    isAdmin: userRole === 'admin',
    isSalesManager: userRole === 'sales_manager',
    isSalesRep: userRole === 'sales_rep',
    isCustomerSuccess: userRole === 'customer_success',
    isSupportAgent: userRole === 'support_agent',
    isViewer: userRole === 'viewer',
  }
}