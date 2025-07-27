import { useState } from 'react';
import { Brain, AlertCircle, CheckCircle, RefreshCw, ChevronDown, ChevronUp } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { AIScoreResult } from '@/types/api.types';

interface AIScoreDisplayProps {
  score?: AIScoreResult;
  isLoading?: boolean;
  onRefresh?: () => void;
  className?: string;
  showDetails?: boolean;
}

export function AIScoreDisplay({ 
  score, 
  isLoading, 
  onRefresh, 
  className,
  showDetails = true 
}: AIScoreDisplayProps) {
  const [isExpanded, setIsExpanded] = useState(false);

  if (isLoading) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            AI Lead Score
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-4 w-1/2" />
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!score) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            AI Lead Score
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8">
            <p className="text-muted-foreground mb-4">No score available</p>
            {onRefresh && (
              <Button onClick={onRefresh} variant="outline" size="sm">
                <Brain className="mr-2 h-4 w-4" />
                Calculate Score
              </Button>
            )}
          </div>
        </CardContent>
      </Card>
    );
  }

  const getScoreColor = (value: number) => {
    if (value >= 80) return 'text-green-600 dark:text-green-400';
    if (value >= 60) return 'text-yellow-600 dark:text-yellow-400';
    if (value >= 40) return 'text-orange-600 dark:text-orange-400';
    return 'text-red-600 dark:text-red-400';
  };

  const getScoreLabel = (value: number) => {
    if (value >= 80) return 'Hot Lead';
    if (value >= 60) return 'Warm Lead';
    if (value >= 40) return 'Cool Lead';
    return 'Cold Lead';
  };

  const getScoreBadgeVariant = (value: number): "default" | "secondary" | "outline" | "destructive" => {
    if (value >= 80) return 'default';
    if (value >= 60) return 'secondary';
    if (value >= 40) return 'outline';
    return 'destructive';
  };

  const getFactorLabel = (key: string) => {
    const labels: Record<string, string> = {
      company_size: 'Company Size',
      industry_match: 'Industry Match',
      behavior_score: 'Behavior Score',
      engagement: 'Engagement Level',
      budget_signals: 'Budget Signals'
    };
    return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  };

  return (
    <Card className={className}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            AI Lead Score
          </CardTitle>
          {onRefresh && (
            <Button onClick={onRefresh} variant="ghost" size="sm">
              <RefreshCw className="h-4 w-4" />
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Main Score */}
        <div className="text-center">
          <div className={cn("text-6xl font-bold mb-2", getScoreColor(score.score))}>
            {score.score}
          </div>
          <Badge variant={getScoreBadgeVariant(score.score)} className="mb-2">
            {getScoreLabel(score.score)}
          </Badge>
          <p className="text-sm text-muted-foreground">
            Confidence: {Math.round(score.confidence * 100)}%
          </p>
        </div>

        {/* Score Factors */}
        {showDetails && (
          <Collapsible open={isExpanded} onOpenChange={setIsExpanded}>
            <CollapsibleTrigger asChild>
              <Button variant="ghost" className="w-full justify-between p-0 h-auto font-medium">
                <span className="text-sm">Score Breakdown</span>
                {isExpanded ? (
                  <ChevronUp className="h-4 w-4" />
                ) : (
                  <ChevronDown className="h-4 w-4" />
                )}
              </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-3 pt-4">
              {Object.entries(score.factors).map(([key, value]) => (
                <div key={key} className="space-y-1">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                      {getFactorLabel(key)}
                    </span>
                    <span className="font-medium">{value}/20</span>
                  </div>
                  <Progress 
                    value={(value / 20) * 100} 
                    className="h-2"
                  />
                </div>
              ))}
            </CollapsibleContent>
          </Collapsible>
        )}

        {/* AI Insights */}
        {score.insights && score.insights.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <AlertCircle className="h-4 w-4 text-yellow-500" />
              AI Insights
            </h4>
            <ul className="space-y-2">
              {score.insights.map((insight, index) => (
                <li key={index} className="text-sm text-muted-foreground flex items-start gap-2">
                  <span className="text-yellow-500 mt-0.5">•</span>
                  <span>{insight}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Recommended Actions */}
        {score.recommended_actions && score.recommended_actions.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <CheckCircle className="h-4 w-4 text-green-500" />
              Recommended Actions
            </h4>
            <ul className="space-y-2">
              {score.recommended_actions.map((action, index) => (
                <li key={index} className="text-sm text-muted-foreground flex items-start gap-2">
                  <span className="text-green-500 mt-0.5">→</span>
                  <span>{action}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Score Trend (if history available) */}
        {score.created_at && (
          <div className="pt-2 border-t">
            <p className="text-xs text-muted-foreground text-center">
              Last calculated: {new Date(score.created_at).toLocaleString()}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}