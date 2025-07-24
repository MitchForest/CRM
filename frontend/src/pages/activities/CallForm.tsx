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
import { useCall, useCreateCall, useUpdateCall } from '@/hooks/use-activities'
import { useAccounts } from '@/hooks/use-accounts'
import { useContacts } from '@/hooks/use-contacts'
import { DateTimePicker } from '@/components/ui/date-time-picker'

const callSchema = z.object({
  name: z.string().min(1, 'Call subject is required'),
  status: z.string(),
  direction: z.enum(['Inbound', 'Outbound']),
  startDate: z.date(),
  durationHours: z.number().min(0).max(24),
  durationMinutes: z.number().min(0).max(59),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  description: z.string().optional(),
})

type CallFormData = z.infer<typeof callSchema>

export function CallForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: call, isLoading: isLoadingCall } = useCall(id || '')
  const { data: accounts } = useAccounts()
  const { data: contacts } = useContacts()
  
  const createCall = useCreateCall()
  const updateCall = useUpdateCall(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CallFormData>({
    resolver: zodResolver(callSchema),
    defaultValues: call?.data || {
      status: 'Planned',
      direction: 'Outbound',
      startDate: new Date(),
      durationHours: 0,
      durationMinutes: 15,
    },
  })

  const parentType = watch('parentType')

  const onSubmit = async (data: CallFormData) => {
    try {
      const formattedData = {
        name: data.name,
        status: data.status as 'Planned' | 'Held' | 'Cancelled',
        direction: data.direction,
        startDate: data.startDate.toISOString(),
        duration: data.durationHours * 60 + data.durationMinutes,
        parentType: data.parentType,
        parentId: data.parentId,
        description: data.description,
      }

      if (isEdit && id) {
        await updateCall.mutateAsync({ ...formattedData, dateEntered: '', dateModified: '' })
      } else {
        await createCall.mutateAsync({ ...formattedData, dateEntered: '', dateModified: '' })
      }
      
      navigate('/activities')
    } catch {
      toast.error('Failed to save call. Please try again.')
    }
  }

  if (isLoadingCall) {
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
          {isEdit ? 'Edit Call' : 'Log Call'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Subject *</Label>
          <Input
            id="name"
            {...register('name')}
            placeholder="Call with John Doe"
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
              defaultValue={call?.data?.status || 'Planned'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Planned">Planned</SelectItem>
                <SelectItem value="Held">Held</SelectItem>
                <SelectItem value="Cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="direction">Direction</Label>
            <Select
              onValueChange={(value) => setValue('direction', value as 'Inbound' | 'Outbound')}
              defaultValue={call?.data?.direction || 'Outbound'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Inbound">Inbound</SelectItem>
                <SelectItem value="Outbound">Outbound</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="startDate">Start Date & Time</Label>
          <DateTimePicker
            date={watch('startDate')}
            onDateChange={(date) => setValue('startDate', date || new Date())}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="durationHours">Duration (Hours)</Label>
            <Input
              id="durationHours"
              type="number"
              min="0"
              max="24"
              {...register('durationHours', { valueAsNumber: true })}
              disabled={isSubmitting}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="durationMinutes">Duration (Minutes)</Label>
            <Input
              id="durationMinutes"
              type="number"
              min="0"
              max="59"
              {...register('durationMinutes', { valueAsNumber: true })}
              disabled={isSubmitting}
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
              defaultValue={call?.data?.parentType}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Accounts">Account</SelectItem>
                <SelectItem value="Contacts">Contact</SelectItem>
                <SelectItem value="Leads">Lead</SelectItem>
                <SelectItem value="Opportunities">Opportunity</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {parentType && (
            <div className="space-y-2">
              <Label htmlFor="parentId">Select {parentType.slice(0, -1)}</Label>
              <Select
                onValueChange={(value) => setValue('parentId', value)}
                defaultValue={call?.data?.parentId}
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
          <Label htmlFor="description">Notes</Label>
          <Textarea
            id="description"
            rows={4}
            {...register('description')}
            disabled={isSubmitting}
            placeholder="Call notes..."
          />
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Call' : 'Log Call'}
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