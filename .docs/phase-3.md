# Phase 3: Complete Feature Set - Implementation Plan

## Overview

Phase 3 completes the core CRM functionality by implementing the remaining modules (Leads, Opportunities, Quotes, Cases) and essential features like search, filtering, and bulk actions. By the end of this phase, we'll have a fully functional B2C CRM ready for AI enhancements.

**Duration**: 2 weeks (Weeks 7-8)  
**Team Size**: 1-2 frontend developers  
**Prerequisites**: Phase 1 & 2 completed

## Week 7: Leads & Opportunities

### Day 1-2: Leads Module

#### 1. Leads Page
```typescript
// frontend/src/pages/Leads.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search, Filter, Download, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { LeadsTable } from '@/components/leads/LeadsTable'
import { CreateLeadModal } from '@/components/leads/CreateLeadModal'
import { LeadFilters } from '@/components/leads/LeadFilters'
import { apiClient } from '@/lib/api-client'
import { Lead } from '@/types/api'

export function LeadsPage() {
  const [page, setPage] = useState(1)
  const [searchTerm, setSearchTerm] = useState('')
  const [filters, setFilters] = useState<any>({})
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isFiltersOpen, setIsFiltersOpen] = useState(false)
  const [selectedLeads, setSelectedLeads] = useState<string[]>([])

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['leads', page, searchTerm, filters],
    queryFn: () => apiClient.getLeads({ 
      page, 
      limit: 20,
      filters: {
        ...filters,
        ...(searchTerm ? { 
          _or: [
            { email: { like: searchTerm } },
            { first_name: { like: searchTerm } },
            { last_name: { like: searchTerm } }
          ]
        } : {})
      }
    }),
  })

  const handleBulkConvert = async () => {
    if (selectedLeads.length === 0) return
    
    // Show bulk convert modal
    console.log('Converting leads:', selectedLeads)
  }

  const handleExport = () => {
    // Export filtered leads
    console.log('Exporting leads...')
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Leads</h1>
          <p className="text-gray-500">Manage and convert your prospects</p>
        </div>
        <div className="flex items-center gap-3">
          <Button variant="outline" onClick={handleExport}>
            <Download className="mr-2 h-4 w-4" />
            Export
          </Button>
          <Button onClick={() => setIsCreateModalOpen(true)}>
            <Plus className="mr-2 h-4 w-4" />
            Add Lead
          </Button>
        </div>
      </div>

      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search leads..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Button 
          variant="outline"
          onClick={() => setIsFiltersOpen(!isFiltersOpen)}
        >
          <Filter className="mr-2 h-4 w-4" />
          Filters
          {Object.keys(filters).length > 0 && (
            <span className="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-white">
              {Object.keys(filters).length}
            </span>
          )}
        </Button>
      </div>

      {isFiltersOpen && (
        <LeadFilters
          filters={filters}
          onFiltersChange={setFilters}
          onClose={() => setIsFiltersOpen(false)}
        />
      )}

      {selectedLeads.length > 0 && (
        <div className="flex items-center gap-4 rounded-lg bg-gray-50 p-4">
          <span className="text-sm font-medium">
            {selectedLeads.length} leads selected
          </span>
          <Button size="sm" onClick={handleBulkConvert}>
            Convert to Contacts
          </Button>
          <Button size="sm" variant="outline">
            Send Email
          </Button>
          <Button size="sm" variant="destructive">
            Delete
          </Button>
        </div>
      )}

      <LeadsTable
        leads={data?.data || []}
        pagination={data?.pagination}
        isLoading={isLoading}
        selectedLeads={selectedLeads}
        onSelectionChange={setSelectedLeads}
        onPageChange={setPage}
        onConvert={(lead) => {
          // Handle single lead conversion
          console.log('Converting lead:', lead)
        }}
      />

      <CreateLeadModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSuccess={() => {
          refetch()
          setIsCreateModalOpen(false)
        }}
      />
    </div>
  )
}
```

#### 2. Leads Table Component
```typescript
// frontend/src/components/leads/LeadsTable.tsx
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Lead } from '@/types/api'
import { formatDate } from '@/lib/utils'
import { MoreHorizontal, UserPlus, Mail, Phone } from 'lucide-react'
import { ConvertLeadModal } from './ConvertLeadModal'

interface LeadsTableProps {
  leads: Lead[]
  pagination?: {
    page: number
    pages: number
    total: number
  }
  isLoading: boolean
  selectedLeads: string[]
  onSelectionChange: (ids: string[]) => void
  onPageChange: (page: number) => void
  onConvert: (lead: Lead) => void
}

const sourceColors = {
  website: 'bg-blue-100 text-blue-700',
  trial: 'bg-purple-100 text-purple-700',
  webinar: 'bg-green-100 text-green-700',
  referral: 'bg-yellow-100 text-yellow-700',
  ad: 'bg-red-100 text-red-700',
}

const statusColors = {
  new: 'bg-gray-100 text-gray-700',
  contacted: 'bg-blue-100 text-blue-700',
  qualified: 'bg-green-100 text-green-700',
  converted: 'bg-purple-100 text-purple-700',
}

export function LeadsTable({ 
  leads, 
  pagination, 
  isLoading,
  selectedLeads,
  onSelectionChange,
  onPageChange,
  onConvert
}: LeadsTableProps) {
  const navigate = useNavigate()
  const [convertingLead, setConvertingLead] = useState<Lead | null>(null)

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      onSelectionChange(leads.map(l => l.id))
    } else {
      onSelectionChange([])
    }
  }

  const handleSelectOne = (leadId: string, checked: boolean) => {
    if (checked) {
      onSelectionChange([...selectedLeads, leadId])
    } else {
      onSelectionChange(selectedLeads.filter(id => id !== leadId))
    }
  }

  if (isLoading) {
    return <div>Loading leads...</div>
  }

  return (
    <>
      <div className="rounded-lg border bg-white">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-12">
                <Checkbox
                  checked={selectedLeads.length === leads.length && leads.length > 0}
                  onCheckedChange={handleSelectAll}
                />
              </TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Source</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Score</TableHead>
              <TableHead>Created</TableHead>
              <TableHead className="w-12"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {leads.map((lead) => (
              <TableRow key={lead.id}>
                <TableCell>
                  <Checkbox
                    checked={selectedLeads.includes(lead.id)}
                    onCheckedChange={(checked) => 
                      handleSelectOne(lead.id, checked as boolean)
                    }
                  />
                </TableCell>
                <TableCell>
                  <div className="font-medium">
                    {lead.firstName} {lead.lastName}
                  </div>
                  {lead.productInterest && (
                    <div className="text-sm text-gray-500">
                      Interested in: {lead.productInterest}
                    </div>
                  )}
                </TableCell>
                <TableCell>{lead.email}</TableCell>
                <TableCell>
                  <Badge 
                    variant="secondary" 
                    className={sourceColors[lead.source] || 'bg-gray-100'}
                  >
                    {lead.source}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Badge 
                    variant="secondary"
                    className={statusColors[lead.status] || 'bg-gray-100'}
                  >
                    {lead.status}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <div className="h-2 w-16 rounded-full bg-gray-200">
                      <div 
                        className="h-full rounded-full bg-blue-500"
                        style={{ width: `${lead.score}%` }}
                      />
                    </div>
                    <span className="text-sm font-medium">{lead.score}</span>
                  </div>
                </TableCell>
                <TableCell className="text-sm text-gray-500">
                  {formatDate(lead.dateEntered)}
                </TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon">
                        <MoreHorizontal className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onClick={() => setConvertingLead(lead)}>
                        <UserPlus className="mr-2 h-4 w-4" />
                        Convert to Contact
                      </DropdownMenuItem>
                      <DropdownMenuItem>
                        <Mail className="mr-2 h-4 w-4" />
                        Send Email
                      </DropdownMenuItem>
                      <DropdownMenuItem>
                        <Phone className="mr-2 h-4 w-4" />
                        Call
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {convertingLead && (
        <ConvertLeadModal
          lead={convertingLead}
          isOpen={true}
          onClose={() => setConvertingLead(null)}
          onSuccess={() => {
            setConvertingLead(null)
            // Refresh data
          }}
        />
      )}
    </>
  )
}
```

