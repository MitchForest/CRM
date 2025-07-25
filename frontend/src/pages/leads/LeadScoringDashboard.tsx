import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Brain, RefreshCw, Download } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AIScoreDisplay } from '@/components/features/ai-scoring/AIScoreDisplay';
import { apiClient } from '@/lib/api-client';
import { aiService } from '@/services/ai.service';
import { useToast } from '@/components/ui/use-toast';
// import type { Lead } from '@/types/api.generated'; // Type already available from global imports
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
} from 'recharts';

export function LeadScoringDashboard() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [selectedLeadId, setSelectedLeadId] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState<'all' | 'scored' | 'unscored'>('all');
  const [selectedLeads, setSelectedLeads] = useState<string[]>([]);

  // Fetch leads
  const { data: leadsResponse, isLoading: isLoadingLeads } = useQuery({
    queryKey: ['leads', 'scoring', filterStatus],
    queryFn: async () => {
      const response = await apiClient.getLeads({ pageSize: 100 });
      if (!response.data) return { data: [], pagination: { total: 0 } };
      
      // Filter based on AI score status
      let filteredLeads = response.data;
      if (filterStatus === 'scored') {
        filteredLeads = response.data.filter(lead => lead.aiScore && lead.aiScore > 0);
      } else if (filterStatus === 'unscored') {
        filteredLeads = response.data.filter(lead => !lead.aiScore || lead.aiScore === 0);
      }
      
      return {
        data: filteredLeads,
        pagination: response.pagination
      };
    }
  });

  const leads = leadsResponse?.data || [];

  // Fetch selected lead's AI score
  const { data: selectedScore, isLoading: isLoadingScore } = useQuery({
    queryKey: ['ai-score', selectedLeadId],
    queryFn: () => aiService.scoreLead(selectedLeadId!),
    enabled: !!selectedLeadId,
    retry: false
  });

  // Score single lead mutation
  const scoreMutation = useMutation({
    mutationFn: (leadId: string) => aiService.scoreLead(leadId),
    onSuccess: (data, leadId) => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      queryClient.setQueryData(['ai-score', leadId], data);
      toast({
        title: 'Lead scored successfully',
        description: `AI Score: ${data.score}/100`,
      });
    },
    onError: (error: any) => {
      toast({
        title: 'Scoring failed',
        description: error.message || 'Unable to calculate AI score. Please try again.',
        variant: 'destructive',
      });
    },
  });

  // Batch score mutation
  const batchScoreMutation = useMutation({
    mutationFn: (leadIds: string[]) => aiService.batchScoreLeads(leadIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      toast({
        title: 'Batch scoring complete',
        description: 'All selected leads have been scored.',
      });
      setSelectedLeads([]);
    },
    onError: (error: any) => {
      toast({
        title: 'Batch scoring failed',
        description: error.message || 'Unable to score leads. Please try again.',
        variant: 'destructive',
      });
    },
  });

  // Calculate metrics
  const scoredLeads = leads.filter(lead => lead.aiScore && lead.aiScore > 0);
  const unscoredLeads = leads.filter(lead => !lead.aiScore || lead.aiScore === 0);
  
  const scoreDistribution = [
    { range: '0-20', count: scoredLeads.filter(l => l.aiScore! <= 20).length, color: '#ef4444' },
    { range: '21-40', count: scoredLeads.filter(l => l.aiScore! > 20 && l.aiScore! <= 40).length, color: '#f97316' },
    { range: '41-60', count: scoredLeads.filter(l => l.aiScore! > 40 && l.aiScore! <= 60).length, color: '#eab308' },
    { range: '61-80', count: scoredLeads.filter(l => l.aiScore! > 60 && l.aiScore! <= 80).length, color: '#84cc16' },
    { range: '81-100', count: scoredLeads.filter(l => l.aiScore! > 80).length, color: '#22c55e' },
  ];

  const averageScore = scoredLeads.length > 0
    ? Math.round(scoredLeads.reduce((sum, l) => sum + (l.aiScore || 0), 0) / scoredLeads.length)
    : 0;

  const hotLeads = scoredLeads.filter(l => l.aiScore! >= 80).length;
  const warmLeads = scoredLeads.filter(l => l.aiScore! >= 60 && l.aiScore! < 80).length;

  // Table columns
  const columns = [
    {
      id: 'select',
      header: ({ table }: any) => (
        <input
          type="checkbox"
          checked={table.getIsAllPageRowsSelected()}
          onChange={(e) => {
            table.toggleAllPageRowsSelected(!!e.target.checked);
            if (e.target.checked) {
              setSelectedLeads(table.getRowModel().rows.map((row: any) => row.original.id));
            } else {
              setSelectedLeads([]);
            }
          }}
          className="rounded border-gray-300"
        />
      ),
      cell: ({ row }: any) => (
        <input
          type="checkbox"
          checked={selectedLeads.includes(row.original.id)}
          onChange={(e) => {
            if (e.target.checked) {
              setSelectedLeads([...selectedLeads, row.original.id]);
            } else {
              setSelectedLeads(selectedLeads.filter(id => id !== row.original.id));
            }
          }}
          className="rounded border-gray-300"
        />
      ),
      enableSorting: false,
      enableHiding: false,
    },
    {
      accessorKey: 'name',
      header: 'Lead Name',
      cell: ({ row }: any) => (
        <div>
          <div className="font-medium">
            {row.original.firstName} {row.original.lastName}
          </div>
          <div className="text-sm text-muted-foreground">{row.original.email}</div>
        </div>
      ),
    },
    {
      accessorKey: 'accountName',
      header: 'Company',
    },
    {
      accessorKey: 'title',
      header: 'Title',
    },
    {
      accessorKey: 'aiScore',
      header: 'AI Score',
      cell: ({ row }: any) => {
        const score = row.original.aiScore;
        if (!score || score === 0) {
          return (
            <Button
              size="sm"
              variant="outline"
              onClick={() => scoreMutation.mutate(row.original.id)}
              disabled={scoreMutation.isPending}
            >
              {scoreMutation.isPending ? (
                <RefreshCw className="h-4 w-4 animate-spin" />
              ) : (
                'Calculate'
              )}
            </Button>
          );
        }
        
        const getScoreColor = (value: number) => {
          if (value >= 80) return 'text-green-600';
          if (value >= 60) return 'text-yellow-600';
          if (value >= 40) return 'text-orange-600';
          return 'text-red-600';
        };
        
        return (
          <div className="flex items-center gap-2">
            <div className={`font-bold text-lg ${getScoreColor(score)}`}>
              {score}
            </div>
            <Progress value={score} className="w-16 h-2" />
          </div>
        );
      },
    },
    {
      accessorKey: 'status',
      header: 'Status',
      cell: ({ row }: any) => (
        <Badge variant={row.original.status === 'New' ? 'default' : 'secondary'}>
          {row.original.status}
        </Badge>
      ),
    },
    {
      id: 'actions',
      cell: ({ row }: any) => (
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => setSelectedLeadId(row.original.id)}
          >
            View Details
          </Button>
          {(!row.original.aiScore || row.original.aiScore === 0) && (
            <Button
              size="sm"
              variant="ghost"
              onClick={() => scoreMutation.mutate(row.original.id)}
            >
              <Brain className="h-4 w-4" />
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-2">
            <Brain className="h-8 w-8" />
            AI Lead Scoring
          </h1>
          <p className="text-muted-foreground mt-1">
            Leverage AI to prioritize and score your leads automatically
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            onClick={() => {
              // Export functionality
              toast({
                title: 'Export started',
                description: 'Your lead scores will be downloaded shortly.',
              });
            }}
          >
            <Download className="mr-2 h-4 w-4" />
            Export
          </Button>
          <Button
            onClick={() => {
              const unscored = unscoredLeads.map(l => l.id!).filter(Boolean);
              if (unscored.length > 0) {
                batchScoreMutation.mutate(unscored);
              }
            }}
            disabled={unscoredLeads.length === 0 || batchScoreMutation.isPending}
          >
            {batchScoreMutation.isPending && (
              <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
            )}
            Score All Unscored ({unscoredLeads.length})
          </Button>
        </div>
      </div>

      {/* Metrics Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Leads</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{leads.length}</div>
            <p className="text-xs text-muted-foreground">
              {scoredLeads.length} scored, {unscoredLeads.length} unscored
            </p>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Average Score</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{averageScore}</div>
            <Progress value={averageScore} className="mt-2 h-2" />
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Hot Leads</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">{hotLeads}</div>
            <p className="text-xs text-muted-foreground">Score 80+</p>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Warm Leads</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-yellow-600">{warmLeads}</div>
            <p className="text-xs text-muted-foreground">Score 60-79</p>
          </CardContent>
        </Card>
      </div>

      {/* Main Content */}
      <div className="grid gap-6 lg:grid-cols-3">
        {/* Charts */}
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Score Distribution</CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={scoreDistribution}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="range" />
                  <YAxis />
                  <Tooltip />
                  <Bar dataKey="count">
                    {scoreDistribution.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          {/* Leads Table */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Leads</CardTitle>
                <div className="flex items-center gap-2">
                  <Select value={filterStatus} onValueChange={(value: any) => setFilterStatus(value)}>
                    <SelectTrigger className="w-32">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Leads</SelectItem>
                      <SelectItem value="scored">Scored</SelectItem>
                      <SelectItem value="unscored">Unscored</SelectItem>
                    </SelectContent>
                  </Select>
                  {selectedLeads.length > 0 && (
                    <Button
                      size="sm"
                      onClick={() => batchScoreMutation.mutate(selectedLeads)}
                      disabled={batchScoreMutation.isPending}
                    >
                      Score Selected ({selectedLeads.length})
                    </Button>
                  )}
                </div>
              </div>
            </CardHeader>
            <CardContent>
              {isLoadingLeads ? (
                <div className="flex items-center justify-center h-32">
                  <RefreshCw className="h-6 w-6 animate-spin" />
                </div>
              ) : (
                <DataTable
                  columns={columns}
                  data={leads}
                />
              )}
            </CardContent>
          </Card>
        </div>

        {/* Selected Lead Score */}
        <div>
          {selectedLeadId ? (
            <AIScoreDisplay
              score={selectedScore}
              isLoading={isLoadingScore}
              onRefresh={() => scoreMutation.mutate(selectedLeadId)}
            />
          ) : (
            <Card>
              <CardContent className="pt-6">
                <div className="text-center text-muted-foreground">
                  <Brain className="h-12 w-12 mx-auto mb-4 opacity-50" />
                  <p>Select a lead to view detailed AI scoring</p>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}