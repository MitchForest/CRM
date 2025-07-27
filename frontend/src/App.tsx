import { useEffect, lazy, Suspense } from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
// import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { Layout } from '@/components/layout/Layout'
import { PublicLayout } from '@/components/layout/PublicLayout'
import { LoginPage } from '@/pages/Login'
import { useAuthStore } from '@/stores/auth-store'
import { DashboardPage } from '@/pages/Dashboard'
import { ContactsPage } from '@/pages/Contacts'
import { LeadsListPage } from '@/pages/LeadsList'
import { LeadDetailPage } from '@/pages/LeadDetail'
import { LeadFormPage } from '@/pages/LeadForm'
import { SettingsPage } from '@/pages/Settings'

// Marketing/Public Pages
import { Homepage } from '@/pages/marketing/Homepage'
import { Pricing } from '@/pages/marketing/Pricing'
import { GetStarted } from '@/pages/marketing/GetStarted'
import { Support } from '@/pages/marketing/Support'
import { DemoBooking } from '@/pages/marketing/DemoBooking'

// Opportunities
import { OpportunitiesPipeline } from '@/pages/opportunities/OpportunitiesPipeline'
import { OpportunityForm } from '@/pages/opportunities/OpportunityForm'
import { OpportunityDetailPage } from '@/pages/opportunities/OpportunityDetail'


// Cases
import { CasesList } from '@/pages/cases/CasesList'
import { CaseForm } from '@/pages/cases/CaseForm'
import { CaseDetail } from '@/pages/cases/CaseDetail'

// Phase 5 - Unified Contact View
import { ContactUnifiedView } from '@/pages/contacts/ContactUnifiedView'

// Phase 3 - Lazy load for better performance
const FormBuilderPage = lazy(() => import('@/pages/forms/FormBuilderPage').then(m => ({ default: m.FormBuilderPage })))
const FormsList = lazy(() => import('@/pages/forms/FormsList').then(m => ({ default: m.FormsList })))
const KnowledgeBaseAdmin = lazy(() => import('@/pages/kb/KnowledgeBaseAdmin').then(m => ({ default: m.KnowledgeBaseAdmin })))
const ArticleEditor = lazy(() => import('@/pages/kb/ArticleEditor').then(m => ({ default: m.ArticleEditor })))
const KnowledgeBasePublic = lazy(() => import('@/pages/kb/KnowledgeBasePublic').then(m => ({ default: m.KnowledgeBasePublic })))
// Phase 3 - Activity Tracking, Chatbot, and Health
const ActivityTrackingDashboard = lazy(() => import('@/pages/tracking/ActivityTrackingDashboard').then(m => ({ default: m.ActivityTrackingDashboard })))
const SessionDetail = lazy(() => import('@/pages/tracking/SessionDetail').then(m => ({ default: m.SessionDetail })))
const ChatbotSettings = lazy(() => import('@/pages/chatbot/ChatbotSettings').then(m => ({ default: m.ChatbotSettings })))



import { Toaster } from '@/components/ui/sonner'
import { ChatWidget } from '@/components/features/chatbot/ChatWidget'
import { activityTrackingService } from '@/services/activityTracking.service'

// Create a client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      gcTime: 10 * 60 * 1000, // 10 minutes
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

// Loading component for lazy loaded pages
const PageLoader = () => (
  <div className="flex items-center justify-center h-full">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
  </div>
)

