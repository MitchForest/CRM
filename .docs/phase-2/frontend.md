# Phase 2 - Frontend Implementation Guide

## Overview
Phase 2 builds upon Phase 1's foundation to add core CRM functionality: Opportunities pipeline with kanban view, Activities management (Calls, Meetings, Tasks, Notes), Cases for support tickets, and email/document viewing. This phase also enhances the dashboard with pipeline visualization and implements role-based access controls.

## Prerequisites
- Phase 1 completed and working
- Frontend can authenticate and perform CRUD on Leads/Accounts
- Backend API is accessible and CORS is configured

## Step-by-Step Implementation

### 1. Additional Dependencies

#### 1.1 Install Required Packages
```bash
cd frontend
# Drag and drop for kanban
npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities

# Charts for dashboard
npm install recharts

# Date picker
npm install date-fns react-day-picker

# File upload
npm install react-dropzone

# Additional UI components
npm install @radix-ui/react-avatar @radix-ui/react-checkbox @radix-ui/react-popover @radix-ui/react-separator
```

### 2. Type Definitions Update

#### 2.1 Extend Types for New Modules
`src/types/index.ts` (add to existing file):
```typescript
// Opportunities
export interface Opportunity {
  id: string;
  name: string;
  account_id: string;
  account_name: string;
  sales_stage: string;
  amount: number;
  probability: number;
  date_closed: string;
  lead_source?: string;
  next_step?: string;
  description?: string;
  assigned_user_id?: string;
  assigned_user_name?: string;
  contacts?: Contact[];
  date_entered: string;
  date_modified: string;
}

export type OpportunityStage = 
  | 'Qualification'
  | 'Needs Analysis'
  | 'Value Proposition'
  | 'Decision Makers'
  | 'Proposal'
  | 'Negotiation'
  | 'Closed Won'
  | 'Closed Lost';

// Contacts
export interface Contact {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone_work?: string;
  phone_mobile?: string;
  title?: string;
  department?: string;
  account_id?: string;
  account_name?: string;
  primary_contact: boolean;
  date_entered: string;
  date_modified: string;
}

// Activities
export interface BaseActivity {
  id: string;
  name: string;
  status: string;
  priority?: string;
  parent_type?: string;
  parent_id?: string;
  parent_name?: string;
  assigned_user_id?: string;
  assigned_user_name?: string;
  date_entered: string;
  date_modified: string;
  description?: string;
}

export interface Call extends BaseActivity {
  duration_hours: number;
  duration_minutes: number;
  date_start: string;
  direction: 'Inbound' | 'Outbound';
}

export interface Meeting extends BaseActivity {
  duration_hours: number;
  duration_minutes: number;
  date_start: string;
  date_end: string;
  location?: string;
}

export interface Task extends BaseActivity {
  date_due?: string;
  date_start?: string;
  priority: 'High' | 'Medium' | 'Low';
}

export interface Note extends BaseActivity {
  file_mime_type?: string;
  filename?: string;
  file_url?: string;
}

// Cases (Support Tickets)
export interface Case {
  id: string;
  case_number: string;
  name: string;
  status: string;
  priority: 'P1' | 'P2' | 'P3';
  type: string;
  account_id?: string;
  account_name?: string;
  contact_id?: string;
  contact_name?: string;
  description?: string;
  resolution?: string;
  assigned_user_id?: string;
  assigned_user_name?: string;
  date_entered: string;
  date_modified: string;
}

// Emails
export interface Email {
  id: string;
  name: string;
  date_sent: string;
  from_addr: string;
  to_addrs: string;
  cc_addrs?: string;
  status: string;
  parent_type?: string;
  parent_id?: string;
  parent_name?: string;
  description: string;
  description_html?: string;
  attachments?: Document[];
}

// Documents
export interface Document {
  id: string;
  document_name: string;
  filename: string;
  file_ext: string;
  file_mime_type: string;
  file_size: number;
  file_url?: string;
  category_id?: string;
  description?: string;
  date_entered: string;
}

// Enhanced Dashboard Types
export interface PipelineStageData {
  stage: string;
  count: number;
  value: number;
  opportunities: Opportunity[];
}

export interface ActivityMetrics {
  calls_today: number;
  meetings_today: number;
  tasks_overdue: number;
  upcoming_activities: BaseActivity[];
}

export interface CaseMetrics {
  open_cases: number;
  critical_cases: number;
  avg_resolution_time: number;
  cases_by_priority: { priority: string; count: number }[];
}
```

### 3. Service Layer Extensions

#### 3.1 Create Opportunities Service
`src/services/opportunity.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Opportunity, ApiResponse } from '@/types';

export const opportunityService = {
  async getAll(params?: { 
    page?: number; 
    limit?: number; 
    filter?: string;
    stage?: string;
  }) {
    const response = await api.get<ApiResponse<Opportunity[]>>('/module/Opportunities', {
      params: {
        'page[number]': params?.page || 1,
        'page[size]': params?.limit || 50,
        'filter[sales_stage]': params?.stage,
        filter: params?.filter,
      },
    });
    return response.data;
  },

  async getById(id: string) {
    const response = await api.get<Opportunity>(`/module/Opportunities/${id}`);
    return response.data;
  },

  async create(data: Partial<Opportunity>) {
    const response = await api.post<Opportunity>('/module/Opportunities', {
      data: {
        type: 'Opportunities',
        attributes: data,
      },
    });
    return response.data;
  },

  async update(id: string, data: Partial<Opportunity>) {
    const response = await api.patch<Opportunity>(`/module/Opportunities/${id}`, {
      data: {
        type: 'Opportunities',
        id,
        attributes: data,
      },
    });
    return response.data;
  },

  async updateStage(id: string, newStage: string) {
    return this.update(id, { sales_stage: newStage });
  },

  async getContacts(id: string) {
    const response = await api.get(`/module/Opportunities/${id}/relationships/contacts`);
    return response.data;
  },

  async linkContact(opportunityId: string, contactId: string) {
    const response = await api.post(
      `/module/Opportunities/${opportunityId}/relationships/contacts`,
      {
        data: {
          type: 'Contacts',
          id: contactId,
        },
      }
    );
    return response.data;
  },
};
```