#### 3. Lead Filters Component
```typescript
// frontend/src/components/leads/LeadFilters.tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Slider } from '@/components/ui/slider'
import { Button } from '@/components/ui/button'
import { X } from 'lucide-react'

interface LeadFiltersProps {
  filters: any
  onFiltersChange: (filters: any) => void
  onClose: () => void
}

export function LeadFilters({ filters, onFiltersChange, onClose }: LeadFiltersProps) {
  const handleFilterChange = (key: string, value: any) => {
    if (value === 'all' || value === undefined) {
      const { [key]: _, ...rest } = filters
      onFiltersChange(rest)
    } else {
      onFiltersChange({ ...filters, [key]: value })
    }
  }

  const clearFilters = () => {
    onFiltersChange({})
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-3">
        <CardTitle className="text-base">Filters</CardTitle>
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={clearFilters}
            disabled={Object.keys(filters).length === 0}
          >
            Clear all
          </Button>
          <Button
            variant="ghost"
            size="icon"
            onClick={onClose}
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid gap-4 md:grid-cols-4">
          <div className="space-y-2">
            <Label>Source</Label>
            <Select
              value={filters.source || 'all'}
              onValueChange={(value) => handleFilterChange('source', value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Sources</SelectItem>
                <SelectItem value="website">Website</SelectItem>
                <SelectItem value="trial">Trial</SelectItem>
                <SelectItem value="webinar">Webinar</SelectItem>
                <SelectItem value="referral">Referral</SelectItem>
                <SelectItem value="ad">Ad Campaign</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>Status</Label>
            <Select
              value={filters.status || 'all'}
              onValueChange={(value) => handleFilterChange('status', value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem value="new">New</SelectItem>
                <SelectItem value="contacted">Contacted</SelectItem>
                <SelectItem value="qualified">Qualified</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>
              Lead Score: {filters.minScore || 0} - {filters.maxScore || 100}
            </Label>
            <Slider
              value={[filters.minScore || 0, filters.maxScore || 100]}
              onValueChange={([min, max]) => {
                handleFilterChange('minScore', min)
                handleFilterChange('maxScore', max)
              }}
              min={0}
              max={100}
              step={10}
              className="mt-2"
            />
          </div>

          <div className="space-y-2">
            <Label>Date Range</Label>
            <Select
              value={filters.dateRange || 'all'}
              onValueChange={(value) => handleFilterChange('dateRange', value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Time</SelectItem>
                <SelectItem value="today">Today</SelectItem>
                <SelectItem value="week">This Week</SelectItem>
                <SelectItem value="month">This Month</SelectItem>
                <SelectItem value="quarter">This Quarter</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
```

#### 4. Convert Lead Modal
```typescript
// frontend/src/components/leads/ConvertLeadModal.tsx
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Lead } from '@/types/api'
import { apiClient } from '@/lib/api-client'
import { Loader2, UserPlus, TrendingUp } from 'lucide-react'

interface ConvertLeadModalProps {
  lead: Lead
  isOpen: boolean
  onClose: () => void
  onSuccess: () => void
}

export function ConvertLeadModal({ lead, isOpen, onClose, onSuccess }: ConvertLeadModalProps) {
  const navigate = useNavigate()
  const [createOpportunity, setCreateOpportunity] = useState(true)
  const [opportunityName, setOpportunityName] = useState(
    `${lead.firstName} ${lead.lastName} - ${lead.productInterest || 'Opportunity'}`
  )
  const [opportunityAmount, setOpportunityAmount] = useState('')

  const convertMutation = useMutation({
    mutationFn: () => apiClient.convertLead(lead.id, {
      create_opportunity: createOpportunity,
      opportunity_name: opportunityName,
      opportunity_amount: parseFloat(opportunityAmount) || 0,
    }),
    onSuccess: (data) => {
      onSuccess()
      // Navigate to the new contact
      navigate(`/contacts/${data.data.contact.id}`)
    },
  })

  const handleConvert = () => {
    convertMutation.mutate()
  }

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Convert Lead</DialogTitle>
          <DialogDescription>
            Convert {lead.firstName} {lead.lastName} to a contact
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6 py-4">
          <div className="rounded-lg border p-4">
            <div className="flex items-center gap-3">
              <UserPlus className="h-5 w-5 text-gray-500" />
              <div>
                <p className="font-medium">Contact will be created</p>
                <p className="text-sm text-gray-500">
                  {lead.firstName} {lead.lastName} - {lead.email}
                </p>
              </div>
            </div>
          </div>

          <div className="space-y-4">
            <div className="flex items-center space-x-2">
              <Checkbox
                id="create-opportunity"
                checked={createOpportunity}
                onCheckedChange={(checked) => setCreateOpportunity(checked as boolean)}
              />
              <Label htmlFor="create-opportunity" className="flex items-center gap-2">
                <TrendingUp className="h-4 w-4" />
                Create an opportunity
              </Label>
            </div>

            {createOpportunity && (
              <div className="ml-6 space-y-4">
                <div className="space-y-2">
                  <Label>Opportunity Name</Label>
                  <Input
                    value={opportunityName}
                    onChange={(e) => setOpportunityName(e.target.value)}
                    placeholder="Enter opportunity name"
                  />
                </div>

                <div className="space-y-2">
                  <Label>Expected Value</Label>
                  <Input
                    type="number"
                    value={opportunityAmount}
                    onChange={(e) => setOpportunityAmount(e.target.value)}
                    placeholder="0.00"
                  />
                </div>
              </div>
            )}
          </div>

          {lead.score > 70 && (
            <div className="rounded-lg bg-green-50 p-4">
              <p className="text-sm text-green-800">
                <strong>High-quality lead!</strong> This lead has a score of {lead.score}, 
                indicating a high probability of conversion.
              </p>
            </div>
          )}
        </div>

        <div className="flex justify-end gap-3">
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button 
            onClick={handleConvert}
            disabled={convertMutation.isPending}
          >
            {convertMutation.isPending && (
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            )}
            Convert Lead
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
```