function AppContent() {
  const { accessToken, refreshToken } = useAuthStore()

  useEffect(() => {
    // Sync auth tokens with API client when they change
    if (accessToken) {
      // The API client will pick up the token from localStorage via getStoredAuth
      // Force a re-read by updating the axios default headers
      // Update local storage to sync tokens
      // Store in expected format for getStoredAuth
      const stored = localStorage.getItem('auth-storage')
      if (stored) {
        try {
          const parsed = JSON.parse(stored)
          // Ensure the token is in the right place
          if (parsed.state && parsed.state.accessToken !== accessToken) {
            parsed.state.accessToken = accessToken
            parsed.state.refreshToken = refreshToken
            localStorage.setItem('auth-storage', JSON.stringify(parsed))
          }
        } catch (e) {
          console.error('Failed to sync auth storage:', e)
        }
      }
    }
  }, [accessToken, refreshToken])

  // Initialize activity tracking
  useEffect(() => {
    const handleRouteChange = () => {
      // Track all page views including public pages
      activityTrackingService.trackPageView({
        page_url: window.location.pathname + window.location.search,
        title: document.title,
      })
    }

    // Track initial page view
    handleRouteChange()

    // Listen for route changes
    window.addEventListener('popstate', handleRouteChange)
    
    return () => {
      window.removeEventListener('popstate', handleRouteChange)
    }
  }, [])

  return (
      <Router>
        <Routes>
          {/* Public Marketing Pages */}
          <Route path="/" element={<PublicLayout />}>
            <Route index element={<Homepage />} />
            <Route path="pricing" element={<Pricing />} />
            <Route path="get-started" element={<GetStarted />} />
            <Route path="support" element={<Support />} />
            <Route path="demo" element={<DemoBooking />} />
          </Route>
          
          {/* Public KB Routes */}
          <Route path="/kb/public">
            <Route index element={
              <Suspense fallback={<PageLoader />}>
                <KnowledgeBasePublic />
              </Suspense>
            } />
            <Route path=":slug" element={
              <Suspense fallback={<PageLoader />}>
                <KnowledgeBasePublic />
              </Suspense>
            } />
          </Route>
          
          {/* Auth Route */}
          <Route path="/login" element={<LoginPage />} />
          
          {/* Protected CRM Routes */}
          <Route path="/app" element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }>
            <Route index element={<DashboardPage />} />
            
            {/* Contacts Routes */}
            <Route path="contacts" element={<ContactsPage />} />
            <Route path="contacts/:id" element={<ContactUnifiedView />} />
            
            {/* Leads Routes */}
            <Route path="leads" element={<LeadsListPage />} />
            <Route path="leads/new" element={<LeadFormPage />} />
            <Route path="leads/:id" element={<LeadDetailPage />} />
            <Route path="leads/:id/edit" element={<LeadFormPage />} />
            
            
            {/* Opportunities Routes */}
            <Route path="opportunities" element={<OpportunitiesPipeline />} />
            <Route path="opportunities/new" element={<OpportunityForm />} />
            <Route path="opportunities/:id" element={<OpportunityDetailPage />} />
            <Route path="opportunities/:id/edit" element={<OpportunityForm />} />
            
            
            {/* Cases Routes */}
            <Route path="cases" element={<CasesList />} />
            <Route path="cases/new" element={<CaseForm />} />
            <Route path="cases/:id" element={<CaseDetail />} />
            <Route path="cases/:id/edit" element={<CaseForm />} />
            
            {/* Phase 3 Routes */}
            
            {/* Form Builder */}
            <Route path="forms" element={
              <Suspense fallback={<PageLoader />}>
                <FormsList />
              </Suspense>
            } />
            <Route path="forms/new" element={
              <Suspense fallback={<PageLoader />}>
                <FormBuilderPage />
              </Suspense>
            } />
            <Route path="forms/:id" element={
              <Suspense fallback={<PageLoader />}>
                <FormBuilderPage />
              </Suspense>
            } />
            
            {/* Knowledge Base */}
            <Route path="kb" element={
              <Suspense fallback={<PageLoader />}>
                <KnowledgeBaseAdmin />
              </Suspense>
            } />
            <Route path="kb/new" element={
              <Suspense fallback={<PageLoader />}>
                <ArticleEditor />
              </Suspense>
            } />
            <Route path="kb/edit/:id" element={
              <Suspense fallback={<PageLoader />}>
                <ArticleEditor />
              </Suspense>
            } />
            
            {/* Activity Tracking */}
            <Route path="tracking" element={
              <Suspense fallback={<PageLoader />}>
                <ActivityTrackingDashboard />
              </Suspense>
            } />
            <Route path="tracking/sessions/:id" element={
              <Suspense fallback={<PageLoader />}>
                <SessionDetail />
              </Suspense>
            } />
            
            {/* Chatbot Settings */}
            <Route path="chatbot" element={
              <Suspense fallback={<PageLoader />}>
                <ChatbotSettings />
              </Suspense>
            } />
            
            <Route path="settings" element={<SettingsPage />} />
          </Route>
          
          {/* Redirect old routes to /app */}
          <Route path="/contacts" element={<Navigate to="/app/contacts" replace />} />
          <Route path="/leads" element={<Navigate to="/app/leads" replace />} />
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        
        {/* Global Chat Widget - Show on all pages */}
        <ChatWidget 
          position="bottom-right"
          theme="auto"
          onLeadCapture={(_leadInfo) => {
            // Handle lead capture from chat
            queryClient.invalidateQueries({ queryKey: ['leads'] })
          }}
        />
      </Router>
  )
}

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppContent />
      <Toaster position="top-right" />
      {/* <ReactQueryDevtools initialIsOpen={false} /> */}
    </QueryClientProvider>
  )
}

export default App