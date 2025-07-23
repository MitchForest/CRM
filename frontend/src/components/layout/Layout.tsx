import { Outlet } from 'react-router-dom'
import { AppSidebar } from './app-sidebar'
import { Header } from './Header'
import { SidebarProvider, SidebarInset } from '@/components/ui/sidebar'

export function Layout() {
  return (
    <SidebarProvider>
      <div className="flex h-screen w-full">
        <AppSidebar />
        <SidebarInset className="flex flex-1 flex-col">
          <Header />
          <main className="flex-1 overflow-y-auto">
            <div className="container mx-auto py-6">
              <Outlet />
            </div>
          </main>
        </SidebarInset>
      </div>
    </SidebarProvider>
  )
}