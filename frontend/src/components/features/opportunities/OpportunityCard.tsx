import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Link } from 'react-router-dom'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Building2, Calendar, DollarSign } from 'lucide-react'
import type { Opportunity } from '@/types/api.generated'
import { cn } from '@/lib/utils'

interface OpportunityCardProps {
  opportunity: Opportunity
}

export function OpportunityCard({ opportunity }: OpportunityCardProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: opportunity.id || '' })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  const probabilityColor = 
    (opportunity.probability || 0) >= 70 ? 'text-green-600' :
    (opportunity.probability || 0) >= 40 ? 'text-yellow-600' :
    'text-red-600'

  const formatCurrency = (amount: number | string | undefined) => {
    if (!amount) return '$0'
    
    // Convert to number and handle potential string values
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount
    
    if (isNaN(numAmount) || !isFinite(numAmount)) {
      return '$0'
    }
    
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: opportunity.currency || 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(numAmount)
  }

  const formatDate = (dateString: string) => {
    if (!dateString) return 'No date'
    const date = new Date(dateString)
    if (isNaN(date.getTime())) return 'Invalid date'
    return new Intl.DateTimeFormat('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }).format(date)
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      className={cn(
        "transform-gpu",
        isDragging && "opacity-50"
      )}
    >
      <Card className={cn(
        "cursor-grab hover:shadow-md active:cursor-grabbing transition-all duration-200",
        "hover:scale-[1.02] hover:-translate-y-1",
        isDragging && "ring-2 ring-primary"
      )}>
        <CardContent className="p-4">
          <Link
            to={`/opportunities/${opportunity.id}`}
            className="block space-y-2"
            onClick={(e) => e.stopPropagation()}
          >
            <h4 className="font-medium line-clamp-1">{opportunity.name}</h4>
            
            {opportunity.contactName && (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Building2 className="h-3 w-3" />
                <span className="line-clamp-1">{opportunity.contactName}</span>
              </div>
            )}

            <div className="flex items-center justify-between">
              <div className="flex items-center gap-1">
                <DollarSign className="h-3 w-3" />
                <span className="font-semibold">
                  {formatCurrency(opportunity.amount || 0)}
                </span>
              </div>
              <Badge variant="secondary" className={cn(probabilityColor)}>
                {opportunity.probability}%
              </Badge>
            </div>

            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <Calendar className="h-3 w-3" />
              <span>Close: {formatDate(opportunity.closeDate)}</span>
            </div>

            {opportunity.nextStep && (
              <p className="text-xs text-muted-foreground line-clamp-2">
                Next: {opportunity.nextStep}
              </p>
            )}
          </Link>
        </CardContent>
      </Card>
    </div>
  )
}