### Day 3-4: Opportunities Module

#### 1. Opportunities Pipeline Page
```typescript
// frontend/src/pages/Opportunities.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { DragDropContext, Droppable, Draggable, DropResult } from '@hello-pangea/dnd'
import { Plus, Filter, BarChart3 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { OpportunityCard } from '@/components/opportunities/OpportunityCard'
import { CreateOpportunityModal } from '@/components/opportunities/CreateOpportunityModal'
import { OpportunityFilters } from '@/components/opportunities/OpportunityFilters'
import { apiClient } from '@/lib/api-client'
import { Opportunity } from '@/types/api'
import { cn } from '@/lib/utils'

const stages = [
  { id: 'trial', name: 'Trial', color: 'bg-blue-500' },
  { id: 'negotiation', name: 'Negotiation', color: 'bg-yellow-500' },
  { id: 'closing', name: 'Closing', color: 'bg-purple-500' },
  { id: 'won', name: 'Won', color: 'bg-green-500' },
  { id: 'lost', name: 'Lost', color: 'bg-red-500' },
]

export function OpportunitiesPage() {
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isFiltersOpen, setIsFiltersOpen] = useState(false)
  const [filters, setFilters] = useState<any>({})
  const [viewMode, setViewMode] = useState<'pipeline' | 'list'>('pipeline')

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['opportunities', filters],
    queryFn: () => apiClient.getOpportunities({ 
      limit: 100,
      filters 
    }),
  })

  const opportunities = data?.data || []

  // Group opportunities by stage
  const opportunitiesByStage = stages.reduce((acc, stage) => {
    acc[stage.id] = opportunities.filter(opp => opp.stage === stage.id)
    return acc
  }, {} as Record<string, Opportunity[]>)

  // Calculate totals
  const stageTotals = stages.map(stage => ({
    ...stage,
    count: opportunitiesByStage[stage.id].length,
    value: opportunitiesByStage[stage.id].reduce((sum, opp) => sum + opp.amount, 0),
  }))

  const handleDragEnd = async (result: DropResult) => {
    if (!result.destination) return

    const { draggableId, source, destination } = result
    
    if (source.droppableId === destination.droppableId) return

    // Update opportunity stage
    try {
      await apiClient.updateOpportunity(draggableId, {
        stage: destination.droppableId,
      })
      refetch()
    } catch (error) {
      console.error('Failed to update opportunity:', error)
    }
  }

  if (isLoading) {
    return <div>Loading opportunities...</div>
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Opportunities</h1>
          <p className="text-gray-500">Manage your sales pipeline</p>
        </div>
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => setViewMode(viewMode === 'pipeline' ? 'list' : 'pipeline')}
          >
            <BarChart3 className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            onClick={() => setIsFiltersOpen(!isFiltersOpen)}
          >
            <Filter className="mr-2 h-4 w-4" />
            Filters
          </Button>
          <Button onClick={() => setIsCreateModalOpen(true)}>
            <Plus className="mr-2 h-4 w-4" />
            Add Opportunity
          </Button>
        </div>
      </div>

      {/* Stage Summary */}
      <div className="grid gap-4 md:grid-cols-5">
        {stageTotals.map((stage) => (
          <div
            key={stage.id}
            className="rounded-lg border bg-white p-4"
          >
            <div className="flex items-center justify-between">
              <h3 className="font-medium">{stage.name}</h3>
              <div className={cn('h-2 w-2 rounded-full', stage.color)} />
            </div>
            <p className="mt-2 text-2xl font-bold">{stage.count}</p>
            <p className="text-sm text-gray-500">
              ${stage.value.toLocaleString()}
            </p>
          </div>
        ))}
      </div>

      {isFiltersOpen && (
        <OpportunityFilters
          filters={filters}
          onFiltersChange={setFilters}
          onClose={() => setIsFiltersOpen(false)}
        />
      )}

      {/* Pipeline View */}
      {viewMode === 'pipeline' && (
        <DragDropContext onDragEnd={handleDragEnd}>
          <div className="flex gap-4 overflow-x-auto pb-4">
            {stages.map((stage) => (
              <div
                key={stage.id}
                className="min-w-[300px] flex-1"
              >
                <div className="mb-4 flex items-center justify-between">
                  <h3 className="font-medium">{stage.name}</h3>
                  <span className="text-sm text-gray-500">
                    {opportunitiesByStage[stage.id].length}
                  </span>
                </div>

                <Droppable droppableId={stage.id}>
                  {(provided, snapshot) => (
                    <div
                      ref={provided.innerRef}
                      {...provided.droppableProps}
                      className={cn(
                        'min-h-[200px] space-y-3 rounded-lg border-2 border-dashed p-3 transition-colors',
                        snapshot.isDraggingOver
                          ? 'border-primary bg-primary/5'
                          : 'border-gray-200'
                      )}
                    >
                      {opportunitiesByStage[stage.id].map((opportunity, index) => (
                        <Draggable
                          key={opportunity.id}
                          draggableId={opportunity.id}
                          index={index}
                        >
                          {(provided, snapshot) => (
                            <div
                              ref={provided.innerRef}
                              {...provided.draggableProps}
                              {...provided.dragHandleProps}
                              className={cn(
                                snapshot.isDragging && 'opacity-50'
                              )}
                            >
                              <OpportunityCard
                                opportunity={opportunity}
                                onUpdate={refetch}
                              />
                            </div>
                          )}
                        </Draggable>
                      ))}
                      {provided.placeholder}
                    </div>
                  )}
                </Droppable>
              </div>
            ))}
          </div>
        </DragDropContext>
      )}

      <CreateOpportunityModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSuccess={() => {
          refetch()
          setIsCreateModalOpen(false)
        }}
      />
    </div>
  )
}
```

