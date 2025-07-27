import { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { ArrowLeft, Edit, Trash2, DollarSign, Calendar, User, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useOpportunity, useDeleteOpportunity } from '@/hooks/use-opportunities';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/utils';
import { ActivityTimeline } from '@/components/activities/ActivityTimeline';
import { useActivitiesByParent } from '@/hooks/use-activities';

const stageColors = {
  prospecting: 'bg-gray-100 text-gray-700',
  qualification: 'bg-blue-100 text-blue-700',
  needs_analysis: 'bg-indigo-100 text-indigo-700',
  value_proposition: 'bg-purple-100 text-purple-700',
  decision_makers: 'bg-pink-100 text-pink-700',
  perception_analysis: 'bg-purple-100 text-purple-700',
  proposal: 'bg-orange-100 text-orange-700',
  negotiation: 'bg-yellow-100 text-yellow-700',
  closed_won: 'bg-green-100 text-green-700',
  closed_lost: 'bg-red-100 text-red-700',
};

const stageProbabilities = {
  prospecting: 10,
  qualification: 20,
  needs_analysis: 25,
  value_proposition: 30,
  decision_makers: 40,
  perception_analysis: 50,
  proposal: 65,
  negotiation: 80,
  closed_won: 100,
  closed_lost: 0,
};

export function OpportunityDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  
  const { data: opportunityResponse, isLoading } = useOpportunity(id!);
  const opportunity = opportunityResponse?.data;
  const deleteOpportunity = useDeleteOpportunity();
  const { data: activities } = useActivitiesByParent('Opportunity', id!);

  const handleDelete = async () => {
    await deleteOpportunity.mutateAsync(id!);
    navigate('/opportunities');
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Skeleton className="h-10 w-32" />
          <Skeleton className="h-8 w-48" />
        </div>
        <div className="grid gap-6 md:grid-cols-3">
          <Skeleton className="h-64 md:col-span-2" />
          <Skeleton className="h-64" />
        </div>
      </div>
    );
  }

  if (!opportunity) {
    return (
      <div className="flex flex-col items-center justify-center h-64">
        <TrendingUp className="h-12 w-12 text-muted-foreground mb-4" />
        <p className="text-lg font-semibold">Opportunity not found</p>
        <p className="text-muted-foreground">The opportunity you're looking for doesn't exist.</p>
        <Button asChild className="mt-4">
          <Link to="/opportunities">Back to Opportunities</Link>
        </Button>
      </div>
    );
  }

  const probability = stageProbabilities[opportunity.sales_stage as keyof typeof stageProbabilities] || 0;
  const expectedValue = (opportunity.amount || 0) * (probability / 100);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" asChild>
            <Link to="/opportunities">
              <ArrowLeft className="h-4 w-4" />
            </Link>
          </Button>
          <div>
            <h1 className="text-3xl font-bold">{opportunity.name}</h1>
            <div className="flex items-center gap-4 mt-1 text-muted-foreground">
              <Badge className={stageColors[opportunity.sales_stage as keyof typeof stageColors] || 'bg-gray-100'}>
                {opportunity.sales_stage?.split('_').map((word: string) => 
                  word.charAt(0).toUpperCase() + word.slice(1)
                ).join(' ')}
              </Badge>
              <span>{probability}% probability</span>
            </div>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" asChild>
            <Link to={`/opportunities/${id}`}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </Button>
          <Button variant="outline" onClick={() => setShowDeleteDialog(true)}>
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <div className="md:col-span-2">
          <Tabs defaultValue="overview">
            <TabsList>
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="activities">
                Activities {activities && `(${activities.length})`}
              </TabsTrigger>
              <TabsTrigger value="notes">Notes</TabsTrigger>
            </TabsList>

            <TabsContent value="overview">
              <Card>
                <CardHeader>
                  <CardTitle>Opportunity Details</CardTitle>
                  <CardDescription>
                    Key information about this opportunity
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Amount</p>
                      <p className="text-2xl font-bold">{formatCurrency(opportunity.amount)}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Expected Value</p>
                      <p className="text-2xl font-bold">{formatCurrency(expectedValue)}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Close Date</p>
                      <p className="flex items-center gap-2">
                        <Calendar className="h-4 w-4" />
                        {formatDate(opportunity.date_closed || '')}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Contact</p>
                      {opportunity.account_id && opportunity.account_name ? (
                        <Link 
                          to={`/accounts/${opportunity.account_id}`}
                          className="flex items-center gap-2 text-primary hover:underline"
                        >
                          <User className="h-4 w-4" />
                          {opportunity.account_name}
                        </Link>
                      ) : (
                        <p className="text-muted-foreground">Not specified</p>
                      )}
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Probability</p>
                      <p>{opportunity.probability || probability}%</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Assigned To</p>
                      <p className="flex items-center gap-2">
                        <User className="h-4 w-4" />
                        {opportunity.assigned_user_name || 'Unassigned'}
                      </p>
                    </div>
                  </div>
                  
                  {opportunity.description && (
                    <div className="pt-4 border-t">
                      <p className="text-sm font-medium text-muted-foreground mb-2">Description</p>
                      <p className="text-sm whitespace-pre-wrap">{opportunity.description}</p>
                    </div>
                  )}

                  <div className="pt-4 border-t">
                    <p className="text-sm font-medium text-muted-foreground mb-2">Stage Progress</p>
                    <Progress value={probability} className="mb-2" />
                    <p className="text-xs text-muted-foreground">
                      {probability}% probability of closing
                    </p>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="activities">
              <Card>
                <CardHeader>
                  <CardTitle>Activity Timeline</CardTitle>
                  <CardDescription>
                    All activities related to this opportunity
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ActivityTimeline parentType="Opportunity" parentId={id!} />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="notes">
              <Card>
                <CardHeader>
                  <CardTitle>Notes & Updates</CardTitle>
                  <CardDescription>
                    Track important information and updates
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <Button className="w-full" variant="outline">
                      Add Note
                    </Button>
                    <p className="text-center text-muted-foreground text-sm">
                      Notes will be displayed here
                    </p>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button className="w-full" size="sm">
                <DollarSign className="mr-2 h-4 w-4" />
                Update Amount
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Move to Next Stage
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Schedule Meeting
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Create Quote
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Days in Stage</p>
                <p className="text-2xl font-bold">
                  {opportunity.date_modified && 
                    Math.floor((new Date().getTime() - new Date(opportunity.date_modified).getTime()) / (1000 * 60 * 60 * 24))
                  } days
                </p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Created</p>
                <p className="text-sm">{formatDateTime(opportunity.date_entered || '')}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Last Modified</p>
                <p className="text-sm">{formatDateTime(opportunity.date_modified || opportunity.date_entered || '')}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Opportunity</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete this opportunity? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              Delete Opportunity
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}