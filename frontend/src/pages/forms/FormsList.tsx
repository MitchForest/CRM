import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, FileText, BarChart3, Trash2, Copy, ExternalLink, Code } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Badge } from '@/components/ui/badge';
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogHeader, 
  DialogTitle 
} from '@/components/ui/dialog';
import { 
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useToast } from '@/components/ui/use-toast';
import { useNavigate } from 'react-router-dom';
import { formBuilderService } from '@/services/formBuilder.service';
import { formatDistanceToNow } from 'date-fns';
import type { Form } from '@/types/phase3.types';

export function FormsList() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [selectedForm, setSelectedForm] = useState<Form | null>(null);
  const [showEmbedDialog, setShowEmbedDialog] = useState(false);
  const [deleteFormId, setDeleteFormId] = useState<string | null>(null);

  // Fetch forms
  const { data: formsResponse, isLoading } = useQuery({
    queryKey: ['forms'],
    queryFn: () => formBuilderService.getAllForms({ limit: 50 })
  });

  const forms = formsResponse?.data || [];

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (formId: string) => formBuilderService.deleteForm(formId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['forms'] });
      toast({
        title: 'Form deleted',
        description: 'The form has been deleted successfully.'
      });
      setDeleteFormId(null);
    },
    onError: () => {
      toast({
        title: 'Delete failed',
        description: 'Unable to delete the form. Please try again.',
        variant: 'destructive'
      });
    }
  });

  // Generate embed code mutation
  const embedMutation = useMutation({
    mutationFn: (formId: string) => formBuilderService.generateEmbedCode(formId),
    onSuccess: (embedCode) => {
      if (selectedForm) {
        setSelectedForm({ ...selectedForm, embed_code: embedCode });
      }
    }
  });

  const columns = [
    {
      accessorKey: 'name',
      header: 'Form Name',
      cell: ({ row }: any) => (
        <div className="flex items-center gap-2">
          <FileText className="h-4 w-4 text-muted-foreground" />
          <span className="font-medium">{row.original.name}</span>
        </div>
      )
    },
    {
      accessorKey: 'fields',
      header: 'Fields',
      cell: ({ row }: any) => (
        <Badge variant="secondary">
          {row.original.fields.length} fields
        </Badge>
      )
    },
    {
      accessorKey: 'submissions_count',
      header: 'Submissions',
      cell: ({ row }: any) => (
        <div className="flex items-center gap-2">
          <BarChart3 className="h-4 w-4 text-muted-foreground" />
          <span>{row.original.submissions_count || 0}</span>
        </div>
      )
    },
    {
      accessorKey: 'date_created',
      header: 'Created',
      cell: ({ row }: any) => (
        <span className="text-sm text-muted-foreground">
          {formatDistanceToNow(new Date(row.original.date_created), { addSuffix: true })}
        </span>
      )
    },
    {
      id: 'actions',
      cell: ({ row }: any) => (
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => navigate(`/forms/${row.original.id}`)}
          >
            Edit
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => {
              setSelectedForm(row.original);
              setShowEmbedDialog(true);
              if (!row.original.embed_code) {
                embedMutation.mutate(row.original.id);
              }
            }}
          >
            <Code className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => window.open(`/forms/preview/${row.original.id}`, '_blank')}
          >
            <ExternalLink className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => setDeleteFormId(row.original.id)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      )
    }
  ];

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast({
      title: 'Copied!',
      description: 'Embed code copied to clipboard.'
    });
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Forms</h1>
          <p className="text-muted-foreground mt-1">
            Create and manage dynamic forms for lead capture
          </p>
        </div>
        <Button onClick={() => navigate('/forms/new')}>
          <Plus className="mr-2 h-4 w-4" />
          Create Form
        </Button>
      </div>

      {/* Metrics */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Forms</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{forms.length}</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Submissions</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {forms.reduce((sum, form) => sum + (form.submissions_count || 0), 0)}
            </div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Avg. Fields/Form</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {forms.length > 0 
                ? Math.round(forms.reduce((sum, form) => sum + form.fields.length, 0) / forms.length)
                : 0
              }
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Forms Table */}
      <Card>
        <CardHeader>
          <CardTitle>All Forms</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center h-32">
              <div className="h-6 w-6 animate-spin rounded-full border-b-2 border-gray-900" />
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={forms}
            />
          )}
        </CardContent>
      </Card>

      {/* Embed Code Dialog */}
      <Dialog open={showEmbedDialog} onOpenChange={setShowEmbedDialog}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Embed Code</DialogTitle>
            <DialogDescription>
              Copy this code to embed the form on your website
            </DialogDescription>
          </DialogHeader>
          {selectedForm && (
            <div className="space-y-4">
              <div className="bg-muted p-4 rounded-lg">
                <pre className="text-sm overflow-x-auto">
                  <code>{selectedForm.embed_code || 'Generating embed code...'}</code>
                </pre>
              </div>
              <Button 
                onClick={() => copyToClipboard(selectedForm.embed_code || '')}
                disabled={!selectedForm.embed_code}
                className="w-full"
              >
                <Copy className="mr-2 h-4 w-4" />
                Copy to Clipboard
              </Button>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <AlertDialog open={!!deleteFormId} onOpenChange={() => setDeleteFormId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. This will permanently delete the form
              and all its submissions.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteFormId && deleteMutation.mutate(deleteFormId)}
              className="bg-destructive text-destructive-foreground"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}