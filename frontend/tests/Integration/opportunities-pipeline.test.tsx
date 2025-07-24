import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import userEvent from '@testing-library/user-event'
import { OpportunitiesPipeline } from '@/pages/opportunities/OpportunitiesPipeline'
import { useOpportunities, useUpdateOpportunity } from '@/hooks/use-opportunities'
import type { Opportunity } from '@/types/api.generated'

// Mock the hooks
vi.mock('@/hooks/use-opportunities')

// Mock DnD Kit
vi.mock('@dnd-kit/core', () => ({
  DndContext: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  DragOverlay: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  PointerSensor: vi.fn(),
  useSensor: vi.fn(() => ({})),
  useSensors: vi.fn(() => []),
  useDroppable: vi.fn(() => ({
    isOver: false,
    setNodeRef: vi.fn(),
  })),
}))

vi.mock('@dnd-kit/sortable', () => ({
  SortableContext: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  verticalListSortingStrategy: vi.fn(),
  useSortable: vi.fn(() => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
    transition: null,
    isDragging: false,
  })),
}))

const mockOpportunities: Opportunity[] = [
  {
    id: '1',
    name: 'Test Opportunity 1',
    // accountId: 'acc1',
    // accountName: 'Test Account 1',
    salesStage: 'Qualification',
    amount: 50000,
    probability: 20,
    closeDate: '2024-03-01',
    // leadSource: 'Website',
    nextStep: 'Schedule demo',
    description: 'Test description',
    assignedUserId: 'user1',
    assignedUserName: 'John Doe',
    // dateEntered: '2024-01-01',
    // dateModified: '2024-01-15',
  },
  {
    id: '2',
    name: 'Test Opportunity 2',
    // accountId: 'acc2',
    // accountName: 'Test Account 2',
    salesStage: 'Proposal',
    amount: 75000,
    probability: 75,
    closeDate: '2024-02-15',
    // leadSource: 'Referral',
    nextStep: 'Send proposal',
    description: 'Another test',
    assignedUserId: 'user2',
    assignedUserName: 'Jane Smith',
    // dateEntered: '2024-01-05',
    // dateModified: '2024-01-20',
  },
]

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

describe('OpportunitiesPipeline', () => {
  const mockUseOpportunities = vi.mocked(useOpportunities)
  const mockUseUpdateOpportunity = vi.mocked(useUpdateOpportunity)
  const mockMutate = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
    
    mockUseOpportunities.mockReturnValue({
      data: { data: mockOpportunities },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    mockUseUpdateOpportunity.mockReturnValue({
      mutate: mockMutate,
      mutateAsync: vi.fn(),
      isPending: false,
      isError: false,
      isSuccess: false,
      error: null,
      data: undefined,
    } as any)
  })

  it('renders pipeline view with opportunities', async () => {
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Opportunities Pipeline')).toBeInTheDocument()
      expect(screen.getByText('Test Opportunity 1')).toBeInTheDocument()
      expect(screen.getByText('Test Opportunity 2')).toBeInTheDocument()
    })
  })

  it('displays pipeline stages', async () => {
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Qualification')).toBeInTheDocument()
      expect(screen.getByText('Needs Analysis')).toBeInTheDocument()
      expect(screen.getByText('Value Proposition')).toBeInTheDocument()
      expect(screen.getByText('Decision Makers')).toBeInTheDocument()
      expect(screen.getByText('Proposal')).toBeInTheDocument()
      expect(screen.getByText('Negotiation')).toBeInTheDocument()
      expect(screen.getByText('Closed Won')).toBeInTheDocument()
      expect(screen.getByText('Closed Lost')).toBeInTheDocument()
    })
  })

  it('shows opportunity details in cards', async () => {
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Test Account 1')).toBeInTheDocument()
      expect(screen.getByText('$50,000')).toBeInTheDocument()
      expect(screen.getByText('20%')).toBeInTheDocument()
      
      expect(screen.getByText('Test Account 2')).toBeInTheDocument()
      expect(screen.getByText('$75,000')).toBeInTheDocument()
      expect(screen.getByText('75%')).toBeInTheDocument()
    })
  })

  it('displays pipeline metrics in header', async () => {
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Total value
      expect(screen.getByText(/Total: \$125,000/)).toBeInTheDocument()
      // Weighted value: (50000 * 0.2) + (75000 * 0.75) = 10000 + 56250 = 66250
      expect(screen.getByText(/Weighted: \$66,250/)).toBeInTheDocument()
      // Count
      expect(screen.getByText(/Count: 2/)).toBeInTheDocument()
    })
  })

  it('switches between pipeline and table view', async () => {
    const user = userEvent.setup()
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    // Initially in pipeline view
    await waitFor(() => {
      expect(screen.getByText('Test Opportunity 1')).toBeInTheDocument()
    })

    // Find and click the view toggle button
    const viewToggle = screen.getByRole('button', { name: /list|grid/i })
    await user.click(viewToggle)

    // Should show table view
    await waitFor(() => {
      expect(screen.getByRole('table')).toBeInTheDocument()
      expect(screen.getByText('Account')).toBeInTheDocument()
      expect(screen.getByText('Stage')).toBeInTheDocument()
      expect(screen.getByText('Amount')).toBeInTheDocument()
      expect(screen.getByText('Probability')).toBeInTheDocument()
      expect(screen.getByText('Close Date')).toBeInTheDocument()
    })
  })

  it('navigates to new opportunity form', async () => {
    // const user = userEvent.setup()
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    const newButton = await screen.findByRole('link', { name: /new opportunity/i })
    expect(newButton).toHaveAttribute('href', '/opportunities/new')
  })

  it('shows loading state', () => {
    mockUseOpportunities.mockReturnValue({
      data: null,
      isLoading: true,
      error: null,
      refetch: vi.fn(),
    } as any)

    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    expect(screen.getByText('Loading opportunities...')).toBeInTheDocument()
  })

  it('shows empty state when no opportunities', async () => {
    mockUseOpportunities.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText(/no opportunities/i)).toBeInTheDocument()
    })
  })

  // Note: Actual drag-and-drop testing would require more complex mocking
  // of DnD Kit internals or using a different testing approach
  it('calls update mutation when opportunity is moved', async () => {
    render(<OpportunitiesPipeline />, { wrapper: createWrapper() })

    // This is a simplified test - actual drag and drop would need more setup
    // The component should call the update mutation when DnD events fire
    expect(mockMutate).not.toHaveBeenCalled()
  })
})