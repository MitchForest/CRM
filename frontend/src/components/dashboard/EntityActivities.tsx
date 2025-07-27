import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { formatDateTime } from '@/lib/utils';
import { Phone, Calendar, CheckSquare, FileText, Clock } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from 'react-router-dom';
import { cn } from '@/lib/utils';
import type { Call, Meeting, Task, Note } from '@/types/api.generated';

interface EntityActivitiesProps {
  entityType: 'Lead' | 'Opportunity' | 'Case';
  limit?: number;
}

type ActivityWithType = 
  | (Call & { type: 'call'; date: string })
  | (Meeting & { type: 'meeting'; date: string })
  | (Task & { type: 'task'; date: string })
  | (Note & { type: 'note'; date: string });

export function EntityActivities({ entityType, limit = 10 }: EntityActivitiesProps) {
  const { data: activities, isLoading } = useQuery({
    queryKey: ['dashboard', 'activities', entityType],
    queryFn: async () => {
      // Fetch all activity types and filter by parent type
      const filters = [
        { field: 'parentType', operator: 'eq' as const, value: entityType }
      ];
      
      const [calls, meetings, tasks, notes] = await Promise.all([
        apiClient.getCalls({ pageSize: limit, filters }),
        apiClient.getMeetings({ pageSize: limit, filters }),
        apiClient.getTasks({ pageSize: limit, filters }),
        apiClient.getNotes({ pageSize: limit, filters }),
      ]);

      // Combine and sort - using camelCase field names from generated types
      const combined: ActivityWithType[] = [
        ...calls.data.map(c => ({ ...c, type: 'call' as const, date: c.startDate || c.createdAt || '' })),
        ...meetings.data.map(m => ({ ...m, type: 'meeting' as const, date: m.startDate || m.createdAt || '' })),
        ...tasks.data.map(t => ({ ...t, type: 'task' as const, date: t.dueDate || t.createdAt || '' })),
        ...notes.data.map(n => ({ ...n, type: 'note' as const, date: n.createdAt || '' })),
      ].sort((a, b) => new Date(b.date || 0).getTime() - new Date(a.date || 0).getTime());

      return combined.slice(0, limit);
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-3">
        {[1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-16" />
        ))}
      </div>
    );
  }

  if (!activities || activities.length === 0) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <Clock className="mx-auto h-12 w-12 mb-3 opacity-20" />
        <p>No {entityType.toLowerCase()} activities yet</p>
      </div>
    );
  }

  const getIcon = (type: string) => {
    switch (type) {
      case 'call': return <Phone className="h-4 w-4" />;
      case 'meeting': return <Calendar className="h-4 w-4" />;
      case 'task': return <CheckSquare className="h-4 w-4" />;
      case 'note': return <FileText className="h-4 w-4" />;
      default: return <Clock className="h-4 w-4" />;
    }
  };

  const getActivityUrl = (activity: { parentId?: string }) => {
    if (!activity.parentId) return null;
    return `/${entityType.toLowerCase()}s/${activity.parentId}`;
  };

  return (
    <div className="space-y-3">
      {activities.map((activity) => {
        const parentUrl = getActivityUrl(activity);
        return (
          <div
            key={`${activity.type}-${activity.id}`}
            className="flex items-start gap-3 p-3 rounded-lg border bg-card"
          >
            <div className={cn(
              "p-2 rounded-full",
              activity.type === 'call' ? "bg-blue-100 text-blue-700" :
              activity.type === 'meeting' ? "bg-purple-100 text-purple-700" :
              activity.type === 'task' ? "bg-green-100 text-green-700" :
              "bg-yellow-100 text-yellow-700"
            )}>
              {getIcon(activity.type)}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-medium truncate">{activity.name}</p>
              {parentUrl && activity.parentType && (
                <Link 
                  to={parentUrl}
                  className="text-sm text-primary hover:underline"
                >
                  {activity.parentType}
                </Link>
              )}
              <p className="text-xs text-muted-foreground mt-1">
                {formatDateTime(activity.date || '')}
                {activity.assignedUserName && ` • ${activity.assignedUserName}`}
              </p>
            </div>
            {activity.type === 'task' && 'status' in activity && activity.status && (
              <span className={cn(
                "px-2 py-1 text-xs rounded-full",
                activity.status === 'Completed' ? "bg-green-100 text-green-700" :
                ('dueDate' in activity && activity.dueDate && new Date(activity.dueDate) < new Date()) ? "bg-red-100 text-red-700" :
                "bg-gray-100 text-gray-700"
              )}>
                {activity.status}
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}