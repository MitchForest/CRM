import { 
  LayoutDashboard, 
  Users, 
  Target, 
  Building2,
  TrendingUp,
  Calendar,
  HeadphonesIcon,
  Settings,
  LogOut,
  Brain,
  FileText,
  BookOpen,
  Activity,
  Heart,
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
    url: "/",
    icon: LayoutDashboard,
    module: null, // Dashboard is always visible
  },
  {
    title: "Leads",
    url: "/leads",
    icon: Target,
    module: "Leads" as const,
  },
  {
    title: "Contacts",
    url: "/contacts",
    icon: Users,
    module: "Contacts" as const,
  },
  {
    title: "Accounts",
    url: "/accounts",
    icon: Building2,
    module: "Accounts" as const,
  },
  {
    title: "Opportunities",
    url: "/opportunities",
    icon: TrendingUp,
    module: "Opportunities" as const,
  },
  {
    title: "Activities",
    url: "/activities",
    icon: Calendar,
    module: "Activities" as const,
  },
  {
    title: "Cases",
    url: "/cases",
    icon: HeadphonesIcon,
    module: "Cases" as const,
  },
]

const phase3NavItems = [
  {
    title: "AI Lead Scoring",
    url: "/leads/scoring",
    icon: Brain,
    module: "Leads" as const,
  },
  {
    title: "Forms",
    url: "/forms",
    icon: FileText,
    module: null, // Forms might be accessible to all
  },
  {
    title: "Knowledge Base",
    url: "/kb",
    icon: BookOpen,
    module: null, // KB might be accessible to all
  },
  {
    title: "Activity Tracking",
    url: "/tracking",
    icon: Activity,
    module: null, // Tracking might be accessible to all
  },
  {
    title: "Customer Health",
    url: "/health",
    icon: Heart,
    module: "Accounts" as const,
  },
  {
    title: "Chatbot",
    url: "/chatbot",
    icon: MessageCircle,
    module: null, // Chatbot settings might be accessible to all
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
          <h1 className="text-xl font-bold">SaaS CRM</h1>
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
          <SidebarGroupLabel>AI Features</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {phase3NavItems
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