#### 3.2 Create Activities Services
`src/services/activity.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Call, Meeting, Task, Note, ApiResponse } from '@/types';

// Base service for common activity operations
class BaseActivityService<T> {
  constructor(private module: string) {}

  async getAll(params?: { page?: number; limit?: number; filter?: string }) {
    const response = await api.get<ApiResponse<T[]>>(`/module/${this.module}`, {
      params: {
        'page[number]': params?.page || 1,
        'page[size]': params?.limit || 20,
        filter: params?.filter,
      },
    });
    return response.data;
  }

  async getById(id: string) {
    const response = await api.get<T>(`/module/${this.module}/${id}`);
    return response.data;
  }

  async create(data: Partial<T>) {
    const response = await api.post<T>(`/module/${this.module}`, {
      data: {
        type: this.module,
        attributes: data,
      },
    });
    return response.data;
  }

  async update(id: string, data: Partial<T>) {
    const response = await api.patch<T>(`/module/${this.module}/${id}`, {
      data: {
        type: this.module,
        id,
        attributes: data,
      },
    });
    return response.data;
  }

  async delete(id: string) {
    await api.delete(`/module/${this.module}/${id}`);
  }
}

export const callService = new BaseActivityService<Call>('Calls');
export const meetingService = new BaseActivityService<Meeting>('Meetings');
export const taskService = new BaseActivityService<Task>('Tasks');
export const noteService = new BaseActivityService<Note>('Notes');

// Additional activity-specific methods
export const activityService = {
  async getUpcoming(limit = 10) {
    const today = new Date().toISOString();
    const [calls, meetings, tasks] = await Promise.all([
      callService.getAll({
        limit,
        filter: `date_start>${today}`,
      }),
      meetingService.getAll({
        limit,
        filter: `date_start>${today}`,
      }),
      taskService.getAll({
        limit,
        filter: `date_due>${today}`,
      }),
    ]);

    // Combine and sort by date
    const activities = [
      ...calls.data.map(c => ({ ...c, type: 'Call' })),
      ...meetings.data.map(m => ({ ...m, type: 'Meeting' })),
      ...tasks.data.map(t => ({ ...t, type: 'Task' })),
    ].sort((a, b) => {
      const dateA = new Date(a.date_start || a.date_due || '');
      const dateB = new Date(b.date_start || b.date_due || '');
      return dateA.getTime() - dateB.getTime();
    });

    return activities.slice(0, limit);
  },

  async getOverdueTasks() {
    const today = new Date().toISOString();
    return taskService.getAll({
      filter: `date_due<${today} AND status!=Completed`,
    });
  },
};
```

#### 3.3 Create Cases Service
`src/services/case.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Case, ApiResponse } from '@/types';

export const caseService = {
  async getAll(params?: { 
    page?: number; 
    limit?: number; 
    filter?: string;
    status?: string;
    priority?: string;
  }) {
    const response = await api.get<ApiResponse<Case[]>>('/module/Cases', {
      params: {
        'page[number]': params?.page || 1,
        'page[size]': params?.limit || 20,
        'filter[status]': params?.status,
        'filter[priority]': params?.priority,
        filter: params?.filter,
      },
    });
    return response.data;
  },

  async getById(id: string) {
    const response = await api.get<Case>(`/module/Cases/${id}`);
    return response.data;
  },

  async create(data: Partial<Case>) {
    const response = await api.post<Case>('/module/Cases', {
      data: {
        type: 'Cases',
        attributes: data,
      },
    });
    return response.data;
  },

  async update(id: string, data: Partial<Case>) {
    const response = await api.patch<Case>(`/module/Cases/${id}`, {
      data: {
        type: 'Cases',
        id,
        attributes: data,
      },
    });
    return response.data;
  },

  async resolve(id: string, resolution: string) {
    return this.update(id, {
      status: 'Closed',
      resolution,
    });
  },

  async getByAccount(accountId: string) {
    const response = await api.get<ApiResponse<Case[]>>('/module/Cases', {
      params: {
        'filter[account_id]': accountId,
      },
    });
    return response.data;
  },
};
```

### 4. UI Components

#### 4.1 Create Date Picker Component
`src/components/ui/date-picker.tsx`:
```typescript
import * as React from "react";
import { format } from "date-fns";
import { Calendar as CalendarIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";

interface DatePickerProps {
  date?: Date;
  onDateChange: (date: Date | undefined) => void;
  placeholder?: string;
}

export function DatePicker({ date, onDateChange, placeholder = "Pick a date" }: DatePickerProps) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "w-full justify-start text-left font-normal",
            !date && "text-muted-foreground"
          )}
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {date ? format(date, "PPP") : <span>{placeholder}</span>}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={date}
          onSelect={onDateChange}
          initialFocus
        />
      </PopoverContent>
    </Popover>
  );
}
```

#### 4.2 Create Priority Badge Component
`src/components/ui/priority-badge.tsx`:
```typescript
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

interface PriorityBadgeProps {
  priority: 'P1' | 'P2' | 'P3' | 'High' | 'Medium' | 'Low';
  className?: string;
}

export function PriorityBadge({ priority, className }: PriorityBadgeProps) {
  const getColorClass = () => {
    switch (priority) {
      case 'P1':
      case 'High':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'P2':
      case 'Medium':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'P3':
      case 'Low':
        return 'bg-green-100 text-green-800 border-green-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  return (
    <Badge variant="outline" className={cn(getColorClass(), className)}>
      {priority}
    </Badge>
  );
}
```

### 5. Opportunities Pipeline Implementation

#### 5.1 Create Kanban Board Component
`src/components/features/OpportunitiesKanban.tsx`:
```typescript
import { useState, useEffect } from 'react';
import {
  DndContext,
  DragEndEvent,
  DragOverlay,
  DragStartEvent,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { KanbanColumn } from './KanbanColumn';
import { OpportunityCard } from './OpportunityCard';
import { opportunityService } from '@/services/opportunity.service';
import type { Opportunity, OpportunityStage } from '@/types';
import { formatCurrency } from '@/lib/utils';

const stages: OpportunityStage[] = [
  'Qualification',
  'Needs Analysis',
  'Value Proposition',
  'Decision Makers',
  'Proposal',
  'Negotiation',
  'Closed Won',
  'Closed Lost',
];

interface OpportunitiesKanbanProps {
  opportunities: Opportunity[];
}

export function OpportunitiesKanban({ opportunities }: OpportunitiesKanbanProps) {
  const [activeId, setActiveId] = useState<string | null>(null);
  const queryClient = useQueryClient();

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  );

  const updateStageMutation = useMutation({
    mutationFn: ({ id, stage }: { id: string; stage: string }) =>
      opportunityService.updateStage(id, stage),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] });
    },
  });

  const handleDragStart = (event: DragStartEvent) => {
    setActiveId(event.active.id as string);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (!over || active.id === over.id) {
      setActiveId(null);
      return;
    }

    const opportunity = opportunities.find(opp => opp.id === active.id);
    const newStage = over.id as string;

    if (opportunity && opportunity.sales_stage !== newStage) {
      updateStageMutation.mutate({
        id: opportunity.id,
        stage: newStage,
      });
    }

    setActiveId(null);
  };

  const opportunitiesByStage = stages.reduce((acc, stage) => {
    acc[stage] = opportunities.filter(opp => opp.sales_stage === stage);
    return acc;
  }, {} as Record<OpportunityStage, Opportunity[]>);

  const activeOpportunity = activeId
    ? opportunities.find(opp => opp.id === activeId)
    : null;

  return (
    <DndContext
      sensors={sensors}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <ScrollArea className="h-[calc(100vh-12rem)] w-full">
        <div className="flex gap-4 p-4">
          {stages.map((stage) => {
            const stageOpportunities = opportunitiesByStage[stage] || [];
            const totalValue = stageOpportunities.reduce(
              (sum, opp) => sum + (opp.amount || 0),
              0
            );

            return (
              <KanbanColumn
                key={stage}
                id={stage}
                title={stage}
                count={stageOpportunities.length}
                value={formatCurrency(totalValue)}
              >
                <SortableContext
                  items={stageOpportunities.map(opp => opp.id)}
                  strategy={verticalListSortingStrategy}
                >
                  {stageOpportunities.map((opportunity) => (
                    <OpportunityCard
                      key={opportunity.id}
                      opportunity={opportunity}
                    />
                  ))}
                </SortableContext>
              </KanbanColumn>
            );
          })}
        </div>
      </ScrollArea>

      <DragOverlay>
        {activeOpportunity && (
          <Card className="cursor-grabbing opacity-50">
            <OpportunityCard opportunity={activeOpportunity} />
          </Card>
        )}
      </DragOverlay>
    </DndContext>
  );
}
```

