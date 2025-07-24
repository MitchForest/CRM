import * as React from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm, type SubmitHandler } from 'react-hook-form'
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
import { useMeeting, useCreateMeeting, useUpdateMeeting } from '@/hooks/use-activities'
import { useAccounts } from '@/hooks/use-accounts'
import { useContacts } from '@/hooks/use-contacts'
import { DateTimePicker } from '@/components/ui/date-time-picker'

const meetingSchema = z.object({
  name: z.string().min(1, 'Meeting subject is required'),
  status: z.string(),
  location: z.string().optional(),
  startDate: z.date(),
  endDate: z.date().optional(),
  durationHours: z.number().min(0).max(24),
  durationMinutes: z.number().min(0).max(59),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  description: z.string().optional(),
})

type MeetingFormData = z.infer<typeof meetingSchema>

export function MeetingForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: meeting, isLoading: isLoadingMeeting } = useMeeting(id || '')
  const { data: accounts } = useAccounts()
  const { data: contacts } = useContacts()
  
  const createMeeting = useCreateMeeting()
  const updateMeeting = useUpdateMeeting(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<MeetingFormData>({
    resolver: zodResolver(meetingSchema),
    defaultValues: meeting ? {
      name: meeting.name,
      status: meeting.status,
      startDate: new Date(meeting.startDate),
      durationHours: Math.floor((meeting.duration || 0) / 60),
      durationMinutes: (meeting.duration || 0) % 60,
      location: meeting.location,
      parentType: meeting.parentType,
      parentId: meeting.parentId,
      description: meeting.description,
    } : {
      status: 'Planned',
      startDate: new Date(),
      durationHours: 1,
      durationMinutes: 0,
    },
  })

  const parentType = watch('parentType')
  const startDate = watch('startDate')
  const durationHours = watch('durationHours')
  const durationMinutes = watch('durationMinutes')

  // Calculate end date based on start date and duration
  React.useEffect(() => {
    if (startDate) {
      const endDate = new Date(startDate)
      endDate.setHours(endDate.getHours() + (durationHours || 0))
      endDate.setMinutes(endDate.getMinutes() + (durationMinutes || 0))
      setValue('endDate', endDate)
    }
  }, [startDate, durationHours, durationMinutes, setValue])

  const onSubmit: SubmitHandler<MeetingFormData> = async (data) => {
    try {
      const formattedData = {
        name: data.name,
        status: data.status as 'Planned' | 'Held' | 'Cancelled',
        startDate: data.startDate.toISOString(),
        endDate: data.endDate?.toISOString() || '',
        durationHours: data.durationHours,
        durationMinutes: data.durationMinutes,
        location: data.location,
        parentType: data.parentType,
        parentId: data.parentId,
        description: data.description,
        type: 'In Person' as const,
      }

      if (isEdit && id) {
        await updateMeeting.mutateAsync({ ...formattedData, dateEntered: '', dateModified: '' })
      } else {
        await createMeeting.mutateAsync({ ...formattedData, dateEntered: '', dateModified: '' })
      }
      
      navigate('/activities')
    } catch {
      toast.error('Failed to save meeting. Please try again.')
    }
  }

  if (isLoadingMeeting) {
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
          {isEdit ? 'Edit Meeting' : 'Schedule Meeting'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Subject *</Label>
          <Input
            id="name"
            {...register('name')}
            placeholder="Meeting with John Doe"
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
              defaultValue={meeting?.status || 'Planned'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Planned">Planned</SelectItem>
                <SelectItem value="Held">Held</SelectItem>
                <SelectItem value="Not Held">Not Held</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="location">Location</Label>
            <Input
              id="location"
              {...register('location')}
              placeholder="Conference Room A"
              disabled={isSubmitting}
            />
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
              step="15"
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
              defaultValue={meeting?.parentType}
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
                defaultValue={meeting?.parentId}
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
          <Label htmlFor="description">Meeting Agenda / Notes</Label>
          <Textarea
            id="description"
            rows={6}
            {...register('description')}
            disabled={isSubmitting}
            placeholder="Meeting agenda and notes..."
          />
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Meeting' : 'Schedule Meeting'}
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