import { useDroppable } from '@dnd-kit/core'
import { cn } from '@/lib/utils'

interface KanbanColumnProps {
  id: string
  title: string
  count: number
  value: string
  children: React.ReactNode
}

export function KanbanColumn({ id, title, count, value, children }: KanbanColumnProps) {
  const { isOver, setNodeRef } = useDroppable({
    id,
  })

  const isClosedStage = id === 'Closed Won' || id === 'Closed Lost'
  const isWonStage = id === 'Closed Won'

  return (
    <div
      ref={setNodeRef}
      className={cn(
        "flex min-w-[300px] flex-col rounded-lg border bg-gray-50 p-4",
        isOver && "border-primary bg-primary/5",
        isClosedStage && "bg-gray-100"
      )}
    >
      <div className="mb-4">
        <div className="flex items-center justify-between">
          <h3 className={cn(
            "font-semibold",
            isWonStage && "text-green-700",
            id === 'Closed Lost' && "text-red-700"
          )}>
            {title}
          </h3>
          <span className="text-sm text-muted-foreground">{count}</span>
        </div>
        <p className="text-sm font-medium text-muted-foreground">{value}</p>
      </div>
      <div className="flex-1 space-y-2">
        {children}
      </div>
    </div>
  )
}