#### 2. Opportunity Card Component
```typescript
// frontend/src/components/opportunities/OpportunityCard.tsx
import { Link } from 'react-router-dom'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Opportunity } from '@/types/api'
import { formatDate } from '@/lib/utils'
import { 
  MoreVertical, 
  Calendar, 
  DollarSign, 
  TrendingUp,
  AlertCircle,
  CheckCircle
} from 'lucide-react'

interface OpportunityCardProps {
  opportunity: Opportunity
  onUpdate: () => void
}

const productColors = {
  starter: 'bg-blue-100 text-blue-700',
  pro: 'bg-purple-100 text-purple-700',
  enterprise: 'bg-orange-100 text-orange-700',
}

export function OpportunityCard({ opportunity, onUpdate }: OpportunityCardProps) {
  const daysUntilClose = Math.ceil(
    (new Date(opportunity.closeDate).getTime() - new Date().getTime()) / 
    (1000 * 60 * 60 * 24)
  )

  const isOverdue = daysUntilClose < 0 && !['won', 'lost'].includes(opportunity.stage)

  return (
    <Card className="cursor-pointer hover:shadow-md transition-shadow">
      <CardContent className="p-4">
        <div className="flex items-start justify-between mb-3">
          <Link 
            to={`/opportunities/${opportunity.id}`}
            className="font-medium hover:underline line-clamp-1"
          >
            {opportunity.name}
          </Link>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-6 w-6">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>Edit</DropdownMenuItem>
              <DropdownMenuItem>Clone</DropdownMenuItem>
              <DropdownMenuItem className="text-red-600">Delete</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <div className="space-y-3">
          <div className="flex items-center gap-2">
            <Badge 
              variant="secondary"
              className={productColors[opportunity.product] || 'bg-gray-100'}
            >
              {opportunity.product}
            </Badge>
            {opportunity.nextBestAction && (
              <Badge variant="outline" className="text-xs">
                <TrendingUp className="mr-1 h-3 w-3" />
                {opportunity.nextBestAction}
              </Badge>
            )}
          </div>

          <div className="flex items-center justify-between text-sm">
            <div className="flex items-center gap-1">
              <DollarSign className="h-4 w-4 text-gray-400" />
              <span className="font-semibold">
                ${opportunity.amount.toLocaleString()}
              </span>
            </div>
            <div className="flex items-center gap-1">
              <Calendar className="h-4 w-4 text-gray-400" />
              <span className={isOverdue ? 'text-red-600 font-medium' : ''}>
                {isOverdue ? 'Overdue' : formatDate(opportunity.closeDate)}
              </span>
            </div>
          </div>

          <div className="space-y-1">
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-500">Probability</span>
              <span className="font-medium">{opportunity.probability}%</span>
            </div>
            <Progress value={opportunity.probability} className="h-2" />
          </div>

          {opportunity.winReasons && opportunity.winReasons.length > 0 && (
            <div className="flex items-center gap-1 text-xs text-green-600">
              <CheckCircle className="h-3 w-3" />
              {opportunity.winReasons[0]}
            </div>
          )}

          {isOverdue && (
            <div className="flex items-center gap-1 text-xs text-red-600">
              <AlertCircle className="h-3 w-3" />
              {Math.abs(daysUntilClose)} days overdue
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
```

### Day 5: Global Search Implementation

#### 1. Global Search Component
```typescript
// frontend/src/components/search/GlobalSearch.tsx
import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useDebounce } from '@/hooks/use-debounce'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { apiClient } from '@/lib/api-client'
import { Users, Target, TrendingUp, Search, Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'

interface GlobalSearchProps {
  isOpen: boolean
  onClose: () => void
}

const moduleConfig = {
  contacts: {
    icon: Users,
    color: 'text-blue-500',
    label: 'Contact',
    route: '/contacts',
  },
  leads: {
    icon: Target,
    color: 'text-green-500',
    label: 'Lead',
    route: '/leads',
  },
  opportunities: {
    icon: TrendingUp,
    color: 'text-purple-500',
    label: 'Opportunity',
    route: '/opportunities',
  },
}

export function GlobalSearch({ isOpen, onClose }: GlobalSearchProps) {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [results, setResults] = useState<any[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const debouncedSearch = useDebounce(search, 300)

  useEffect(() => {
    if (debouncedSearch.length < 2) {
      setResults([])
      return
    }

    const searchAll = async () => {
      setIsLoading(true)
      try {
        // Search across all modules
        const [contacts, leads, opportunities] = await Promise.all([
          apiClient.getContacts({ 
            limit: 5, 
            filters: { 
              _or: [
                { first_name: { like: debouncedSearch } },
                { last_name: { like: debouncedSearch } },
                { email: { like: debouncedSearch } },
              ]
            } 
          }),
          apiClient.getLeads({ 
            limit: 5, 
            filters: { 
              _or: [
                { first_name: { like: debouncedSearch } },
                { last_name: { like: debouncedSearch } },
                { email: { like: debouncedSearch } },
              ]
            } 
          }),
          apiClient.getOpportunities({ 
            limit: 5, 
            filters: { 
              name: { like: debouncedSearch } 
            } 
          }),
        ])

        const allResults = [
          ...contacts.data.map((item: any) => ({ ...item, module: 'contacts' })),
          ...leads.data.map((item: any) => ({ ...item, module: 'leads' })),
          ...opportunities.data.map((item: any) => ({ ...item, module: 'opportunities' })),
        ]

        setResults(allResults)
      } catch (error) {
        console.error('Search failed:', error)
      } finally {
        setIsLoading(false)
      }
    }

    searchAll()
  }, [debouncedSearch])

  const handleSelect = (result: any) => {
    const config = moduleConfig[result.module as keyof typeof moduleConfig]
    navigate(`${config.route}/${result.id}`)
    onClose()
    setSearch('')
  }

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="p-0 gap-0 max-w-2xl">
        <Command className="rounded-lg border-0">
          <div className="flex items-center border-b px-3">
            <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
            <input
              className="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
              placeholder="Search contacts, leads, opportunities..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            {isLoading && <Loader2 className="h-4 w-4 animate-spin" />}
          </div>
          <CommandList>
            {search.length < 2 ? (
              <div className="py-6 text-center text-sm text-muted-foreground">
                Type at least 2 characters to search
              </div>
            ) : results.length === 0 && !isLoading ? (
              <CommandEmpty>No results found.</CommandEmpty>
            ) : (
              <>
                {Object.entries(
                  results.reduce((acc, result) => {
                    if (!acc[result.module]) acc[result.module] = []
                    acc[result.module].push(result)
                    return acc
                  }, {} as Record<string, any[]>)
                ).map(([module, moduleResults]) => {
                  const config = moduleConfig[module as keyof typeof moduleConfig]
                  const Icon = config.icon

                  return (
                    <CommandGroup key={module} heading={config.label + 's'}>
                      {moduleResults.map((result) => (
                        <CommandItem
                          key={result.id}
                          value={result.id}
                          onSelect={() => handleSelect(result)}
                          className="cursor-pointer"
                        >
                          <Icon className={cn('mr-2 h-4 w-4', config.color)} />
                          <div className="flex-1">
                            <div className="font-medium">
                              {result.name || `${result.firstName} ${result.lastName}`}
                            </div>
                            {result.email && (
                              <div className="text-sm text-muted-foreground">
                                {result.email}
                              </div>
                            )}
                          </div>
                          <Badge variant="secondary" className="ml-2">
                            {config.label}
                          </Badge>
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  )
                })}
              </>
            )}
          </CommandList>
        </Command>
      </DialogContent>
    </Dialog>
  )
}
```

