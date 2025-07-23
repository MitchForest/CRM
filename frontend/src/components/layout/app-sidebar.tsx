import { 
  LayoutDashboard, 
  Users, 
  Target, 
  Building2,
  Settings,
  LogOut
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

const mainNavItems = [
  {
    title: "Dashboard",
    url: "/",
    icon: LayoutDashboard,
  },
  {
    title: "Leads",
    url: "/leads",
    icon: Target,
  },
  {
    title: "Contacts",
    url: "/contacts",
    icon: Users,
  },
  {
    title: "Accounts",
    url: "/accounts",
    icon: Building2,
  },
]

const bottomNavItems = [
  {
    title: "Settings",
    url: "/settings",
    icon: Settings,
  },
]

export function AppSidebar() {
  const logout = useAuthStore((state) => state.logout)
  const user = useAuthStore((state) => state.user)
  const navigate = useNavigate()
  const location = useLocation()

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
          <h1 className="text-xl font-bold">SaaS CRM</h1>
        </div>
      </SidebarHeader>
      
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Navigation</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {mainNavItems.map((item) => (
                <SidebarMenuItem key={item.url}>
                  <SidebarMenuButton 
                    onClick={() => navigate(item.url)}
                    isActive={location.pathname === item.url}
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
                    isActive={location.pathname === item.url}
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