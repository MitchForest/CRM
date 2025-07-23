import { useState, useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Plus, DollarSign, Calendar, User, ChevronRight, MoreHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { DataTable } from '@/components/ui/data-table'
import { Skeleton } from '@/components/ui/skeleton'
import { useOpportunities } from '@/hooks/use-opportunities'
import { formatDate, formatCurrency } from '@/lib/utils'
import type { Opportunity } from '@/types/api.generated'
import { type ColumnDef } from '@tanstack/react-table'

// Sales stages for pipeline view
const SALES_STAGES = [
  { id: 'prospecting', label: 'Prospecting', color: 'bg-slate-100' },
  { id: 'qualification', label: 'Qualification', color: 'bg-blue-100' },
  { id: 'proposal', label: 'Proposal', color: 'bg-yellow-100' },
  { id: 'negotiation', label: 'Negotiation', color: 'bg-orange-100' },
  { id: 'closed-won', label: 'Closed Won', color: 'bg-green-100' },
  { id: 'closed-lost', label: 'Closed Lost', color: 'bg-red-100' },
]

// Table columns definition
const columns: ColumnDef<Opportunity>[] = [
  {
    accessorKey: 'name',
    header: 'Opportunity',
    cell: ({ row }) => {
      const opportunity = row.original
      return (
        <Link 
          to={`/opportunities/${opportunity.id}`}
          className="font-medium hover:underline"
        >
          {opportunity.name}
        </Link>
      )
    },
  },
  {
    accessorKey: 'contactName',
    header: 'Contact',
    cell: ({ row }) => row.original.contactName || '-',
  },
  {
    accessorKey: 'amount',
    header: 'Amount',
    cell: ({ row }) => formatCurrency(row.original.amount),
  },
  {
    accessorKey: 'salesStage',
    header: 'Stage',
    cell: ({ row }) => {
      const stage = SALES_STAGES.find(s => s.id === row.original.salesStage)
      return (
        <Badge variant="secondary" className={stage?.color}>
          {stage?.label || row.original.salesStage}
        </Badge>
      )
    },
  },
  {
    accessorKey: 'closeDate',
    header: 'Close Date',
    cell: ({ row }) => formatDate(row.original.closeDate),
  },
  {
    accessorKey: 'probability',
    header: 'Probability',
    cell: ({ row }) => `${row.original.probability || 0}%`,
  },
  {
    id: 'actions',
    cell: ({ row }) => {
      const opportunity = row.original
      return (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-8 w-8 p-0">
              <span className="sr-only">Open menu</span>
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>
            <DropdownMenuItem asChild>
              <Link to={`/opportunities/${opportunity.id}`}>View details</Link>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
              <Link to={`/opportunities/${opportunity.id}/edit`}>Edit</Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem className="text-red-600">Delete</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      )
    },
  },
]

// Pipeline card component
function OpportunityCard({ opportunity }: { opportunity: Opportunity }) {
  const navigate = useNavigate()
  
  return (
    <Card 
      className="cursor-pointer hover:shadow-md transition-shadow"
      onClick={() => navigate(`/opportunities/${opportunity.id}`)}
    >
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between">
          <h4 className="font-semibold text-sm line-clamp-2">{opportunity.name}</h4>
          <ChevronRight className="h-4 w-4 text-muted-foreground flex-shrink-0" />
        </div>
      </CardHeader>
      <CardContent className="space-y-2">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <DollarSign className="h-3 w-3" />
          <span className="font-medium text-foreground">
            {formatCurrency(opportunity.amount)}
          </span>
        </div>
        {opportunity.contactName && (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <User className="h-3 w-3" />
            <span className="truncate">{opportunity.contactName}</span>
          </div>
        )}
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Calendar className="h-3 w-3" />
          <span>{formatDate(opportunity.closeDate)}</span>
        </div>
        {opportunity.probability && (
          <div className="flex items-center justify-between">
            <span className="text-xs text-muted-foreground">Probability</span>
            <Badge variant="outline" className="text-xs">
              {opportunity.probability}%
            </Badge>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

// Pipeline column component
function PipelineColumn({ 
  stage, 
  opportunities,
  totalValue 
}: { 
  stage: typeof SALES_STAGES[0]
  opportunities: Opportunity[]
  totalValue: number
}) {
  return (
    <div className="flex-1 min-w-[300px]">
      <div className={`rounded-t-lg px-4 py-3 ${stage.color}`}>
        <div className="flex items-center justify-between">
          <h3 className="font-semibold">{stage.label}</h3>
          <Badge variant="secondary" className="ml-2">
            {opportunities.length}
          </Badge>
        </div>
        <p className="text-sm text-muted-foreground mt-1">
          {formatCurrency(totalValue)}
        </p>
      </div>
      <div className="bg-muted/20 rounded-b-lg p-3 min-h-[400px] space-y-3">
        {opportunities.map((opp) => (
          <OpportunityCard key={opp.id} opportunity={opp} />
        ))}
      </div>
    </div>
  )
}

export default function OpportunitiesList() {
  const navigate = useNavigate()
  const [view, setView] = useState<'pipeline' | 'table'>('pipeline')
  const { data, isLoading, error } = useOpportunities()

  // Group opportunities by stage for pipeline view
  const opportunitiesByStage = useMemo(() => {
    if (!data?.data) return {}
    
    const grouped = data.data.reduce((acc, opp) => {
      const stage = opp.salesStage || 'prospecting'
      if (!acc[stage]) acc[stage] = []
      acc[stage].push(opp)
      return acc
    }, {} as Record<string, Opportunity[]>)
    
    return grouped
  }, [data])

  // Calculate total value by stage
  const totalValueByStage = useMemo(() => {
    const totals: Record<string, number> = {}
    SALES_STAGES.forEach(stage => {
      const opps = opportunitiesByStage[stage.id] || []
      totals[stage.id] = opps.reduce((sum, opp) => sum + (opp.amount || 0), 0)
    })
    return totals
  }, [opportunitiesByStage])

  if (isLoading) {
    return (
      <div className="p-8 space-y-6">
        <Skeleton className="h-8 w-48" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Skeleton className="h-[400px]" />
          <Skeleton className="h-[400px]" />
          <Skeleton className="h-[400px]" />
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="p-8">
        <div className="text-center text-red-600">
          Error loading opportunities: {error.message}
        </div>
      </div>
    )
  }

  return (
    <div className="p-8 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Opportunities</h1>
          <p className="text-muted-foreground mt-1">
            Manage your sales pipeline and track deals
          </p>
        </div>
        <Button onClick={() => navigate('/opportunities/new')}>
          <Plus className="mr-2 h-4 w-4" />
          New Opportunity
        </Button>
      </div>

      <Tabs value={view} onValueChange={(v) => setView(v as 'pipeline' | 'table')}>
        <TabsList>
          <TabsTrigger value="pipeline">Pipeline View</TabsTrigger>
          <TabsTrigger value="table">Table View</TabsTrigger>
        </TabsList>

        <TabsContent value="pipeline" className="mt-6">
          <div className="flex gap-4 overflow-x-auto pb-4">
            {SALES_STAGES.map((stage) => (
              <PipelineColumn
                key={stage.id}
                stage={stage}
                opportunities={opportunitiesByStage[stage.id] || []}
                totalValue={totalValueByStage[stage.id] || 0}
              />
            ))}
          </div>
        </TabsContent>

        <TabsContent value="table" className="mt-6">
          <DataTable 
            columns={columns} 
            data={data?.data || []} 
          />
        </TabsContent>
      </Tabs>
    </div>
  )
}