import { useNavigate, useParams } from 'react-router-dom'
import { useForm, type SubmitHandler } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ArrowLeft, Loader2, Upload } from 'lucide-react'
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
import { useNote, useCreateNote, useUpdateNote } from '@/hooks/use-activities'
import { useAccounts } from '@/hooks/use-accounts'
import { useContacts } from '@/hooks/use-contacts'
import { useCallback } from 'react'
import { useDropzone } from 'react-dropzone'

const noteSchema = z.object({
  name: z.string().min(1, 'Note subject is required'),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  description: z.string().min(1, 'Note content is required'),
  fileName: z.string().optional(),
  fileMimeType: z.string().optional(),
})

type NoteFormData = z.infer<typeof noteSchema>

export function NoteForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: note, isLoading: isLoadingNote } = useNote(id || '')
  const { data: accounts } = useAccounts()
  const { data: contacts } = useContacts()
  
  const createNote = useCreateNote()
  const updateNote = useUpdateNote(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<NoteFormData>({
    resolver: zodResolver(noteSchema),
    defaultValues: note ? {
      name: note.name,
      description: note.description,
      parentType: note.parentType,
      parentId: note.parentId,
    } : {},
  })

  const parentType = watch('parentType')
  const fileName = watch('fileName')

  const onDrop = useCallback((acceptedFiles: File[]) => {
    if (acceptedFiles.length > 0) {
      const file = acceptedFiles[0]
      if (file) {
        setValue('fileName', file.name)
        setValue('fileMimeType', file.type)
        // In a real implementation, you would upload the file here
        toast.success(`${file.name} will be attached to this note.`)
      }
    }
  }, [setValue])

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    maxFiles: 1,
    accept: {
      'application/pdf': ['.pdf'],
      'application/msword': ['.doc'],
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
      'text/plain': ['.txt'],
      'image/*': ['.png', '.jpg', '.jpeg', '.gif'],
    },
  })

  const onSubmit: SubmitHandler<NoteFormData> = async (data) => {
    try {
      if (isEdit && id) {
        await updateNote.mutateAsync({ ...data, status: 'Active', dateEntered: '', dateModified: '' })
      } else {
        await createNote.mutateAsync({ ...data, status: 'Active', dateEntered: '', dateModified: '' })
      }
      
      navigate('/activities')
    } catch {
      toast.error('Failed to save note. Please try again.')
    }
  }

  if (isLoadingNote) {
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
          {isEdit ? 'Edit Note' : 'Create Note'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Subject *</Label>
          <Input
            id="name"
            {...register('name')}
            placeholder="Meeting notes"
            disabled={isSubmitting}
          />
          {errors.name && (
            <p className="text-sm text-destructive">{errors.name.message}</p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="parentType">Related To</Label>
            <Select
              onValueChange={(value) => {
                setValue('parentType', value)
                setValue('parentId', '')
              }}
              defaultValue={note?.parentType}
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
                defaultValue={note?.parentId}
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
          <Label htmlFor="description">Note Content *</Label>
          <Textarea
            id="description"
            rows={8}
            {...register('description')}
            disabled={isSubmitting}
            placeholder="Enter your note content..."
          />
          {errors.description && (
            <p className="text-sm text-destructive">{errors.description.message}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label>Attachment</Label>
          <div
            {...getRootProps()}
            className={`border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors ${
              isDragActive ? 'border-primary bg-primary/5' : 'border-gray-300 hover:border-gray-400'
            }`}
          >
            <input {...getInputProps()} />
            <Upload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
            {fileName ? (
              <p className="text-sm">Selected: {fileName}</p>
            ) : (
              <>
                <p className="text-sm text-gray-600">
                  {isDragActive
                    ? 'Drop the file here...'
                    : 'Drag and drop a file here, or click to select'}
                </p>
                <p className="text-xs text-gray-500 mt-1">
                  PDF, DOC, DOCX, TXT, or images
                </p>
              </>
            )}
          </div>
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Note' : 'Create Note'}
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