#### 2. Update Header with Global Search
```typescript
// frontend/src/components/layout/Header.tsx (updated)
import { useState } from 'react'
import { Bell, Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth-store'
import { GlobalSearch } from '@/components/search/GlobalSearch'

export function Header() {
  const user = useAuthStore((state) => state.user)
  const [isSearchOpen, setIsSearchOpen] = useState(false)

  return (
    <>
      <header className="flex h-16 items-center justify-between border-b bg-white px-6">
        <div className="flex items-center flex-1">
          <div 
            className="relative w-96 cursor-pointer"
            onClick={() => setIsSearchOpen(true)}
          >
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <Input
              type="search"
              placeholder="Search contacts, leads, opportunities..."
              className="pl-10"
              readOnly
            />
          </div>
        </div>

        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon">
            <Bell className="h-5 w-5" />
          </Button>
          
          <div className="flex items-center gap-3">
            <div className="text-right">
              <p className="text-sm font-medium">
                {user?.firstName} {user?.lastName}
              </p>
              <p className="text-xs text-gray-500">{user?.email}</p>
            </div>
            <div className="h-8 w-8 rounded-full bg-gray-300" />
          </div>
        </div>
      </header>

      <GlobalSearch 
        isOpen={isSearchOpen} 
        onClose={() => setIsSearchOpen(false)} 
      />
    </>
  )
}
```

## Week 8: Quotes, Cases & Polish

### Day 1-2: Quotes Module

#### 1. Quotes Page
```typescript
// frontend/src/pages/Quotes.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search, Filter, Send, Copy } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { QuotesTable } from '@/components/quotes/QuotesTable'
import { CreateQuoteModal } from '@/components/quotes/CreateQuoteModal'
import { apiClient } from '@/lib/api-client'

export function QuotesPage() {
  const [page, setPage] = useState(1)
  const [searchTerm, setSearchTerm] = useState('')
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['quotes', page, searchTerm],
    queryFn: () => apiClient.getQuotes({ 
      page, 
      limit: 20,
      filters: searchTerm ? { name: { like: searchTerm } } : undefined
    }),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Quotes</h1>
          <p className="text-gray-500">Create and manage pricing proposals</p>
        </div>
        <Button onClick={() => setIsCreateModalOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          Create Quote
        </Button>
      </div>

      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search quotes..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Button variant="outline">
          <Filter className="mr-2 h-4 w-4" />
          Filters
        </Button>
      </div>

      <QuotesTable
        quotes={data?.data || []}
        pagination={data?.pagination}
        isLoading={isLoading}
        onPageChange={setPage}
        onQuoteAction={(action, quote) => {
          if (action === 'send') {
            // Handle send quote
            console.log('Sending quote:', quote)
          } else if (action === 'duplicate') {
            // Handle duplicate
            console.log('Duplicating quote:', quote)
          }
          refetch()
        }}
      />

      <CreateQuoteModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSuccess={() => {
          refetch()
          setIsCreateModalOpen(false)
        }}
      />
    </div>
  )
}
```