#### 5.2 Create Kanban Column Component
`src/components/features/KanbanColumn.tsx`:
```typescript
import { useDroppable } from '@dnd-kit/core';
import { cn } from '@/lib/utils';

interface KanbanColumnProps {
  id: string;
  title: string;
  count: number;
  value: string;
  children: React.ReactNode;
}

export function KanbanColumn({ id, title, count, value, children }: KanbanColumnProps) {
  const { isOver, setNodeRef } = useDroppable({
    id,
  });

  const isClosedStage = id === 'Closed Won' || id === 'Closed Lost';
  const isWonStage = id === 'Closed Won';

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
  );
}
```

#### 5.3 Create Opportunity Card Component
`src/components/features/OpportunityCard.tsx`:
```typescript
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link } from 'react-router-dom';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Building2, Calendar, DollarSign } from 'lucide-react';
import type { Opportunity } from '@/types';
import { formatCurrency, formatDate } from '@/lib/utils';

interface OpportunityCardProps {
  opportunity: Opportunity;
}

export function OpportunityCard({ opportunity }: OpportunityCardProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: opportunity.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  const probabilityColor = 
    opportunity.probability >= 70 ? 'text-green-600' :
    opportunity.probability >= 40 ? 'text-yellow-600' :
    'text-red-600';

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      className={isDragging ? 'opacity-50' : ''}
    >
      <Card className="cursor-grab hover:shadow-md active:cursor-grabbing">
        <CardContent className="p-4">
          <Link
            to={`/opportunities/${opportunity.id}`}
            className="block space-y-2"
            onClick={(e) => e.stopPropagation()}
          >
            <h4 className="font-medium line-clamp-1">{opportunity.name}</h4>
            
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Building2 className="h-3 w-3" />
              <span className="line-clamp-1">{opportunity.account_name}</span>
            </div>

            <div className="flex items-center justify-between">
              <div className="flex items-center gap-1">
                <DollarSign className="h-3 w-3" />
                <span className="font-semibold">
                  {formatCurrency(opportunity.amount)}
                </span>
              </div>
              <Badge variant="secondary" className={probabilityColor}>
                {opportunity.probability}%
              </Badge>
            </div>

            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <Calendar className="h-3 w-3" />
              <span>Close: {formatDate(opportunity.date_closed)}</span>
            </div>

            {opportunity.next_step && (
              <p className="text-xs text-muted-foreground line-clamp-2">
                Next: {opportunity.next_step}
              </p>
            )}
          </Link>
        </CardContent>
      </Card>
    </div>
  );
}
```

### 6. Opportunities Pages

#### 6.1 Create Opportunities Pipeline Page
`src/pages/Opportunities/OpportunitiesPipeline.tsx`:
```typescript
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Plus, LayoutGrid, List } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { OpportunitiesKanban } from '@/components/features/OpportunitiesKanban';
import { OpportunitiesTable } from '@/components/features/OpportunitiesTable';
import { opportunityService } from '@/services/opportunity.service';
import { formatCurrency } from '@/lib/utils';

export function OpportunitiesPipelinePage() {
  const [view, setView] = useState<'pipeline' | 'table'>('pipeline');

  const { data: opportunities, isLoading } = useQuery({
    queryKey: ['opportunities'],
    queryFn: () => opportunityService.getAll({ limit: 100 }),
  });

  const calculateMetrics = () => {
    if (!opportunities?.data) return { total: 0, weighted: 0, count: 0 };

    const total = opportunities.data.reduce(
      (sum, opp) => sum + (opp.amount || 0),
      0
    );
    const weighted = opportunities.data.reduce(
      (sum, opp) => sum + (opp.amount || 0) * (opp.probability || 0) / 100,
      0
    );

    return {
      total,
      weighted,
      count: opportunities.data.length,
    };
  };

  const metrics = calculateMetrics();

  return (
    <div className="h-full flex flex-col">
      <div className="flex items-center justify-between p-6 pb-4">
        <div>
          <h1 className="text-2xl font-semibold">Opportunities Pipeline</h1>
          <div className="mt-1 flex gap-4 text-sm text-muted-foreground">
            <span>Total: {formatCurrency(metrics.total)}</span>
            <span>Weighted: {formatCurrency(metrics.weighted)}</span>
            <span>Count: {metrics.count}</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => setView(view === 'pipeline' ? 'table' : 'pipeline')}>
            {view === 'pipeline' ? <List className="h-4 w-4" /> : <LayoutGrid className="h-4 w-4" />}
          </Button>
          <Button asChild>
            <Link to="/opportunities/new">
              <Plus className="mr-2 h-4 w-4" />
              New Opportunity
            </Link>
          </Button>
        </div>
      </div>

      <div className="flex-1 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-full">
            <p>Loading opportunities...</p>
          </div>
        ) : (
          <>
            {view === 'pipeline' ? (
              <OpportunitiesKanban opportunities={opportunities?.data || []} />
            ) : (
              <div className="p-6 pt-0">
                <OpportunitiesTable opportunities={opportunities?.data || []} />
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
```

