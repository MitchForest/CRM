import { useState } from 'react'
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core'
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core'
import {
  SortableContext,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { Card } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
import { KanbanColumn } from './KanbanColumn'
import { OpportunityCard } from './OpportunityCard'
import { useUpdateOpportunityStage } from '@/hooks/use-opportunities'
import type { Opportunity } from '@/types/api.generated'
import type { OpportunityStage } from '@/types/phase2.types'

const stages: OpportunityStage[] = [
  'Qualification',
  'Needs Analysis',
  'Value Proposition',
  'Decision Makers',
  'Proposal',
  'Negotiation',
  'Closed Won',
  'Closed Lost',
]

interface OpportunitiesKanbanProps {
  opportunities: Opportunity[]
}

export function OpportunitiesKanban({ opportunities }: OpportunitiesKanbanProps) {
  const [activeId, setActiveId] = useState<string | null>(null)
  const updateStageMutation = useUpdateOpportunityStage()

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  )

  const handleDragStart = (event: DragStartEvent) => {
    setActiveId(event.active.id as string)
  }

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event

    if (!over || active.id === over.id) {
      setActiveId(null)
      return
    }

    const opportunity = opportunities.find(opp => opp.id === active.id)
    const newStage = over.id as string

    if (opportunity && opportunity.salesStage !== newStage) {
      updateStageMutation.mutate({
        id: opportunity.id!,
        stage: newStage,
      })
    }

    setActiveId(null)
  }

  const opportunitiesByStage = stages.reduce((acc, stage) => {
    acc[stage] = opportunities.filter(opp => opp.salesStage === stage)
    return acc
  }, {} as Record<OpportunityStage, Opportunity[]>)

  const activeOpportunity = activeId
    ? opportunities.find(opp => opp.id === activeId)
    : null

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  }

  return (
    <DndContext
      sensors={sensors}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <ScrollArea className="h-[calc(100vh-12rem)] w-full">
        <div className="flex gap-4 p-4">
          {stages.map((stage) => {
            const stageOpportunities = opportunitiesByStage[stage] || []
            const totalValue = stageOpportunities.reduce(
              (sum, opp) => sum + (opp.amount || 0),
              0
            )

            return (
              <KanbanColumn
                key={stage}
                id={stage}
                title={stage}
                count={stageOpportunities.length}
                value={formatCurrency(totalValue)}
              >
                <SortableContext
                  items={stageOpportunities.map(opp => opp.id || '')}
                  strategy={verticalListSortingStrategy}
                >
                  {stageOpportunities.map((opportunity) => (
                    <OpportunityCard
                      key={opportunity.id}
                      opportunity={opportunity}
                    />
                  ))}
                </SortableContext>
              </KanbanColumn>
            )
          })}
        </div>
      </ScrollArea>

      <DragOverlay>
        {activeOpportunity && (
          <Card className="cursor-grabbing opacity-50">
            <OpportunityCard opportunity={activeOpportunity} />
          </Card>
        )}
      </DragOverlay>
    </DndContext>
  )
}