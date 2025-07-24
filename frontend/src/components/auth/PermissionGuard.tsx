import type { ReactNode } from 'react'
import { usePermissions } from '@/hooks/use-permissions'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { ShieldOff } from 'lucide-react'

type Module = 'Leads' | 'Accounts' | 'Opportunities' | 'Cases' | 'Activities' | 'Contacts'
type Action = 'view' | 'create' | 'edit' | 'delete'

interface PermissionGuardProps {
  module: Module
  action: Action
  children: ReactNode
  fallback?: ReactNode
  showAlert?: boolean
}

export function PermissionGuard({ 
  module, 
  action, 
  children, 
  fallback = null,
  showAlert = false 
}: PermissionGuardProps) {
  const { hasPermission } = usePermissions()

  if (!hasPermission(module, action)) {
    if (showAlert) {
      return (
        <Alert variant="destructive">
          <ShieldOff className="h-4 w-4" />
          <AlertTitle>Access Denied</AlertTitle>
          <AlertDescription>
            You don't have permission to {action} {module.toLowerCase()}.
          </AlertDescription>
        </Alert>
      )
    }
    return <>{fallback}</>
  }

  return <>{children}</>
}

// Convenience wrapper for checking module access
interface ModuleGuardProps {
  module: Module
  children: ReactNode
  fallback?: ReactNode
}

export function ModuleGuard({ module, children, fallback = null }: ModuleGuardProps) {
  const { hasModuleAccess } = usePermissions()

  if (!hasModuleAccess(module)) {
    return <>{fallback}</>
  }

  return <>{children}</>
}