#### 6.2 Create Opportunity Form Page
`src/pages/Opportunities/OpportunityForm.tsx`:
```typescript
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { useToast } from '@/components/ui/use-toast';
import { opportunityService } from '@/services/opportunity.service';
import { accountService } from '@/services/account.service';
import type { OpportunityStage } from '@/types';

const opportunitySchema = z.object({
  name: z.string().min(1, 'Opportunity name is required'),
  account_id: z.string().min(1, 'Account is required'),
  sales_stage: z.string(),
  amount: z.number().min(0, 'Amount must be positive'),
  probability: z.number().min(0).max(100),
  date_closed: z.date(),
  lead_source: z.string().optional(),
  next_step: z.string().optional(),
  description: z.string().optional(),
});

type OpportunityFormData = z.infer<typeof opportunitySchema>;

const stages: OpportunityStage[] = [
  'Qualification',
  'Needs Analysis',
  'Value Proposition',
  'Decision Makers',
  'Proposal',
  'Negotiation',
  'Closed Won',
  'Closed Lost',
];

const stageProbabilities: Record<OpportunityStage, number> = {
  'Qualification': 10,
  'Needs Analysis': 20,
  'Value Proposition': 40,
  'Decision Makers': 60,
  'Proposal': 75,
  'Negotiation': 90,
  'Closed Won': 100,
  'Closed Lost': 0,
};

export function OpportunityFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const isEdit = Boolean(id);

  const { data: opportunity, isLoading: isLoadingOpportunity } = useQuery({
    queryKey: ['opportunity', id],
    queryFn: () => opportunityService.getById(id!),
    enabled: isEdit,
  });

  const { data: accounts } = useQuery({
    queryKey: ['accounts'],
    queryFn: () => accountService.getAll({ limit: 100 }),
  });

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<OpportunityFormData>({
    resolver: zodResolver(opportunitySchema),
    defaultValues: opportunity || {
      sales_stage: 'Qualification',
      probability: 10,
      date_closed: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // 30 days from now
    },
  });

  const selectedStage = watch('sales_stage');

  // Update probability when stage changes
  React.useEffect(() => {
    if (selectedStage && stageProbabilities[selectedStage as OpportunityStage] !== undefined) {
      setValue('probability', stageProbabilities[selectedStage as OpportunityStage]);
    }
  }, [selectedStage, setValue]);

  const createMutation = useMutation({
    mutationFn: opportunityService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] });
      toast({
        title: 'Opportunity created',
        description: 'The opportunity has been created successfully.',
      });
      navigate('/opportunities');
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<OpportunityFormData> }) =>
      opportunityService.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] });
      queryClient.invalidateQueries({ queryKey: ['opportunity', id] });
      toast({
        title: 'Opportunity updated',
        description: 'The opportunity has been updated successfully.',
      });
      navigate('/opportunities');
    },
  });

  const onSubmit = async (data: OpportunityFormData) => {
    const formattedData = {
      ...data,
      date_closed: data.date_closed.toISOString().split('T')[0],
    };

    if (isEdit && id) {
      updateMutation.mutate({ id, data: formattedData });
    } else {
      createMutation.mutate(formattedData);
    }
  };

  if (isLoadingOpportunity) {
    return <div>Loading...</div>;
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate('/opportunities')}
          className="mr-4"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <h1 className="text-2xl font-semibold">
          {isEdit ? 'Edit Opportunity' : 'New Opportunity'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Opportunity Name</Label>
          <Input
            id="name"
            {...register('name')}
            disabled={isSubmitting}
          />
          {errors.name && (
            <p className="text-sm text-destructive">{errors.name.message}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="account_id">Account</Label>
          <Select
            onValueChange={(value) => setValue('account_id', value)}
            defaultValue={opportunity?.account_id}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select an account" />
            </SelectTrigger>
            <SelectContent>
              {accounts?.data.map((account) => (
                <SelectItem key={account.id} value={account.id}>
                  {account.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.account_id && (
            <p className="text-sm text-destructive">{errors.account_id.message}</p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="sales_stage">Sales Stage</Label>
            <Select
              onValueChange={(value) => setValue('sales_stage', value)}
              defaultValue={opportunity?.sales_stage || 'Qualification'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {stages.map((stage) => (
                  <SelectItem key={stage} value={stage}>
                    {stage}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="probability">Probability (%)</Label>
            <Input
              id="probability"
              type="number"
              min="0"
              max="100"
              {...register('probability', { valueAsNumber: true })}
              disabled={isSubmitting}
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="amount">Amount ($)</Label>
            <Input
              id="amount"
              type="number"
              min="0"
              step="0.01"
              {...register('amount', { valueAsNumber: true })}
              disabled={isSubmitting}
            />
            {errors.amount && (
              <p className="text-sm text-destructive">{errors.amount.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="date_closed">Expected Close Date</Label>
            <DatePicker
              date={watch('date_closed')}
              onDateChange={(date) => setValue('date_closed', date || new Date())}
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="lead_source">Lead Source</Label>
          <Select
            onValueChange={(value) => setValue('lead_source', value)}
            defaultValue={opportunity?.lead_source}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select lead source" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Website">Website</SelectItem>
              <SelectItem value="Referral">Referral</SelectItem>
              <SelectItem value="Partner">Partner</SelectItem>
              <SelectItem value="Direct">Direct Sales</SelectItem>
              <SelectItem value="Marketing">Marketing Campaign</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label htmlFor="next_step">Next Step</Label>
          <Input
            id="next_step"
            {...register('next_step')}
            disabled={isSubmitting}
            placeholder="e.g., Schedule demo, Send proposal"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            rows={4}
            {...register('description')}
            disabled={isSubmitting}
          />
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Opportunity' : 'Create Opportunity'}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/opportunities')}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
        </div>
      </form>
    </div>
  );
}
```

### 7. Activities Implementation

