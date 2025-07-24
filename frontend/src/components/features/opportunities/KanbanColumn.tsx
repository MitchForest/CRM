import { useDroppable } from '@dnd-kit/core'
import { cn } from '@/lib/utils'

interface KanbanColumnProps {
  id: string
  title: string
  count: number
  value: string
  children: React.ReactNode
  isEmpty?: boolean
}

export function KanbanColumn({ id, title, count, value, children, isEmpty }: KanbanColumnProps) {
  const { isOver, setNodeRef } = useDroppable({
    id,
  })

  const isClosedStage = id === 'Closed Won' || id === 'Closed Lost'
  const isWonStage = id === 'Closed Won'

  return (
    <div
      ref={setNodeRef}
      className={cn(
        "flex min-w-[300px] flex-col rounded-lg border bg-gray-50 p-4 transition-all duration-200 relative",
        isOver && "border-primary border-2 bg-primary/5 shadow-lg",
        isClosedStage && !isOver && "bg-gray-100",
        !isOver && "border-gray-200"
      )}
    >
      {/* Overlay effect when dragging over */}
      {isOver && (
        <div className="absolute inset-0 bg-primary/5 rounded-lg pointer-events-none animate-pulse" />
      )}
      <div className="mb-4 relative z-10">
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
      <div className={cn(
        "flex-1 space-y-2 min-h-[100px] rounded-md transition-colors duration-200 relative z-10"
      )}>
        {children}
        {/* Show empty state when no items */}
        {isEmpty && count === 0 && (
          <div className={cn(
            "flex items-center justify-center p-8 border-2 border-dashed rounded-md",
            isOver ? "border-primary/50 bg-primary/10" : "border-gray-300"
          )}>
            <p className={cn(
              "text-sm font-medium",
              isOver ? "text-primary" : "text-muted-foreground"
            )}>
              {isOver ? "Drop here" : "No opportunities"}
            </p>
          </div>
        )}
      </div>
    </div>
  )
}