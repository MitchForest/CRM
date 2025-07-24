import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useCase, useCreateCase, useUpdateCase } from '@/hooks/use-cases'
import { useContacts } from '@/hooks/use-contacts'
import { toast } from 'sonner'

const caseSchema = z.object({
  name: z.string().min(1, 'Subject is required'),
  status: z.string(),
  priority: z.enum(['High', 'Medium', 'Low']),
  type: z.string(),
  accountId: z.string().optional(),
  contactId: z.string().optional(),
  description: z.string().optional(),
  resolution: z.string().optional(),
})

type CaseFormData = z.infer<typeof caseSchema>

export function CaseForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: caseData, isLoading: isLoadingCase } = useCase(id || '')
  const { data: contacts } = useContacts({ pageSize: 100 })
  
  const createCase = useCreateCase()
  const updateCase = useUpdateCase(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CaseFormData>({
    resolver: zodResolver(caseSchema),
    defaultValues: {
      status: 'New',
      priority: 'Medium',
      type: 'Problem',
    },
  })

  const status = watch('status')

  // Use effect to reset form when case data is loaded
  useEffect(() => {
    if (caseData?.data && isEdit) {
      reset({
        name: caseData.data.name,
        status: caseData.data.status,
        priority: caseData.data.priority as 'High' | 'Medium' | 'Low',
        type: caseData.data.type,
        contactId: caseData.data.contactId,
        description: caseData.data.description,
        resolution: caseData.data.resolution,
      })
    }
  }, [caseData, isEdit, reset])

  const onSubmit = async (data: CaseFormData) => {
    try {
      const formattedData = {
        ...data,
        status: data.status as 'New' | 'Assigned' | 'Closed' | 'Pending Input' | 'Rejected' | 'Duplicate'
      }
      if (isEdit && id) {
        await updateCase.mutateAsync(formattedData)
      } else {
        await createCase.mutateAsync(formattedData)
      }
      
      navigate('/cases')
    } catch {
      toast.error('Failed to save case. Please try again.')
    }
  }

  if (isLoadingCase) {
    return <div className="p-6">Loading...</div>
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate('/cases')}
          className="mr-4"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <h1 className="text-2xl font-semibold">
          {isEdit ? 'Edit Case' : 'New Case'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Subject *</Label>
          <Input
            id="name"
            {...register('name')}
            placeholder="Brief description of the issue"
            disabled={isSubmitting}
          />
          {errors.name && (
            <p className="text-sm text-destructive">{errors.name.message}</p>
          )}
        </div>

        <div className="grid grid-cols-3 gap-4">
          <div className="space-y-2">
            <Label htmlFor="status">Status</Label>
            <Select
              value={watch('status') || 'New'}
              onValueChange={(value) => setValue('status', value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="New">New</SelectItem>
                <SelectItem value="Assigned">Assigned</SelectItem>
                <SelectItem value="In Progress">In Progress</SelectItem>
                <SelectItem value="Pending Input">Pending Input</SelectItem>
                <SelectItem value="Closed">Closed</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="priority">Priority</Label>
            <Select
              value={watch('priority') || 'Medium'}
              onValueChange={(value) => setValue('priority', value as 'High' | 'Medium' | 'Low')}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="High">P1 - High</SelectItem>
                <SelectItem value="Medium">P2 - Medium</SelectItem>
                <SelectItem value="Low">P3 - Low</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="type">Type</Label>
            <Select
              value={watch('type') || 'Problem'}
              onValueChange={(value) => setValue('type', value)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Problem">Problem</SelectItem>
                <SelectItem value="Feature Request">Feature Request</SelectItem>
                <SelectItem value="Question">Question</SelectItem>
                <SelectItem value="Bug">Bug</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="contactId">Contact</Label>
          <Select
            value={watch('contactId') || ''}
            onValueChange={(value) => setValue('contactId', value)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select contact" />
            </SelectTrigger>
            <SelectContent>
              {contacts?.data.map((contact) => (
                <SelectItem key={contact.id || ''} value={contact.id || ''}>
                  {contact.firstName} {contact.lastName}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            rows={6}
            {...register('description')}
            disabled={isSubmitting}
            placeholder="Detailed description of the issue..."
          />
        </div>

        {status === 'Closed' && (
          <div className="space-y-2">
            <Label htmlFor="resolution">Resolution</Label>
            <Textarea
              id="resolution"
              rows={4}
              {...register('resolution')}
              disabled={isSubmitting}
              placeholder="How was this case resolved?"
            />
          </div>
        )}

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Case' : 'Create Case'}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/cases')}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
        </div>
      </form>
    </div>
  )
}