#### 7.1 Create Activities List Page
`src/pages/Activities/ActivitiesList.tsx`:
```typescript
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Phone, Calendar, CheckSquare, FileText, Plus } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { activityService, callService, meetingService, taskService, noteService } from '@/services/activity.service';
import { formatDate } from '@/lib/utils';

export function ActivitiesListPage() {
  const [activeTab, setActiveTab] = useState('all');

  const { data: upcomingActivities } = useQuery({
    queryKey: ['activities', 'upcoming'],
    queryFn: () => activityService.getUpcoming(20),
  });

  const { data: overdueTasks } = useQuery({
    queryKey: ['tasks', 'overdue'],
    queryFn: activityService.getOverdueTasks,
  });

  const { data: recentCalls } = useQuery({
    queryKey: ['calls', 'recent'],
    queryFn: () => callService.getAll({ limit: 10 }),
  });

  const { data: recentMeetings } = useQuery({
    queryKey: ['meetings', 'recent'],
    queryFn: () => meetingService.getAll({ limit: 10 }),
  });

  const activityTypes = [
    { id: 'call', label: 'Call', icon: Phone, color: 'text-blue-600', path: '/activities/calls/new' },
    { id: 'meeting', label: 'Meeting', icon: Calendar, color: 'text-green-600', path: '/activities/meetings/new' },
    { id: 'task', label: 'Task', icon: CheckSquare, color: 'text-purple-600', path: '/activities/tasks/new' },
    { id: 'note', label: 'Note', icon: FileText, color: 'text-gray-600', path: '/activities/notes/new' },
  ];

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Activities</h1>
        <div className="flex gap-2">
          {activityTypes.map((type) => {
            const Icon = type.icon;
            return (
              <Button key={type.id} variant="outline" size="sm" asChild>
                <Link to={type.path}>
                  <Icon className={`mr-1 h-4 w-4 ${type.color}`} />
                  {type.label}
                </Link>
              </Button>
            );
          })}
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Overdue Tasks</CardTitle>
          </CardHeader>
          <CardContent>
            {overdueTasks?.data.length === 0 ? (
              <p className="text-sm text-muted-foreground">No overdue tasks</p>
            ) : (
              <div className="space-y-2">
                {overdueTasks?.data.slice(0, 5).map((task) => (
                  <div key={task.id} className="flex items-center justify-between">
                    <Link
                      to={`/activities/tasks/${task.id}`}
                      className="text-sm hover:underline line-clamp-1"
                    >
                      {task.name}
                    </Link>
                    <span className="text-xs text-red-600">
                      {formatDate(task.date_due!)}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Today's Activities</CardTitle>
          </CardHeader>
          <CardContent>
            {upcomingActivities?.filter(a => {
              const activityDate = new Date(a.date_start || a.date_due || '');
              const today = new Date();
              return activityDate.toDateString() === today.toDateString();
            }).length === 0 ? (
              <p className="text-sm text-muted-foreground">No activities scheduled for today</p>
            ) : (
              <div className="space-y-2">
                {upcomingActivities?.filter(a => {
                  const activityDate = new Date(a.date_start || a.date_due || '');
                  const today = new Date();
                  return activityDate.toDateString() === today.toDateString();
                }).map((activity) => {
                  const Icon = activity.type === 'Call' ? Phone :
                    activity.type === 'Meeting' ? Calendar :
                    activity.type === 'Task' ? CheckSquare : FileText;
                  
                  return (
                    <div key={activity.id} className="flex items-center gap-2">
                      <Icon className="h-4 w-4 text-muted-foreground" />
                      <Link
                        to={`/activities/${activity.type.toLowerCase()}s/${activity.id}`}
                        className="text-sm hover:underline line-clamp-1 flex-1"
                      >
                        {activity.name}
                      </Link>
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Upcoming Activities</CardTitle>
          </CardHeader>
          <CardContent>
            {upcomingActivities?.length === 0 ? (
              <p className="text-sm text-muted-foreground">No upcoming activities</p>
            ) : (
              <div className="space-y-2">
                {upcomingActivities?.slice(0, 5).map((activity) => {
                  const Icon = activity.type === 'Call' ? Phone :
                    activity.type === 'Meeting' ? Calendar :
                    activity.type === 'Task' ? CheckSquare : FileText;
                  
                  return (
                    <div key={activity.id} className="flex items-center gap-2">
                      <Icon className="h-4 w-4 text-muted-foreground" />
                      <Link
                        to={`/activities/${activity.type.toLowerCase()}s/${activity.id}`}
                        className="text-sm hover:underline line-clamp-1 flex-1"
                      >
                        {activity.name}
                      </Link>
                      <span className="text-xs text-muted-foreground">
                        {formatDate(activity.date_start || activity.date_due || '')}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="mt-6">
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="all">All Activities</TabsTrigger>
            <TabsTrigger value="calls">Calls</TabsTrigger>
            <TabsTrigger value="meetings">Meetings</TabsTrigger>
            <TabsTrigger value="tasks">Tasks</TabsTrigger>
            <TabsTrigger value="notes">Notes</TabsTrigger>
          </TabsList>

          <TabsContent value="all" className="mt-4">
            <ActivityTimeline activities={upcomingActivities || []} />
          </TabsContent>

          <TabsContent value="calls" className="mt-4">
            <CallsList calls={recentCalls?.data || []} />
          </TabsContent>

          <TabsContent value="meetings" className="mt-4">
            <MeetingsList meetings={recentMeetings?.data || []} />
          </TabsContent>

          <TabsContent value="tasks" className="mt-4">
            <TasksList />
          </TabsContent>

          <TabsContent value="notes" className="mt-4">
            <NotesList />
          </TabsContent>
        </Tabs>
      </div>
    </div>
  );
}
```

### 8. Cases (Support Tickets) Implementation

#### 8.1 Create Cases List Page
`src/pages/Cases/CasesList.tsx`:
```typescript
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Plus, Search, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { PriorityBadge } from '@/components/ui/priority-badge';
import { caseService } from '@/services/case.service';
import { formatDate } from '@/lib/utils';

export function CasesListPage() {
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');

  const { data, isLoading } = useQuery({
    queryKey: ['cases', statusFilter, searchTerm],
    queryFn: () =>
      caseService.getAll({
        status: statusFilter || undefined,
        filter: searchTerm,
      }),
  });

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      'New': 'bg-blue-100 text-blue-800',
      'Assigned': 'bg-yellow-100 text-yellow-800',
      'In Progress': 'bg-purple-100 text-purple-800',
      'Pending Input': 'bg-orange-100 text-orange-800',
      'Closed': 'bg-gray-100 text-gray-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  const criticalCases = data?.data.filter(c => c.priority === 'P1') || [];

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Support Cases</h1>
          {criticalCases.length > 0 && (
            <div className="mt-1 flex items-center gap-2 text-sm text-red-600">
              <AlertCircle className="h-4 w-4" />
              <span>{criticalCases.length} critical cases require attention</span>
            </div>
          )}
        </div>
        <Button asChild>
          <Link to="/cases/new">
            <Plus className="mr-2 h-4 w-4" />
            New Case
          </Link>
        </Button>
      </div>

      <div className="mb-4 flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search cases..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Select
          value={statusFilter}
          onValueChange={setStatusFilter}
        >
          <SelectTrigger className="w-[180px]">
            <SelectValue placeholder="All statuses" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">All statuses</SelectItem>
            <SelectItem value="New">New</SelectItem>
            <SelectItem value="Assigned">Assigned</SelectItem>
            <SelectItem value="In Progress">In Progress</SelectItem>
            <SelectItem value="Pending Input">Pending Input</SelectItem>
            <SelectItem value="Closed">Closed</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading && <div>Loading cases...</div>}

      {data && (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Case #</TableHead>
                <TableHead>Subject</TableHead>
                <TableHead>Account</TableHead>
                <TableHead>Priority</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Assigned To</TableHead>
                <TableHead>Created</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.data.map((caseItem) => (
                <TableRow key={caseItem.id}>
                  <TableCell className="font-medium">
                    {caseItem.case_number}
                  </TableCell>
                  <TableCell>{caseItem.name}</TableCell>
                  <TableCell>{caseItem.account_name || '-'}</TableCell>
                  <TableCell>
                    <PriorityBadge priority={caseItem.priority} />
                  </TableCell>
                  <TableCell>
                    <Badge className={getStatusColor(caseItem.status)}>
                      {caseItem.status}
                    </Badge>
                  </TableCell>
                  <TableCell>{caseItem.assigned_user_name || '-'}</TableCell>
                  <TableCell>{formatDate(caseItem.date_entered)}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" asChild>
                      <Link to={`/cases/${caseItem.id}`}>View</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
```

