import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"
import type { CasePriority, CasePriorityLabel } from "@/types/api.types"

interface PriorityBadgeProps {
  priority: CasePriority | CasePriorityLabel | 'High' | 'Medium' | 'Low'
  className?: string
}

export function PriorityBadge({ priority, className }: PriorityBadgeProps) {
  const getColorClass = () => {
    switch (priority) {
      case 'P1':
      case 'High':
        return 'bg-red-100 text-red-800 border-red-200'
      case 'P2':
      case 'Medium':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200'
      case 'P3':
      case 'Low':
        return 'bg-green-100 text-green-800 border-green-200'
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200'
    }
  }

  return (
    <Badge variant="outline" className={cn(getColorClass(), className)}>
      {priority}
    </Badge>
  )
}