#### 2. Create Quote Modal
```typescript
// frontend/src/components/quotes/CreateQuoteModal.tsx
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { apiClient } from '@/lib/api-client'
import { Loader2, Plus, Trash2 } from 'lucide-react'
import { ContactSearch } from '@/components/shared/ContactSearch'

const quoteSchema = z.object({
  name: z.string().min(1, 'Quote name is required'),
  contact_id: z.string().min(1, 'Contact is required'),
  valid_until: z.string().min(1, 'Valid until date is required'),
  stage: z.string(),
  description: z.string().optional(),
})

type QuoteForm = z.infer<typeof quoteSchema>

interface LineItem {
  id: string
  product: string
  description: string
  quantity: number
  price: number
  total: number
}

export function CreateQuoteModal({ isOpen, onClose, onSuccess }: any) {
  const [isLoading, setIsLoading] = useState(false)
  const [lineItems, setLineItems] = useState<LineItem[]>([
    {
      id: '1',
      product: '',
      description: '',
      quantity: 1,
      price: 0,
      total: 0,
    },
  ])

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<QuoteForm>({
    resolver: zodResolver(quoteSchema),
    defaultValues: {
      stage: 'Draft',
    },
  })

  const addLineItem = () => {
    setLineItems([
      ...lineItems,
      {
        id: Date.now().toString(),
        product: '',
        description: '',
        quantity: 1,
        price: 0,
        total: 0,
      },
    ])
  }

  const removeLineItem = (id: string) => {
    setLineItems(lineItems.filter(item => item.id !== id))
  }

  const updateLineItem = (id: string, field: keyof LineItem, value: any) => {
    setLineItems(lineItems.map(item => {
      if (item.id === id) {
        const updated = { ...item, [field]: value }
        if (field === 'quantity' || field === 'price') {
          updated.total = updated.quantity * updated.price
        }
        return updated
      }
      return item
    }))
  }

  const calculateTotal = () => {
    return lineItems.reduce((sum, item) => sum + item.total, 0)
  }

  const onSubmit = async (data: QuoteForm) => {
    setIsLoading(true)
    try {
      await apiClient.createQuote({
        ...data,
        line_items: lineItems,
        total: calculateTotal(),
      })
      onSuccess()
    } catch (error) {
      console.error('Failed to create quote:', error)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create Quote</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label>Quote Name</Label>
              <Input {...register('name')} placeholder="Q-2024-001" />
              {errors.name && (
                <p className="text-sm text-red-500">{errors.name.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Contact</Label>
              <ContactSearch
                onSelect={(contact) => setValue('contact_id', contact.id)}
              />
              {errors.contact_id && (
                <p className="text-sm text-red-500">{errors.contact_id.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Valid Until</Label>
              <Input {...register('valid_until')} type="date" />
              {errors.valid_until && (
                <p className="text-sm text-red-500">{errors.valid_until.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Stage</Label>
              <Select
                defaultValue="Draft"
                onValueChange={(value) => setValue('stage', value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Draft">Draft</SelectItem>
                  <SelectItem value="Delivered">Delivered</SelectItem>
                  <SelectItem value="On Hold">On Hold</SelectItem>
                  <SelectItem value="Confirmed">Confirmed</SelectItem>
                  <SelectItem value="Closed Accepted">Closed Accepted</SelectItem>
                  <SelectItem value="Closed Lost">Closed Lost</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label>Description</Label>
            <Textarea 
              {...register('description')} 
              placeholder="Additional notes..."
              rows={3}
            />
          </div>

          {/* Line Items */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <Label>Line Items</Label>
              <Button type="button" size="sm" onClick={addLineItem}>
                <Plus className="mr-2 h-4 w-4" />
                Add Item
              </Button>
            </div>

            <div className="rounded-lg border">
              <table className="w-full">
                <thead className="border-b bg-gray-50">
                  <tr>
                    <th className="p-3 text-left text-sm font-medium">Product</th>
                    <th className="p-3 text-left text-sm font-medium">Description</th>
                    <th className="p-3 text-right text-sm font-medium">Qty</th>
                    <th className="p-3 text-right text-sm font-medium">Price</th>
                    <th className="p-3 text-right text-sm font-medium">Total</th>
                    <th className="p-3 w-10"></th>
                  </tr>
                </thead>
                <tbody>
                  {lineItems.map((item) => (
                    <tr key={item.id} className="border-b">
                      <td className="p-3">
                        <Input
                          value={item.product}
                          onChange={(e) => updateLineItem(item.id, 'product', e.target.value)}
                          placeholder="Product name"
                        />
                      </td>
                      <td className="p-3">
                        <Input
                          value={item.description}
                          onChange={(e) => updateLineItem(item.id, 'description', e.target.value)}
                          placeholder="Description"
                        />
                      </td>
                      <td className="p-3">
                        <Input
                          type="number"
                          value={item.quantity}
                          onChange={(e) => updateLineItem(item.id, 'quantity', parseInt(e.target.value) || 0)}
                          className="text-right w-20"
                        />
                      </td>
                      <td className="p-3">
                        <Input
                          type="number"
                          value={item.price}
                          onChange={(e) => updateLineItem(item.id, 'price', parseFloat(e.target.value) || 0)}
                          className="text-right w-24"
                          step="0.01"
                        />
                      </td>
                      <td className="p-3 text-right font-medium">
                        ${item.total.toFixed(2)}
                      </td>
                      <td className="p-3">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => removeLineItem(item.id)}
                          disabled={lineItems.length === 1}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <td colSpan={4} className="p-3 text-right font-medium">
                      Total:
                    </td>
                    <td className="p-3 text-right font-bold text-lg">
                      ${calculateTotal().toFixed(2)}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div className="flex justify-end gap-3">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={isLoading}>
              {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Create Quote
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  )
}
```

### Day 3: Cases (Support Tickets) Module

#### 1. Cases Page
```typescript
// frontend/src/pages/Cases.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search, Filter, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { CasesTable } from '@/components/cases/CasesTable'
import { CreateCaseModal } from '@/components/cases/CreateCaseModal'
import { CaseStats } from '@/components/cases/CaseStats'
import { apiClient } from '@/lib/api-client'

export function CasesPage() {
  const [page, setPage] = useState(1)
  const [searchTerm, setSearchTerm] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['cases', page, searchTerm, statusFilter],
    queryFn: () => apiClient.getCases({ 
      page, 
      limit: 20,
      filters: {
        ...(searchTerm ? { name: { like: searchTerm } } : {}),
        ...(statusFilter !== 'all' ? { status: statusFilter } : {})
      }
    }),
  })

  const { data: stats } = useQuery({
    queryKey: ['case-stats'],
    queryFn: () => apiClient.getCaseStats(),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Support Cases</h1>
          <p className="text-gray-500">Manage customer support tickets</p>
        </div>
        <Button onClick={() => setIsCreateModalOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          Create Case
        </Button>
      </div>

      <CaseStats stats={stats?.data} />

      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search cases..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Button variant="outline">
          <Filter className="mr-2 h-4 w-4" />
          Filters
        </Button>
      </div>

      <Tabs value={statusFilter} onValueChange={setStatusFilter}>
        <TabsList>
          <TabsTrigger value="all">All Cases</TabsTrigger>
          <TabsTrigger value="New">New</TabsTrigger>
          <TabsTrigger value="Assigned">Assigned</TabsTrigger>
          <TabsTrigger value="Pending Input">Pending</TabsTrigger>
          <TabsTrigger value="Closed">Closed</TabsTrigger>
        </TabsList>

        <TabsContent value={statusFilter} className="mt-6">
          <CasesTable
            cases={data?.data || []}
            pagination={data?.pagination}
            isLoading={isLoading}
            onPageChange={setPage}
            onCaseUpdate={() => refetch()}
          />
        </TabsContent>
      </Tabs>

      <CreateCaseModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSuccess={() => {
          refetch()
          setIsCreateModalOpen(false)
        }}
      />
    </div>
  )
}
```

