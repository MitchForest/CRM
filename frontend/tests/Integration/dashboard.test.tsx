import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import { DashboardPage } from '@/pages/Dashboard'
import { useDashboard } from '@/hooks/use-dashboard'

// Mock the dashboard hook
vi.mock('@/hooks/use-dashboard')

// Mock recharts to avoid rendering issues in tests
vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: any) => <div>{children}</div>,
  BarChart: ({ children }: any) => <div data-testid="bar-chart">{children}</div>,
  Bar: () => null,
  PieChart: ({ children }: any) => <div data-testid="pie-chart">{children}</div>,
  Pie: () => null,
  Cell: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  Legend: () => null,
}))

const mockDashboardData = {
  metrics: {
    totalLeads: 150,
    totalAccounts: 75,
    newLeadsToday: 12,
    pipelineValue: 1250000,
    callsToday: 8,
    meetingsToday: 3,
    tasksOverdue: 5,
    openCases: 15,
  },
  pipelineData: [
    { stage: 'Qualification', count: 10, value: 150000 },
    { stage: 'Needs Analysis', count: 8, value: 200000 },
    { stage: 'Value Proposition', count: 6, value: 300000 },
    { stage: 'Decision Makers', count: 4, value: 250000 },
    { stage: 'Proposal', count: 3, value: 350000 },
  ],
  casesByPriority: [
    { priority: 'P1', count: 2 },
    { priority: 'P2', count: 8 },
    { priority: 'P3', count: 5 },
  ],
  recentActivities: [
    {
      id: '1',
      name: 'Call with John Doe',
      parentName: 'Acme Corp',
      assignedUserName: 'Sales Rep',
      status: 'Completed',
    },
    {
      id: '2',
      name: 'Follow up meeting',
      parentName: 'Tech Solutions',
      assignedUserName: 'Sales Manager',
      status: 'Scheduled',
    },
  ],
}

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  )
}

describe('DashboardPage', () => {
  const mockUseDashboard = vi.mocked(useDashboard)

  beforeEach(() => {
    vi.clearAllMocks()
    
    mockUseDashboard.mockReturnValue({
      metrics: mockDashboardData.metrics,
      pipelineData: mockDashboardData.pipelineData,
      casesByPriority: mockDashboardData.casesByPriority,
      recentActivities: mockDashboardData.recentActivities,
      isLoading: false,
      error: null,
    } as any)
  })

  it('renders dashboard header', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument()
    })
  })

  it('displays key metrics cards', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Check metric titles
      expect(screen.getByText('Total Leads')).toBeInTheDocument()
      expect(screen.getByText('Total Accounts')).toBeInTheDocument()
      expect(screen.getByText('New Leads Today')).toBeInTheDocument()
      expect(screen.getByText('Pipeline Value')).toBeInTheDocument()

      // Check metric values
      expect(screen.getByText('150')).toBeInTheDocument()
      expect(screen.getByText('75')).toBeInTheDocument()
      expect(screen.getByText('12')).toBeInTheDocument()
      expect(screen.getByText('$1,250,000')).toBeInTheDocument()
    })
  })

  it('displays activity metrics', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText("Today's Calls")).toBeInTheDocument()
      expect(screen.getByText("Today's Meetings")).toBeInTheDocument()
      expect(screen.getByText('Overdue Tasks')).toBeInTheDocument()
      expect(screen.getByText('Open Cases')).toBeInTheDocument()

      expect(screen.getByText('8')).toBeInTheDocument()
      expect(screen.getByText('3')).toBeInTheDocument()
      expect(screen.getByText('5')).toBeInTheDocument()
      expect(screen.getByText('15')).toBeInTheDocument()
    })
  })

  it('renders sales pipeline chart', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Sales Pipeline')).toBeInTheDocument()
      expect(screen.getByTestId('bar-chart')).toBeInTheDocument()
    })
  })

  it('renders cases by priority chart', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Cases by Priority')).toBeInTheDocument()
      expect(screen.getByTestId('pie-chart')).toBeInTheDocument()
    })
  })

  it('displays recent activity section', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Recent Activity')).toBeInTheDocument()
      expect(screen.getByText('Call with John Doe')).toBeInTheDocument()
      expect(screen.getByText('Acme Corp • Sales Rep')).toBeInTheDocument()
      expect(screen.getByText('Follow up meeting')).toBeInTheDocument()
      expect(screen.getByText('Tech Solutions • Sales Manager')).toBeInTheDocument()
    })
  })

  it('shows activity tabs', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: 'All' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Leads' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Opportunities' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Cases' })).toBeInTheDocument()
    })
  })

  it('shows loading state', () => {
    mockUseDashboard.mockReturnValue({
      metrics: null,
      pipelineData: null,
      casesByPriority: null,
      recentActivities: null,
      isLoading: true,
      error: null,
    } as any)

    render(<DashboardPage />, { wrapper: createWrapper() })

    expect(screen.getByText('Loading pipeline data...')).toBeInTheDocument()
  })

  it('handles empty pipeline data', async () => {
    mockUseDashboard.mockReturnValue({
      metrics: mockDashboardData.metrics,
      pipelineData: [],
      casesByPriority: [],
      recentActivities: [],
      isLoading: false,
      error: null,
    } as any)

    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('No case data available')).toBeInTheDocument()
    })
  })

  it('formats currency values correctly', async () => {
    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Pipeline value should be formatted with commas and dollar sign
      expect(screen.getByText('$1,250,000')).toBeInTheDocument()
    })
  })

  it('handles error state', async () => {
    mockUseDashboard.mockReturnValue({
      metrics: null,
      pipelineData: null,
      casesByPriority: null,
      recentActivities: null,
      isLoading: false,
      error: new Error('Failed to load dashboard'),
    } as any)

    render(<DashboardPage />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText(/error|failed/i)).toBeInTheDocument()
    })
  })

  it('updates data on refresh interval', async () => {
    const { rerender } = render(<Dashboard />, { wrapper: createWrapper() })

    // Initial render
    expect(mockUseDashboard).toHaveBeenCalledTimes(1)

    // Simulate data update
    const updatedData = {
      ...mockDashboardData,
      metrics: {
        ...mockDashboardData.metrics,
        newLeadsToday: 15,
      },
    }

    mockUseDashboard.mockReturnValue({
      metrics: updatedData.metrics,
      pipelineData: updatedData.pipelineData,
      casesByPriority: updatedData.casesByPriority,
      recentActivities: updatedData.recentActivities,
      isLoading: false,
      error: null,
    } as any)

    rerender(<Dashboard />)

    await waitFor(() => {
      expect(screen.getByText('15')).toBeInTheDocument()
    })
  })
})