### 9. Enhanced Dashboard

#### 9.1 Update Dashboard Service
`src/services/dashboard.service.ts` (update existing):
```typescript
import { api } from '@/lib/api';
import type { DashboardMetrics, PipelineStageData, ActivityMetrics, CaseMetrics } from '@/types';

export const dashboardService = {
  async getMetrics(): Promise<DashboardMetrics> {
    // Use custom endpoint from Phase 2 backend
    const response = await api.get('/dashboard/metrics');
    return response.data.data;
  },

  async getPipelineData(): Promise<PipelineStageData[]> {
    const response = await api.get('/dashboard/pipeline');
    return response.data.data;
  },

  async getActivityMetrics(): Promise<ActivityMetrics> {
    const response = await api.get('/dashboard/activities');
    return response.data.data;
  },

  async getCaseMetrics(): Promise<CaseMetrics> {
    const response = await api.get('/dashboard/cases');
    return response.data.data;
  },
};
```

#### 9.2 Update Dashboard Page with Charts
`src/pages/Dashboard.tsx` (replace existing):
```typescript
import { useQuery } from '@tanstack/react-query';
import { Users, Building2, TrendingUp, DollarSign, Phone, Calendar, CheckSquare, AlertCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';
import { dashboardService } from '@/services/dashboard.service';
import { formatCurrency } from '@/lib/utils';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

export function DashboardPage() {
  const { data: metrics, isLoading: isLoadingMetrics } = useQuery({
    queryKey: ['dashboard-metrics'],
    queryFn: dashboardService.getMetrics,
    refetchInterval: 30000,
  });

  const { data: pipelineData, isLoading: isLoadingPipeline } = useQuery({
    queryKey: ['dashboard-pipeline'],
    queryFn: dashboardService.getPipelineData,
    refetchInterval: 60000,
  });

  const { data: activityMetrics } = useQuery({
    queryKey: ['dashboard-activities'],
    queryFn: dashboardService.getActivityMetrics,
    refetchInterval: 30000,
  });

  const { data: caseMetrics } = useQuery({
    queryKey: ['dashboard-cases'],
    queryFn: dashboardService.getCaseMetrics,
    refetchInterval: 30000,
  });

  const cards = [
    {
      title: 'Total Leads',
      value: metrics?.total_leads || 0,
      icon: Users,
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
    },
    {
      title: 'Total Accounts',
      value: metrics?.total_accounts || 0,
      icon: Building2,
      color: 'text-green-600',
      bgColor: 'bg-green-100',
    },
    {
      title: 'New Leads Today',
      value: metrics?.new_leads_today || 0,
      icon: TrendingUp,
      color: 'text-purple-600',
      bgColor: 'bg-purple-100',
    },
    {
      title: 'Pipeline Value',
      value: formatCurrency(metrics?.pipeline_value || 0),
      icon: DollarSign,
      color: 'text-orange-600',
      bgColor: 'bg-orange-100',
    },
  ];

  const activityCards = [
    {
      title: "Today's Calls",
      value: activityMetrics?.calls_today || 0,
      icon: Phone,
      color: 'text-blue-600',
    },
    {
      title: "Today's Meetings",
      value: activityMetrics?.meetings_today || 0,
      icon: Calendar,
      color: 'text-green-600',
    },
    {
      title: 'Overdue Tasks',
      value: activityMetrics?.tasks_overdue || 0,
      icon: CheckSquare,
      color: 'text-red-600',
    },
    {
      title: 'Open Cases',
      value: caseMetrics?.open_cases || 0,
      icon: AlertCircle,
      color: 'text-orange-600',
    },
  ];

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-semibold">Dashboard</h1>

      {/* Key Metrics */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {cards.map((card) => {
          const Icon = card.icon;
          return (
            <Card key={card.title}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                  {card.title}
                </CardTitle>
                <div className={`rounded-full p-2 ${card.bgColor}`}>
                  <Icon className={`h-4 w-4 ${card.color}`} />
                </div>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{card.value}</div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Activity Metrics */}
      <div className="grid gap-4 md:grid-cols-4">
        {activityCards.map((card) => {
          const Icon = card.icon;
          return (
            <Card key={card.title}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium flex items-center gap-2">
                  <Icon className={`h-4 w-4 ${card.color}`} />
                  {card.title}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{card.value}</div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Charts */}
      <div className="grid gap-6 md:grid-cols-2">
        {/* Pipeline Chart */}
        <Card>
          <CardHeader>
            <CardTitle>Sales Pipeline</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoadingPipeline ? (
              <div className="h-[300px] flex items-center justify-center">
                <p className="text-muted-foreground">Loading pipeline data...</p>
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={pipelineData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="stage" angle={-45} textAnchor="end" height={80} />
                  <YAxis />
                  <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                  <Bar dataKey="value" fill="#8884d8" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Cases by Priority */}
        <Card>
          <CardHeader>
            <CardTitle>Cases by Priority</CardTitle>
          </CardHeader>
          <CardContent>
            {caseMetrics?.cases_by_priority ? (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={caseMetrics.cases_by_priority}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ priority, count }) => `${priority}: ${count}`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="count"
                  >
                    {caseMetrics.cases_by_priority.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[300px] flex items-center justify-center">
                <p className="text-muted-foreground">No case data available</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Activity</CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs defaultValue="all">
            <TabsList>
              <TabsTrigger value="all">All</TabsTrigger>
              <TabsTrigger value="leads">Leads</TabsTrigger>
              <TabsTrigger value="opportunities">Opportunities</TabsTrigger>
              <TabsTrigger value="cases">Cases</TabsTrigger>
            </TabsList>
            <TabsContent value="all" className="mt-4">
              {activityMetrics?.upcoming_activities?.slice(0, 5).map((activity) => (
                <div key={activity.id} className="flex items-center justify-between py-2 border-b last:border-0">
                  <div>
                    <p className="font-medium">{activity.name}</p>
                    <p className="text-sm text-muted-foreground">
                      {activity.parent_name} • {activity.assigned_user_name}
                    </p>
                  </div>
                  <Badge variant="outline">{activity.status}</Badge>
                </div>
              ))}
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  );
}
```