#### 2. Case Stats Component
```typescript
// frontend/src/components/cases/CaseStats.tsx
import { Card, CardContent } from '@/components/ui/card'
import { AlertCircle, Clock, CheckCircle, Users } from 'lucide-react'

interface CaseStatsProps {
  stats?: {
    new: number
    inProgress: number
    resolved: number
    avgResponseTime: string
  }
}

export function CaseStats({ stats }: CaseStatsProps) {
  if (!stats) return null

  const items = [
    {
      label: 'New Cases',
      value: stats.new,
      icon: AlertCircle,
      color: 'text-red-500',
      bg: 'bg-red-100',
    },
    {
      label: 'In Progress',
      value: stats.inProgress,
      icon: Clock,
      color: 'text-yellow-500',
      bg: 'bg-yellow-100',
    },
    {
      label: 'Resolved',
      value: stats.resolved,
      icon: CheckCircle,
      color: 'text-green-500',
      bg: 'bg-green-100',
    },
    {
      label: 'Avg Response',
      value: stats.avgResponseTime,
      icon: Users,
      color: 'text-blue-500',
      bg: 'bg-blue-100',
    },
  ]

  return (
    <div className="grid gap-4 md:grid-cols-4">
      {items.map((item) => (
        <Card key={item.label}>
          <CardContent className="flex items-center justify-between p-6">
            <div>
              <p className="text-sm text-gray-500">{item.label}</p>
              <p className="text-2xl font-bold">{item.value}</p>
            </div>
            <div className={`rounded-full p-3 ${item.bg}`}>
              <item.icon className={`h-6 w-6 ${item.color}`} />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
```

### Day 4: Settings Page

#### 1. Settings Page
```typescript
// frontend/src/pages/Settings.tsx
import { useState } from 'react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ProfileSettings } from '@/components/settings/ProfileSettings'
import { NotificationSettings } from '@/components/settings/NotificationSettings'
import { ApiSettings } from '@/components/settings/ApiSettings'
import { User, Bell, Key, Palette } from 'lucide-react'

export function SettingsPage() {
  const [activeTab, setActiveTab] = useState('profile')

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Settings</h1>
        <p className="text-gray-500">Manage your account and preferences</p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="grid w-full max-w-md grid-cols-4">
          <TabsTrigger value="profile" className="flex items-center gap-2">
            <User className="h-4 w-4" />
            <span className="hidden sm:inline">Profile</span>
          </TabsTrigger>
          <TabsTrigger value="notifications" className="flex items-center gap-2">
            <Bell className="h-4 w-4" />
            <span className="hidden sm:inline">Notifications</span>
          </TabsTrigger>
          <TabsTrigger value="api" className="flex items-center gap-2">
            <Key className="h-4 w-4" />
            <span className="hidden sm:inline">API</span>
          </TabsTrigger>
          <TabsTrigger value="appearance" className="flex items-center gap-2">
            <Palette className="h-4 w-4" />
            <span className="hidden sm:inline">Theme</span>
          </TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="mt-6">
          <ProfileSettings />
        </TabsContent>

        <TabsContent value="notifications" className="mt-6">
          <NotificationSettings />
        </TabsContent>

        <TabsContent value="api" className="mt-6">
          <ApiSettings />
        </TabsContent>

        <TabsContent value="appearance" className="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Appearance</CardTitle>
              <CardDescription>
                Customize how the application looks
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <div>
                  <h3 className="text-sm font-medium mb-3">Theme</h3>
                  <div className="grid grid-cols-3 gap-3">
                    <button className="rounded-lg border-2 border-primary p-4 text-center">
                      <div className="text-sm font-medium">Light</div>
                    </button>
                    <button className="rounded-lg border p-4 text-center">
                      <div className="text-sm font-medium">Dark</div>
                    </button>
                    <button className="rounded-lg border p-4 text-center">
                      <div className="text-sm font-medium">System</div>
                    </button>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
```

#### 2. Profile Settings Component
```typescript
// frontend/src/components/settings/ProfileSettings.tsx
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useAuthStore } from '@/stores/auth-store'
import { Loader2 } from 'lucide-react'

export function ProfileSettings() {
  const user = useAuthStore((state) => state.user)
  const [isLoading, setIsLoading] = useState(false)

  const { register, handleSubmit } = useForm({
    defaultValues: {
      firstName: user?.firstName || '',
      lastName: user?.lastName || '',
      email: user?.email || '',
      phone: '',
      bio: '',
    },
  })

  const onSubmit = async (data: any) => {
    setIsLoading(true)
    try {
      // Update profile
      console.log('Updating profile:', data)
    } catch (error) {
      console.error('Failed to update profile:', error)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Profile Information</CardTitle>
        <CardDescription>
          Update your personal information and contact details
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label>First Name</Label>
              <Input {...register('firstName')} />
            </div>
            <div className="space-y-2">
              <Label>Last Name</Label>
              <Input {...register('lastName')} />
            </div>
          </div>

          <div className="space-y-2">
            <Label>Email</Label>
            <Input {...register('email')} type="email" />
          </div>

          <div className="space-y-2">
            <Label>Phone</Label>
            <Input {...register('phone')} type="tel" />
          </div>

          <div className="space-y-2">
            <Label>Bio</Label>
            <Textarea {...register('bio')} rows={4} />
          </div>

          <div className="flex justify-end">
            <Button type="submit" disabled={isLoading}>
              {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Save Changes
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
```

### Day 5: Final Polish & Testing

