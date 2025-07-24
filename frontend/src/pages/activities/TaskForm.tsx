import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ArrowLeft, Loader2 } from 'lucide-react'
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
import { toast } from 'sonner'
import { useTask, useCreateTask, useUpdateTask } from '@/hooks/use-activities'
import { useAccounts } from '@/hooks/use-accounts'
import { useContacts } from '@/hooks/use-contacts'
import { DatePicker } from '@/components/ui/date-picker'

const taskSchema = z.object({
  name: z.string().min(1, 'Task name is required'),
  status: z.string(),
  priority: z.enum(['High', 'Medium', 'Low']),
  dueDate: z.date().optional(),
  startDate: z.date().optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  description: z.string().optional(),
})

type TaskFormData = z.infer<typeof taskSchema>

export function TaskForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: task, isLoading: isLoadingTask } = useTask(id || '')
  const { data: accounts } = useAccounts()
  const { data: contacts } = useContacts()
  
  const createTask = useCreateTask()
  const updateTask = useUpdateTask(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<TaskFormData>({
    resolver: zodResolver(taskSchema),
    defaultValues: task?.data || {
      status: 'Not Started',
      priority: 'Medium',
    },
  })

  const parentType = watch('parentType')

  const onSubmit = async (data: TaskFormData) => {
    try {
      const formattedData = {
        name: data.name,
        status: data.status as 'Not Started' | 'In Progress' | 'Completed' | 'Pending Input' | 'Deferred',
        priority: data.priority,
        dueDate: data.dueDate?.toISOString(),
        startDate: data.startDate?.toISOString(),
        parentType: data.parentType,
        parentId: data.parentId,
        description: data.description,
      }

      if (isEdit && id) {
        await updateTask.mutateAsync({ ...formattedData, dateEntered: '', dateModified: '' })
      } else {
        await createTask.mutateAsync({ ...formattedData, dateEntered: '', dateModified: '' })
      }
      
      navigate('/activities')
    } catch {
      toast.error('Failed to save task. Please try again.')
    }
  }

  if (isLoadingTask) {
    return <div className="p-6">Loading...</div>
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate('/activities')}
          className="mr-4"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <h1 className="text-2xl font-semibold">
          {isEdit ? 'Edit Task' : 'Create Task'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Task Name *</Label>
          <Input
            id="name"
            {...register('name')}
            placeholder="Follow up with customer"
            disabled={isSubmitting}
          />
          {errors.name && (
            <p className="text-sm text-destructive">{errors.name.message}</p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="status">Status</Label>
            <Select
              onValueChange={(value) => setValue('status', value)}
              defaultValue={task?.data?.status || 'Not Started'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Not Started">Not Started</SelectItem>
                <SelectItem value="In Progress">In Progress</SelectItem>
                <SelectItem value="Completed">Completed</SelectItem>
                <SelectItem value="Pending Input">Pending Input</SelectItem>
                <SelectItem value="Deferred">Deferred</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="priority">Priority</Label>
            <Select
              onValueChange={(value) => setValue('priority', value as 'High' | 'Medium' | 'Low')}
              defaultValue={task?.data?.priority || 'Medium'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="High">High</SelectItem>
                <SelectItem value="Medium">Medium</SelectItem>
                <SelectItem value="Low">Low</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="startDate">Start Date</Label>
            <DatePicker
              date={watch('startDate')}
              onDateChange={(date) => setValue('startDate', date)}
              placeholder="Select start date"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="dueDate">Due Date</Label>
            <DatePicker
              date={watch('dueDate')}
              onDateChange={(date) => setValue('dueDate', date)}
              placeholder="Select due date"
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="parentType">Related To</Label>
            <Select
              onValueChange={(value) => {
                setValue('parentType', value)
                setValue('parentId', '')
              }}
              defaultValue={task?.data?.parentType}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Accounts">Account</SelectItem>
                <SelectItem value="Contacts">Contact</SelectItem>
                <SelectItem value="Leads">Lead</SelectItem>
                <SelectItem value="Opportunities">Opportunity</SelectItem>
                <SelectItem value="Cases">Case</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {parentType && (
            <div className="space-y-2">
              <Label htmlFor="parentId">Select {parentType.slice(0, -1)}</Label>
              <Select
                onValueChange={(value) => setValue('parentId', value)}
                defaultValue={task?.data?.parentId}
              >
                <SelectTrigger>
                  <SelectValue placeholder={`Select ${parentType.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                  {parentType === 'Accounts' && accounts?.data.map((account) => (
                    <SelectItem key={account.id} value={account.id || ''}>
                      {account.name}
                    </SelectItem>
                  ))}
                  {parentType === 'Contacts' && contacts?.data.map((contact) => (
                    <SelectItem key={contact.id} value={contact.id || ''}>
                      {contact.firstName} {contact.lastName}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            rows={4}
            {...register('description')}
            disabled={isSubmitting}
            placeholder="Task details..."
          />
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Task' : 'Create Task'}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/activities')}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
        </div>
      </form>
    </div>
  )
}