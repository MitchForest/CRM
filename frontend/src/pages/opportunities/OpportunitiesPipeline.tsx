import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, LayoutGrid, List } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { OpportunitiesKanban } from '@/components/features/opportunities/OpportunitiesKanban'
import { OpportunitiesTable } from '@/components/features/opportunities/OpportunitiesTable'
import { useOpportunitiesPipeline, usePipelineMetrics } from '@/hooks/use-opportunities'

export function OpportunitiesPipeline() {
  const [view, setView] = useState<'pipeline' | 'table'>('pipeline')
  const { data, isLoading } = useOpportunitiesPipeline()
  const metrics = usePipelineMetrics()

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  }

  return (
    <div className="h-full flex flex-col">
      <div className="flex items-center justify-between p-6 pb-4">
        <div>
          <h1 className="text-2xl font-semibold">Opportunities Pipeline</h1>
          <div className="mt-1 flex gap-4 text-sm text-muted-foreground">
            <span>Total: {formatCurrency(metrics.totalValue)}</span>
            <span>Weighted: {formatCurrency(metrics.weightedValue)}</span>
            <span>Count: {metrics.totalCount}</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button 
            variant="outline" 
            size="sm" 
            onClick={() => setView(view === 'pipeline' ? 'table' : 'pipeline')}
          >
            {view === 'pipeline' ? <List className="h-4 w-4" /> : <LayoutGrid className="h-4 w-4" />}
          </Button>
          <Button asChild>
            <Link to="/opportunities/new">
              <Plus className="mr-2 h-4 w-4" />
              New Opportunity
            </Link>
          </Button>
        </div>
      </div>

      <div className="flex-1 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-full">
            <p>Loading opportunities...</p>
          </div>
        ) : (
          <>
            {view === 'pipeline' ? (
              <OpportunitiesKanban opportunities={data?.opportunities || []} />
            ) : (
              <div className="p-6 pt-0">
                <OpportunitiesTable opportunities={data?.opportunities || []} />
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}