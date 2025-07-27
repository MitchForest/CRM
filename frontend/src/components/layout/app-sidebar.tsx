import { 
  LayoutDashboard, 
  Users, 
  Target,
  TrendingUp,
  HeadphonesIcon,
  Settings,
  LogOut,
  FileText,
  BookOpen,
  Activity,
  MessageCircle
} from "lucide-react"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { useAuthStore } from '@/stores/auth-store'
import { apiClient } from '@/lib/api-client'
import { useNavigate, useLocation } from 'react-router-dom'
import { usePermissions } from '@/hooks/use-permissions'

const mainNavItems = [
  {
    title: "Dashboard",
    url: "/app",
    icon: LayoutDashboard,
    module: null, // Dashboard is always visible
  },
  {
    title: "Leads",
    url: "/app/leads",
    icon: Target,
    module: "Leads" as const,
  },
  {
    title: "Contacts",
    url: "/app/contacts",
    icon: Users,
    module: "Contacts" as const,
  },
  {
    title: "Opportunities",
    url: "/app/opportunities",
    icon: TrendingUp,
    module: "Opportunities" as const,
  },
  {
    title: "Support Tickets",
    url: "/app/cases",
    icon: HeadphonesIcon,
    module: "Cases" as const,
  },
]

const adminNavItems = [
  {
    title: "Knowledge Base",
    url: "/app/kb",
    icon: BookOpen,
    module: null,
  },
  {
    title: "Forms",
    url: "/app/forms",
    icon: FileText,
    module: null,
  },
  {
    title: "Chatbot",
    url: "/app/chatbot",
    icon: MessageCircle,
    module: null,
  },
  {
    title: "Tracking Script",
    url: "/app/tracking",
    icon: Activity,
    module: null,
  },
]

const bottomNavItems = [
  {
    title: "Settings",
    url: "/app/settings",
    icon: Settings,
  },
]

export function AppSidebar() {
  const logout = useAuthStore((state) => state.logout)
  const user = useAuthStore((state) => state.user)
  const navigate = useNavigate()
  const location = useLocation()
  const { hasModuleAccess } = usePermissions()

  const handleLogout = async () => {
    try {
      await apiClient.logout()
    } catch {
      // Even if logout fails on server, clear local state
    }
    logout()
    window.location.href = '/login'
  }

  return (
    <Sidebar>
      <SidebarHeader className="border-b">
        <div className="px-2 py-4">
          <h1 className="text-xl font-bold">Sassy CRM</h1>
        </div>
      </SidebarHeader>
      
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Navigation</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {mainNavItems
                .filter(item => !item.module || hasModuleAccess(item.module))
                .map((item) => (
                  <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton 
                      onClick={() => navigate(item.url)}
                      isActive={location.pathname === item.url || location.pathname.startsWith(item.url + '/')}
                    >
                      <item.icon className="h-4 w-4" />
                      <span>{item.title}</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarGroup>
          <SidebarGroupLabel>Admin</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {adminNavItems
                .filter(item => !item.module || hasModuleAccess(item.module))
                .map((item) => (
                  <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton 
                      onClick={() => navigate(item.url)}
                      isActive={location.pathname === item.url || location.pathname.startsWith(item.url + '/')}
                    >
                      <item.icon className="h-4 w-4" />
                      <span>{item.title}</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarGroup>
          <SidebarGroupLabel>System</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {bottomNavItems.map((item) => (
                <SidebarMenuItem key={item.url}>
                  <SidebarMenuButton 
                    onClick={() => navigate(item.url)}
                    isActive={location.pathname === item.url || location.pathname.startsWith(item.url + '/')}
                  >
                    <item.icon className="h-4 w-4" />
                    <span>{item.title}</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter className="border-t">
        <SidebarMenu>
          <SidebarMenuItem>
            <div className="px-2 py-2 text-sm">
              <p className="font-medium">{user?.firstName} {user?.lastName}</p>
              <p className="text-muted-foreground">{user?.email}</p>
            </div>
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={handleLogout}>
              <LogOut className="h-4 w-4" />
              <span>Logout</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  )
}