#### 1. Error Boundary
```typescript
// frontend/src/components/ErrorBoundary.tsx
import { Component, ErrorInfo, ReactNode } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { AlertTriangle } from 'lucide-react'

interface Props {
  children: ReactNode
}

interface State {
  hasError: boolean
  error: Error | null
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error: null,
  }

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error }
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Uncaught error:', error, errorInfo)
  }

  private handleReset = () => {
    this.setState({ hasError: false, error: null })
  }

  public render() {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-screen items-center justify-center p-4">
          <Card className="w-full max-w-md">
            <CardHeader>
              <div className="flex items-center gap-2">
                <AlertTriangle className="h-5 w-5 text-red-500" />
                <CardTitle>Something went wrong</CardTitle>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-gray-600">
                An unexpected error occurred. Please try refreshing the page.
              </p>
              {this.state.error && (
                <div className="rounded-lg bg-gray-50 p-3">
                  <p className="text-xs font-mono text-gray-700">
                    {this.state.error.message}
                  </p>
                </div>
              )}
              <div className="flex gap-3">
                <Button onClick={() => window.location.reload()}>
                  Refresh Page
                </Button>
                <Button variant="outline" onClick={this.handleReset}>
                  Try Again
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )
    }

    return this.props.children
  }
}
```

#### 2. Loading States
```typescript
// frontend/src/components/ui/skeleton.tsx
import { cn } from '@/lib/utils'

function Skeleton({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('animate-pulse rounded-md bg-muted', className)}
      {...props}
    />
  )
}

export { Skeleton }

// frontend/src/components/LoadingStates.tsx
import { Skeleton } from '@/components/ui/skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/card'

export function TableSkeleton() {
  return (
    <div className="space-y-4">
      <div className="rounded-lg border">
        <div className="p-4">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="flex items-center space-x-4 py-3">
              <Skeleton className="h-12 w-12 rounded-full" />
              <div className="space-y-2">
                <Skeleton className="h-4 w-[200px]" />
                <Skeleton className="h-4 w-[150px]" />
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

export function DashboardSkeleton() {
  return (
    <div className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {[...Array(4)].map((_, i) => (
          <Card key={i}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <Skeleton className="h-4 w-[100px]" />
              <Skeleton className="h-4 w-4" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-7 w-[120px]" />
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
```

#### 3. Custom Hooks
```typescript
// frontend/src/hooks/use-debounce.ts
import { useEffect, useState } from 'react'

export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value)

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value)
    }, delay)

    return () => {
      clearTimeout(handler)
    }
  }, [value, delay])

  return debouncedValue
}

// frontend/src/hooks/use-local-storage.ts
import { useState, useEffect } from 'react'

export function useLocalStorage<T>(key: string, initialValue: T) {
  const [storedValue, setStoredValue] = useState<T>(() => {
    try {
      const item = window.localStorage.getItem(key)
      return item ? JSON.parse(item) : initialValue
    } catch (error) {
      console.error(`Error loading ${key} from localStorage:`, error)
      return initialValue
    }
  })

  const setValue = (value: T | ((val: T) => T)) => {
    try {
      const valueToStore = value instanceof Function ? value(storedValue) : value
      setStoredValue(valueToStore)
      window.localStorage.setItem(key, JSON.stringify(valueToStore))
    } catch (error) {
      console.error(`Error saving ${key} to localStorage:`, error)
    }
  }

  return [storedValue, setValue] as const
}
```

#### 4. Final App Setup
```typescript
// frontend/src/App.tsx (updated with ErrorBoundary)
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { queryClient } from '@/lib/query-client'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { Layout } from '@/components/layout/Layout'
import { LoginPage } from '@/pages/Login'
import { DashboardPage } from '@/pages/Dashboard'
import { ContactsPage } from '@/pages/Contacts'
import { ContactDetailPage } from '@/pages/ContactDetail'
import { LeadsPage } from '@/pages/Leads'
import { OpportunitiesPage } from '@/pages/Opportunities'
import { ActivitiesPage } from '@/pages/Activities'
import { QuotesPage } from '@/pages/Quotes'
import { CasesPage } from '@/pages/Cases'
import { SettingsPage } from '@/pages/Settings'

export function App() {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <Router>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            
            <Route element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }>
              <Route path="/" element={<DashboardPage />} />
              <Route path="/contacts" element={<ContactsPage />} />
              <Route path="/contacts/:id" element={<ContactDetailPage />} />
              <Route path="/leads" element={<LeadsPage />} />
              <Route path="/opportunities" element={<OpportunitiesPage />} />
              <Route path="/activities" element={<ActivitiesPage />} />
              <Route path="/quotes" element={<QuotesPage />} />
              <Route path="/cases" element={<CasesPage />} />
              <Route path="/settings" element={<SettingsPage />} />
            </Route>
            
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Router>
        <ReactQueryDevtools initialIsOpen={false} />
      </QueryClientProvider>
    </ErrorBoundary>
  )
}
```

## Deliverables Checklist

### Week 7 Deliverables
- [ ] Leads module complete
  - [ ] List view with filters
  - [ ] Lead scoring display
  - [ ] Convert lead modal
  - [ ] Bulk actions
- [ ] Opportunities module complete
  - [ ] Pipeline kanban view
  - [ ] Drag-and-drop stage changes
  - [ ] Opportunity cards with AI insights
  - [ ] Stage summary metrics
- [ ] Global search implementation
- [ ] Advanced filtering for all modules

### Week 8 Deliverables
- [ ] Quotes module complete
  - [ ] Quote creation with line items
  - [ ] Quote templates
  - [ ] Send quote functionality
- [ ] Cases (Support) module complete
  - [ ] Case management
  - [ ] Status tracking
  - [ ] Support metrics
- [ ] Settings page complete
  - [ ] Profile management
  - [ ] Notification preferences
  - [ ] API key management
- [ ] Error handling and loading states
- [ ] Final testing and polish

## Testing Checklist

### Unit Tests
```bash
# Run unit tests
npm test

# Run with coverage
npm run test:coverage
```

### Manual Testing
1. **Authentication Flow**
   - [ ] Login/logout works
   - [ ] Token refresh works
   - [ ] Protected routes redirect properly

2. **CRUD Operations**
   - [ ] Create records in all modules
   - [ ] Update records
   - [ ] Delete records
   - [ ] Search and filter

3. **UI/UX**
   - [ ] Responsive design on mobile
   - [ ] Loading states appear
   - [ ] Error states handled gracefully
   - [ ] Keyboard navigation works

4. **Performance**
   - [ ] Page load < 2s
   - [ ] Smooth scrolling in lists
   - [ ] No memory leaks

## Next Steps

After completing Phase 3, you'll have:
1. Complete CRM functionality for all modules
2. Advanced features like search, filtering, and bulk actions
3. Beautiful UI with drag-and-drop pipeline
4. Settings and user preferences
5. Production-ready error handling

The application is now ready for Phase 4: AI Integration to add intelligent features throughout the CRM.