### 10. Role-Based Access Control

#### 10.1 Create Permissions Hook
`src/hooks/usePermissions.ts`:
```typescript
import { useAuthStore } from '@/store/auth';

type Module = 'Leads' | 'Accounts' | 'Opportunities' | 'Cases' | 'Activities';
type Action = 'view' | 'create' | 'edit' | 'delete';

// Define role permissions
const rolePermissions: Record<string, Record<Module, Action[]>> = {
  admin: {
    Leads: ['view', 'create', 'edit', 'delete'],
    Accounts: ['view', 'create', 'edit', 'delete'],
    Opportunities: ['view', 'create', 'edit', 'delete'],
    Cases: ['view', 'create', 'edit', 'delete'],
    Activities: ['view', 'create', 'edit', 'delete'],
  },
  sales_rep: {
    Leads: ['view', 'create', 'edit'],
    Accounts: ['view', 'create', 'edit'],
    Opportunities: ['view', 'create', 'edit'],
    Cases: ['view'],
    Activities: ['view', 'create', 'edit'],
  },
  customer_success: {
    Leads: ['view'],
    Accounts: ['view', 'edit'],
    Opportunities: ['view'],
    Cases: ['view', 'create', 'edit'],
    Activities: ['view', 'create', 'edit'],
  },
};

export function usePermissions() {
  const user = useAuthStore((state) => state.user);
  const userRole = user?.role || 'sales_rep';

  const hasPermission = (module: Module, action: Action): boolean => {
    const permissions = rolePermissions[userRole];
    if (!permissions) return false;
    
    const modulePermissions = permissions[module];
    if (!modulePermissions) return false;
    
    return modulePermissions.includes(action);
  };

  const canView = (module: Module) => hasPermission(module, 'view');
  const canCreate = (module: Module) => hasPermission(module, 'create');
  const canEdit = (module: Module) => hasPermission(module, 'edit');
  const canDelete = (module: Module) => hasPermission(module, 'delete');

  return {
    hasPermission,
    canView,
    canCreate,
    canEdit,
    canDelete,
    userRole,
  };
}
```

#### 10.2 Create Permission Guard Component
`src/components/auth/PermissionGuard.tsx`:
```typescript
import { ReactNode } from 'react';
import { usePermissions } from '@/hooks/usePermissions';

interface PermissionGuardProps {
  module: 'Leads' | 'Accounts' | 'Opportunities' | 'Cases' | 'Activities';
  action: 'view' | 'create' | 'edit' | 'delete';
  children: ReactNode;
  fallback?: ReactNode;
}

export function PermissionGuard({ module, action, children, fallback = null }: PermissionGuardProps) {
  const { hasPermission } = usePermissions();

  if (!hasPermission(module, action)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}
```

### 11. Update App Router

#### 11.1 Update Routes with New Pages
`src/App.tsx` (update existing):
```typescript
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { Toaster } from '@/components/ui/toaster';
import { queryClient } from '@/lib/query-client';
import { AppLayout } from '@/components/layout/AppLayout';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { LoginPage } from '@/pages/Login';
import { DashboardPage } from '@/pages/Dashboard';

// Leads
import { LeadsListPage } from '@/pages/Leads/LeadsList';
import { LeadFormPage } from '@/pages/Leads/LeadForm';

// Accounts
import { AccountsListPage } from '@/pages/Accounts/AccountsList';
import { AccountFormPage } from '@/pages/Accounts/AccountForm';

// Opportunities
import { OpportunitiesPipelinePage } from '@/pages/Opportunities/OpportunitiesPipeline';
import { OpportunityFormPage } from '@/pages/Opportunities/OpportunityForm';

// Activities
import { ActivitiesListPage } from '@/pages/Activities/ActivitiesList';
import { CallFormPage } from '@/pages/Activities/CallForm';
import { MeetingFormPage } from '@/pages/Activities/MeetingForm';
import { TaskFormPage } from '@/pages/Activities/TaskForm';
import { NoteFormPage } from '@/pages/Activities/NoteForm';

// Cases
import { CasesListPage } from '@/pages/Cases/CasesList';
import { CaseFormPage } from '@/pages/Cases/CaseForm';
import { CaseDetailPage } from '@/pages/Cases/CaseDetail';

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<DashboardPage />} />
            
            {/* Leads Routes */}
            <Route path="leads" element={<LeadsListPage />} />
            <Route path="leads/new" element={<LeadFormPage />} />
            <Route path="leads/:id" element={<LeadFormPage />} />
            
            {/* Accounts Routes */}
            <Route path="accounts" element={<AccountsListPage />} />
            <Route path="accounts/new" element={<AccountFormPage />} />
            <Route path="accounts/:id" element={<AccountFormPage />} />
            
            {/* Opportunities Routes */}
            <Route path="opportunities" element={<OpportunitiesPipelinePage />} />
            <Route path="opportunities/new" element={<OpportunityFormPage />} />
            <Route path="opportunities/:id" element={<OpportunityFormPage />} />
            
            {/* Activities Routes */}
            <Route path="activities" element={<ActivitiesListPage />} />
            <Route path="activities/calls/new" element={<CallFormPage />} />
            <Route path="activities/calls/:id" element={<CallFormPage />} />
            <Route path="activities/meetings/new" element={<MeetingFormPage />} />
            <Route path="activities/meetings/:id" element={<MeetingFormPage />} />
            <Route path="activities/tasks/new" element={<TaskFormPage />} />
            <Route path="activities/tasks/:id" element={<TaskFormPage />} />
            <Route path="activities/notes/new" element={<NoteFormPage />} />
            <Route path="activities/notes/:id" element={<NoteFormPage />} />
            
            {/* Cases Routes */}
            <Route path="cases" element={<CasesListPage />} />
            <Route path="cases/new" element={<CaseFormPage />} />
            <Route path="cases/:id" element={<CaseDetailPage />} />
          </Route>
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
      <Toaster />
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}
```

