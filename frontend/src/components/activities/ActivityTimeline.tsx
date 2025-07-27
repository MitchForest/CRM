import { useActivitiesByParent } from '@/hooks/use-activities';
import { formatDateTime } from '@/lib/utils';
import { Phone, Calendar, CheckSquare, FileText, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Link } from 'react-router-dom';

interface ActivityTimelineProps {
  parentType: string;
  parentId: string;
  className?: string;
}

export function ActivityTimeline({ parentType, parentId, className }: ActivityTimelineProps) {
  const { data: activities, isLoading, error } = useActivitiesByParent(parentType, parentId);

  if (isLoading) {
    return (
      <div className={cn("space-y-4", className)}>
        {[1, 2, 3].map((i) => (
          <div key={i} className="flex gap-4">
            <Skeleton className="h-10 w-10 rounded-full" />
            <div className="flex-1 space-y-2">
              <Skeleton className="h-4 w-1/4" />
              <Skeleton className="h-3 w-3/4" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive" className={className}>
        <AlertDescription>
          Failed to load activities. Please try again later.
        </AlertDescription>
      </Alert>
    );
  }

  if (!activities || activities.length === 0) {
    return (
      <div className={cn("text-center py-8 text-muted-foreground", className)}>
        <Clock className="mx-auto h-12 w-12 mb-3 opacity-20" />
        <p>No activities yet</p>
        <p className="text-sm mt-1">Activities will appear here as they are added</p>
      </div>
    );
  }

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'call':
        return <Phone className="h-5 w-5" />;
      case 'meeting':
        return <Calendar className="h-5 w-5" />;
      case 'task':
        return <CheckSquare className="h-5 w-5" />;
      case 'note':
        return <FileText className="h-5 w-5" />;
      default:
        return <Clock className="h-5 w-5" />;
    }
  };

  const getActivityColor = (type: string) => {
    switch (type) {
      case 'call':
        return 'bg-blue-100 text-blue-700 border-blue-200';
      case 'meeting':
        return 'bg-purple-100 text-purple-700 border-purple-200';
      case 'task':
        return 'bg-green-100 text-green-700 border-green-200';
      case 'note':
        return 'bg-yellow-100 text-yellow-700 border-yellow-200';
      default:
        return 'bg-gray-100 text-gray-700 border-gray-200';
    }
  };

  const getActivityLabel = (type: string) => {
    switch (type) {
      case 'call':
        return 'Call';
      case 'meeting':
        return 'Meeting';
      case 'task':
        return 'Task';
      case 'note':
        return 'Note';
      default:
        return 'Activity';
    }
  };

  return (
    <div className={cn("space-y-6", className)}>
      {activities.map((activity) => (
        <div key={activity.id} className="flex gap-4">
          <div className={cn(
            "h-10 w-10 rounded-full border flex items-center justify-center flex-shrink-0",
            getActivityColor(activity.type)
          )}>
            {getActivityIcon(activity.type)}
          </div>
          
          <div className="flex-1 space-y-1">
            <div className="flex items-center gap-2 text-sm">
              <span className="font-medium">{getActivityLabel(activity.type)}</span>
              {activity.status && (
                <span className={cn(
                  "px-2 py-0.5 rounded-full text-xs",
                  activity.status === 'Completed' 
                    ? 'bg-green-100 text-green-700'
                    : activity.type === 'task' && 'date_due' in activity && activity.date_due && new Date(activity.date_due) < new Date()
                    ? 'bg-red-100 text-red-700'
                    : 'bg-gray-100 text-gray-700'
                )}>
                  {activity.status}
                </span>
              )}
            </div>
            
            <h4 className="font-medium">
              {activity.type === 'task' || activity.type === 'note' 
                ? activity.name || activity.description
                : activity.name}
            </h4>
            
            {activity.description && activity.type !== 'note' && (
              <p className="text-sm text-muted-foreground">{activity.description}</p>
            )}
            
            <div className="flex items-center gap-4 text-xs text-muted-foreground">
              <span>{formatDateTime(activity.date_entered || '')}</span>
              {activity.assigned_user_name && (
                <span>Assigned to {activity.assigned_user_name}</span>
              )}
              {activity.type === 'call' && 'duration_minutes' in activity && activity.duration_minutes && (
                <span>{activity.duration_minutes} min</span>
              )}
            </div>
            
            {activity.id && (
              <Link 
                to={`/activities/${activity.type}s/${activity.id}`}
                className="text-xs text-primary hover:underline inline-block mt-1"
              >
                View details →
              </Link>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}