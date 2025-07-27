import { useState } from 'react'
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core'
import type { DragEndEvent, DragStartEvent, DragOverEvent } from '@dnd-kit/core'
import {
  SortableContext,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { ScrollArea } from '@/components/ui/scroll-area'
import { KanbanColumn } from './KanbanColumn'
import { OpportunityCard } from './OpportunityCard'
import { useUpdateOpportunityStage } from '@/hooks/use-opportunities'
import type { Opportunity } from '@/types/api.generated'

const stages = [
  'qualification',
  'proposal',
  'negotiation',
  'closed_won',
  'closed_lost',
] as const

type OpportunityStage = typeof stages[number]

interface OpportunitiesKanbanProps {
  opportunities: OpportunityDB[]
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

  const handleDragOver = (_event: DragOverEvent) => {
    // Drag over handling if needed
  }

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    
    setActiveId(null)

    if (!over) return

    const opportunity = opportunities.find(opp => opp.id === active.id)
    if (!opportunity) return

    // Check if we're dropping on a column or another card
    let targetStage: string | null = null
    
    // If dropping on a column directly
    if (stages.includes(over.id as OpportunityStage)) {
      targetStage = over.id as OpportunityStage
    } else {
      // If dropping on another card, find which stage that card is in
      const targetOpp = opportunities.find(opp => opp.id === over.id)
      if (targetOpp) {
        targetStage = targetOpp.sales_stage || 'qualification'
      }
    }

    if (targetStage && opportunity.sales_stage !== targetStage) {
      updateStageMutation.mutate({
        id: opportunity.id!,
        stage: targetStage as OpportunityStage,
      })
    }
  }

  const opportunitiesByStage = stages.reduce((acc, stage) => {
    acc[stage] = opportunities.filter(opp => opp.sales_stage === stage)
    return acc
  }, {} as Record<OpportunityStage, OpportunityDB[]>)

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
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
    >
      <ScrollArea className="h-[calc(100vh-12rem)] w-full">
        <div className="flex gap-4 p-4">
          {stages.map((stage) => {
            const stageOpportunities = opportunitiesByStage[stage] || []
            const totalValue = stageOpportunities.reduce(
              (sum, opp) => {
                const amount = typeof opp.amount === 'string' ? parseFloat(opp.amount) : (opp.amount || 0)
                return sum + (isNaN(amount) || !isFinite(amount) ? 0 : amount)
              },
              0
            )

            return (
              <KanbanColumn
                key={stage}
                id={stage}
                title={stage}
                count={stageOpportunities.length}
                value={formatCurrency(totalValue)}
                isEmpty={stageOpportunities.length === 0}
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
          <div className="cursor-grabbing opacity-80">
            <OpportunityCard opportunity={activeOpportunity} />
          </div>
        )}
      </DragOverlay>
    </DndContext>
  )
}