#### 11.2 Update Navigation
`src/components/layout/AppLayout.tsx` (update navigation array):
```typescript
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'Leads', href: '/leads', icon: Users },
  { name: 'Accounts', href: '/accounts', icon: Building2 },
  { name: 'Opportunities', href: '/opportunities', icon: TrendingDollar },
  { name: 'Activities', href: '/activities', icon: Calendar },
  { name: 'Cases', href: '/cases', icon: Headphones },
];
```

## Testing Setup

### Frontend Integration Tests
`tests/frontend/integration/opportunities.test.tsx`:
```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { OpportunitiesPipelinePage } from '@/pages/Opportunities/OpportunitiesPipeline';
import { opportunityService } from '@/services/opportunity.service';

vi.mock('@/services/opportunity.service');

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
  },
});

const mockOpportunities = [
  {
    id: '1',
    name: 'Test Opportunity',
    account_id: '1',
    account_name: 'Test Account',
    sales_stage: 'Qualification',
    amount: 50000,
    probability: 20,
    date_closed: '2024-03-01',
  },
  {
    id: '2',
    name: 'Another Opportunity',
    account_id: '2',
    account_name: 'Another Account',
    sales_stage: 'Proposal',
    amount: 75000,
    probability: 75,
    date_closed: '2024-02-15',
  },
];

const renderWithProviders = (component: React.ReactNode) => {
  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{component}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe('Opportunities Pipeline', () => {
  beforeEach(() => {
    (opportunityService.getAll as jest.Mock).mockResolvedValue({
      data: mockOpportunities,
      meta: { total: 2 },
    });
  });

  it('renders pipeline view with opportunities', async () => {
    renderWithProviders(<OpportunitiesPipelinePage />);
    
    await waitFor(() => {
      expect(screen.getByText('Test Opportunity')).toBeInTheDocument();
      expect(screen.getByText('Another Opportunity')).toBeInTheDocument();
    });

    // Check stages are rendered
    expect(screen.getByText('Qualification')).toBeInTheDocument();
    expect(screen.getByText('Proposal')).toBeInTheDocument();
  });

  it('switches between pipeline and table view', async () => {
    renderWithProviders(<OpportunitiesPipelinePage />);
    
    await waitFor(() => {
      expect(screen.getByText('Test Opportunity')).toBeInTheDocument();
    });

    // Switch to table view
    const viewToggle = screen.getByRole('button', { name: /list/i });
    fireEvent.click(viewToggle);

    // Table headers should appear
    await waitFor(() => {
      expect(screen.getByText('Account')).toBeInTheDocument();
      expect(screen.getByText('Stage')).toBeInTheDocument();
      expect(screen.getByText('Amount')).toBeInTheDocument();
    });
  });

  it('shows metrics in header', async () => {
    renderWithProviders(<OpportunitiesPipelinePage />);
    
    await waitFor(() => {
      expect(screen.getByText(/Total: \$125,000/)).toBeInTheDocument();
      expect(screen.getByText(/Count: 2/)).toBeInTheDocument();
    });
  });
});
```

### Activity Creation Test
`tests/frontend/components/ActivityForm.test.tsx`:
```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { TaskFormPage } from '@/pages/Activities/TaskForm';
import { taskService } from '@/services/activity.service';

vi.mock('@/services/activity.service');

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
  },
});

describe('Task Form', () => {
  it('creates a new task', async () => {
    const mockCreate = vi.fn().mockResolvedValue({
      id: '123',
      name: 'Test Task',
      priority: 'High',
      status: 'Not Started',
    });
    
    (taskService.create as jest.Mock) = mockCreate;

    render(
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <TaskFormPage />
        </BrowserRouter>
      </QueryClientProvider>
    );

    // Fill form
    fireEvent.change(screen.getByLabelText('Task Name'), {
      target: { value: 'Test Task' },
    });

    fireEvent.change(screen.getByLabelText('Priority'), {
      target: { value: 'High' },
    });

    // Submit form
    fireEvent.click(screen.getByText('Create Task'));

    await waitFor(() => {
      expect(mockCreate).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'Test Task',
          priority: 'High',
        })
      );
    });
  });
});
```

## Definition of Success

### ✅ Phase 2 Frontend Success Criteria:

1. **Opportunities Pipeline**
   - [ ] Kanban board displays opportunities by stage
   - [ ] Drag-and-drop updates opportunity stage
   - [ ] Pipeline/Table view toggle works
   - [ ] Metrics show total and weighted values
   - [ ] Create/edit opportunity forms work
   - [ ] Probability auto-updates based on stage

2. **Activities Management**
   - [ ] Activities dashboard shows today's activities and overdue tasks
   - [ ] Create forms for Calls, Meetings, Tasks, Notes
   - [ ] Activities linked to parent records (Leads, Accounts, etc.)
   - [ ] Activity timeline displays chronologically
   - [ ] Quick add buttons work for each activity type

3. **Cases (Support Tickets)**
   - [ ] Cases list with priority indicators
   - [ ] Critical cases alert displayed
   - [ ] Create/edit case forms work
   - [ ] Status and priority filters functional
   - [ ] Cases linked to accounts/contacts

4. **Enhanced Dashboard**
   - [ ] Pipeline chart shows opportunities by stage
   - [ ] Activity metrics display correctly
   - [ ] Cases by priority pie chart renders
   - [ ] Recent activity feed updates
   - [ ] All charts responsive and interactive

5. **Role-Based Access**
   - [ ] Permission guards hide unauthorized actions
   - [ ] Navigation shows only permitted modules
   - [ ] Create buttons hidden for restricted users
   - [ ] Edit/Delete actions respect permissions

6. **Testing**
   - [ ] Opportunities drag-drop test passes
   - [ ] Activity creation tests pass
   - [ ] Permission tests verify access control
   - [ ] Dashboard chart rendering tests pass

### Manual Verification Steps:
1. Start dev server: `npm run dev`
2. Log in and navigate to Opportunities
3. Create a new opportunity and verify it appears in pipeline
4. Drag opportunity to different stage
5. Switch to table view and verify data
6. Create activities (Call, Meeting, Task, Note)
7. Verify activities appear in timeline
8. Create a support case with P1 priority
9. Verify critical case alert on Cases page
10. Check dashboard charts load with data
11. Test with different user roles (if implemented in backend)

### Integration Points:
- Opportunities CRUD via v8 API
- Activities modules (Calls, Meetings, Tasks, Notes)
- Cases module configuration
- Custom dashboard endpoints
- Drag-drop updates opportunity stage
- Role permissions from user object

### Next Phase Preview:
Phase 3 will add:
- AI lead scoring integration
- Form builder with drag-drop
- Knowledge base creation and management
- Basic AI chatbot
- Website activity tracking