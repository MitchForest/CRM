import { formatDistanceToNow } from 'date-fns'
import { 
  Mail, 
  Phone, 
  Calendar, 
  FileText, 
  CheckCircle,
  Clock,
  ArrowUpRight,
  ArrowDownLeft,
  MessageSquare,
  Briefcase,
  Activity as ActivityIcon
} from 'lucide-react'
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { EmptyState } from '@/components/ui/empty-state'
import type { Activity } from '@/types/api.generated'

interface ActivityTimelineProps {
  activities: Activity[]
  isLoading?: boolean
  onActivityClick?: (activity: Activity) => void
  emptyStateAction?: {
    label: string
    onClick: () => void
  }
}

const activityConfig = {
  Call: {
    icon: Phone,
    color: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    label: 'Call',
  },
  Meeting: {
    icon: Calendar,
    color: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    label: 'Meeting',
  },
  Email: {
    icon: Mail,
    color: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    label: 'Email',
  },
  Task: {
    icon: CheckCircle,
    color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    label: 'Task',
  },
  Note: {
    icon: FileText,
    color: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    label: 'Note',
  },
} as const

const statusConfig = {
  completed: { label: 'Completed', variant: 'default' as const },
  planned: { label: 'Planned', variant: 'secondary' as const },
  held: { label: 'Held', variant: 'default' as const },
  cancelled: { label: 'Cancelled', variant: 'destructive' as const },
  sent: { label: 'Sent', variant: 'default' as const },
  received: { label: 'Received', variant: 'secondary' as const },
  draft: { label: 'Draft', variant: 'outline' as const },
}

export function ActivityTimeline({
  activities,
  isLoading = false,
  onActivityClick,
  emptyStateAction,
}: ActivityTimelineProps) {
  if (isLoading) {
    return (
      <div className="space-y-6">
        {[...Array(3)].map((_, i) => (
          <div key={i} className="flex gap-4">
            <Skeleton className="h-10 w-10 rounded-full" />
            <Skeleton className="h-24 flex-1" />
          </div>
        ))}
      </div>
    )
  }

  if (activities.length === 0) {
    return (
      <Card className="p-6">
        <EmptyState
          icon={ActivityIcon}
          title="No activities yet"
          description="Activities will appear here as you interact with contacts"
          action={emptyStateAction}
        />
      </Card>
    )
  }

  // Group activities by date
  const groupedActivities = activities.reduce((groups, activity) => {
    const date = new Date(activity.date).toDateString()
    if (!groups[date]) {
      groups[date] = []
    }
    groups[date].push(activity)
    return groups
  }, {} as Record<string, Activity[]>)

  return (
    <div className="relative">
      {/* Timeline line */}
      <div className="absolute left-5 top-0 bottom-0 w-0.5 bg-border" />

      {/* Activities grouped by date */}
      <div className="space-y-8">
        {Object.entries(groupedActivities).map(([date, dateActivities]) => (
          <div key={date}>
            <div className="sticky top-0 z-10 bg-background pb-2">
              <h3 className="text-sm font-semibold text-muted-foreground">
                {formatDateGroup(date)}
              </h3>
            </div>
            
            <div className="space-y-4">
              {dateActivities.map((activity) => {
                const config = activityConfig[activity.type as keyof typeof activityConfig] || activityConfig.Note
                const Icon = config.icon
                const status = activity.status ? statusConfig[activity.status as keyof typeof statusConfig] : null

                return (
                  <div key={activity.id} className="relative flex gap-4">
                    {/* Icon */}
                    <div className={`relative z-10 flex h-10 w-10 items-center justify-center rounded-full ${config.color}`}>
                      <Icon className="h-5 w-5" />
                    </div>

                    {/* Content */}
                    <Card 
                      className="flex-1 p-4 cursor-pointer hover:shadow-md transition-shadow"
                      onClick={() => onActivityClick?.(activity)}
                    >
                      <div className="space-y-2">
                        <div className="flex items-start justify-between">
                          <div className="space-y-1">
                            <div className="flex items-center gap-2">
                              <h4 className="font-medium line-clamp-1">
                                {activity.subject || 'Untitled'}
                              </h4>
                              {status && (
                                <Badge variant={status.variant} className="text-xs">
                                  {status.label}
                                </Badge>
                              )}
                            </div>
                            
                            {activity.description && (
                              <p className="text-sm text-muted-foreground line-clamp-2">
                                {activity.description}
                              </p>
                            )}

                            {/* Activity-specific details */}
                            <div className="flex items-center gap-4 text-xs text-muted-foreground">
                              {activity.relatedTo && (
                                <span className="flex items-center gap-1">
                                  <Briefcase className="h-3 w-3" />
                                  {activity.relatedTo}
                                </span>
                              )}
                              
                              {activity.assignedToName && (
                                <span>Assigned to {activity.assignedToName}</span>
                              )}
                            </div>
                          </div>

                          <span className="text-xs text-muted-foreground whitespace-nowrap">
                            {formatTime(activity.date)}
                          </span>
                        </div>
                      </div>
                    </Card>
                  </div>
                )
              })}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

function formatDateGroup(dateString: string): string {
  const date = new Date(dateString)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  if (date.toDateString() === today.toDateString()) {
    return 'Today'
  } else if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday'
  } else {
    return date.toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
    })
  }
}

function formatTime(dateString: string): string {
  return new Date(dateString).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  })
}