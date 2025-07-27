import { useState, useEffect } from 'react';
import { apiClient } from '@/lib/api-client';
import { formatDateTime } from '@/lib/utils';
import { 
  Phone, Calendar, CheckSquare, FileText, Clock, Globe, 
  MessageSquare, FormInput, TrendingUp, MousePointer,
  User, Mail, ArrowRight, UserCheck
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import type { TimelineActivity } from '@/types/api.types';

interface UnifiedActivityTimelineProps {
  entityType: 'lead' | 'contact' | 'opportunity';
  entityId: string;
  className?: string;
}

export function UnifiedActivityTimeline({ entityType, entityId, className }: UnifiedActivityTimelineProps) {
  const [activities, setActivities] = useState<TimelineActivity[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [originalLeadId, setOriginalLeadId] = useState<string | null>(null);

  useEffect(() => {
    const fetchTimeline = async () => {
      try {
        setIsLoading(true);
        let response;
        
        if (entityType === 'lead') {
          response = await apiClient.getLeadTimeline(entityId, { limit: 100 });
        } else if (entityType === 'contact') {
          // For contacts, use the unified timeline that includes lead history
          const contactResponse = await apiClient.customGet(`/crm/contacts/${entityId}/timeline?limit=100`);
          response = {
            success: true,
            data: contactResponse.data
          };
          if (contactResponse.data?.original_lead_id) {
            setOriginalLeadId(contactResponse.data.original_lead_id);
          }
        } else {
          // For opportunities, we'll use a similar pattern
          response = await apiClient.customGet(`/crm/opportunities/${entityId}/timeline?limit=100`);
        }
        
        if (response.success && response.data) {
          setActivities(response.data.timeline || []);
        } else {
          setError('Failed to load timeline');
        }
      } catch (err) {
        console.error('Timeline fetch error:', err);
        setError('Failed to load timeline');
      } finally {
        setIsLoading(false);
      }
    };

    fetchTimeline();
  }, [entityType, entityId]);

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
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  if (!activities || activities.length === 0) {
    return (
      <div className={cn("text-center py-8 text-muted-foreground", className)}>
        <Clock className="mx-auto h-12 w-12 mb-3 opacity-20" />
        <p>No activities yet</p>
        <p className="text-sm mt-1">Activities will appear here as they are tracked</p>
      </div>
    );
  }

  const getActivityIcon = (activity: TimelineActivity) => {
    switch (activity.type) {
      case 'page_view':
        return <Globe className="h-5 w-5" />;
      case 'web_session':
      case 'session_start':
        return <MousePointer className="h-5 w-5" />;
      case 'form_submission':
        return <FormInput className="h-5 w-5" />;
      case 'chat_message':
      case 'chat_conversation':
        return <MessageSquare className="h-5 w-5" />;
      case 'lead_score_change':
      case 'score_change':
        return <TrendingUp className="h-5 w-5" />;
      case 'demo_scheduled':
        return <Calendar className="h-5 w-5" />;
      case 'lead_converted':
        return <UserCheck className="h-5 w-5" />;
      case 'call':
        return <Phone className="h-5 w-5" />;
      case 'meeting':
        return <Calendar className="h-5 w-5" />;
      case 'task':
        return <CheckSquare className="h-5 w-5" />;
      case 'note':
        return <FileText className="h-5 w-5" />;
      case 'email':
        return <Mail className="h-5 w-5" />;
      default:
        return <Clock className="h-5 w-5" />;
    }
  };

  const getActivityColor = (activity: TimelineActivity) => {
    // Special handling for phase indicators
    if (activity.phase === 'lead') {
      return 'bg-blue-50 text-blue-700 border-blue-200';
    }
    
    switch (activity.type) {
      case 'page_view':
      case 'web_session':
      case 'session_start':
        return 'bg-blue-100 text-blue-700 border-blue-200';
      case 'form_submission':
      case 'demo_scheduled':
        return 'bg-green-100 text-green-700 border-green-200';
      case 'lead_converted':
        return 'bg-purple-100 text-purple-700 border-purple-200';
      case 'chat_message':
      case 'chat_conversation':
        return 'bg-purple-100 text-purple-700 border-purple-200';
      case 'lead_score_change':
      case 'score_change':
        return 'bg-orange-100 text-orange-700 border-orange-200';
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

  const formatActivityTitle = (activity: TimelineActivity) => {
    switch (activity.type) {
      case 'page_view':
        return `Viewed ${activity.metadata?.page_title || activity.metadata?.page_url || 'page'}`;
      case 'web_session':
      case 'session_start':
        return 'Started new session';
      case 'form_submission':
        return `Submitted ${activity.metadata?.form_name || 'form'}`;
      case 'demo_scheduled':
        return 'Scheduled a demo';
      case 'lead_converted':
        return 'Lead converted to contact';
      case 'chat_message':
      case 'chat_conversation':
        return activity.metadata?.is_bot ? 'Chatbot interaction' : 'Chat conversation';
      case 'lead_score_change':
      case 'score_change':
        return `Lead score changed from ${activity.metadata?.old_score || 0} to ${activity.metadata?.new_score || 0}`;
      default:
        return activity.title || activity.type.replace(/_/g, ' ');
    }
  };

  const formatActivityDescription = (activity: TimelineActivity) => {
    if (activity.description) return activity.description;
    
    switch (activity.type) {
      case 'page_view':
        return `Spent ${activity.metadata?.time_on_page || 0} seconds on page`;
      case 'web_session':
      case 'session_start':
        return `From ${activity.metadata?.referrer || 'direct visit'}`;
      case 'form_submission':
        return activity.metadata?.form_data ? 
          `Form data: ${JSON.stringify(activity.metadata.form_data).substring(0, 100)}...` : 
          'Form submitted';
      case 'demo_scheduled':
        return `Scheduled for ${activity.metadata?.scheduled_time || 'TBD'}`;
      case 'lead_converted':
        return `Converted to contact ID: ${activity.metadata?.contact_id}`;
      default:
        return null;
    }
  };

  // Group activities by phase if we have lead history
  const hasLeadPhase = activities.some(a => a.phase === 'lead');
  
  return (
    <div className={cn("space-y-6", className)}>
      {originalLeadId && (
        <Alert className="mb-6">
          <UserCheck className="h-4 w-4" />
          <AlertDescription>
            This contact was converted from a lead. Showing complete activity history.
          </AlertDescription>
        </Alert>
      )}
      
      {hasLeadPhase && (
        <div className="mb-4">
          <h4 className="text-sm font-medium text-muted-foreground mb-2">Lead Phase</h4>
        </div>
      )}
      
      {activities.map((activity, index) => {
        const prevActivity = activities[index - 1];
        const showPhaseTransition = prevActivity?.phase === 'lead' && activity.phase === 'contact';
        
        return (
          <div key={`${activity.type}-${activity.timestamp}-${index}`}>
            {showPhaseTransition && (
              <div className="flex items-center gap-2 my-6 text-sm text-muted-foreground">
                <div className="flex-1 border-t" />
                <ArrowRight className="h-4 w-4" />
                <span className="font-medium">Converted to Contact</span>
                <ArrowRight className="h-4 w-4" />
                <div className="flex-1 border-t" />
              </div>
            )}
            
            <div className="flex gap-4">
              <div className={cn(
                "h-10 w-10 rounded-full border flex items-center justify-center flex-shrink-0",
                getActivityColor(activity)
              )}>
                {getActivityIcon(activity)}
              </div>
              
              <div className="flex-1 space-y-1">
                <div className="flex items-center gap-2 text-sm">
                  <span className="font-medium">
                    {formatActivityTitle(activity)}
                  </span>
                  {activity.phase === 'lead' && (
                    <Badge variant="outline" className="text-xs">
                      Lead Activity
                    </Badge>
                  )}
                  {activity.metadata?.is_conversion && (
                    <Badge className="text-xs bg-green-100 text-green-700">
                      Conversion
                    </Badge>
                  )}
                </div>
                
                {formatActivityDescription(activity) && (
                  <p className="text-sm text-muted-foreground">
                    {formatActivityDescription(activity)}
                  </p>
                )}
                
                {activity.metadata && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {activity.metadata.page_url && (
                      <Badge variant="outline" className="text-xs">
                        {activity.metadata.page_url}
                      </Badge>
                    )}
                    {activity.metadata.session_id && (
                      <Badge variant="outline" className="text-xs">
                        Session: {activity.metadata.session_id.substring(0, 8)}...
                      </Badge>
                    )}
                  </div>
                )}
                
                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                  <span>{formatDateTime(activity.timestamp)}</span>
                  {activity.user_name && (
                    <span className="flex items-center gap-1">
                      <User className="h-3 w-3" />
                      {activity.user_name}
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
} 