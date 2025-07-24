import { useEffect } from 'react'
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
import { DatePicker } from '@/components/ui/date-picker'
import { 
  useOpportunity, 
  useCreateOpportunity, 
  useUpdateOpportunity 
} from '@/hooks/use-opportunities'
import { useAccounts } from '@/hooks/use-accounts'
import type { OpportunityStage } from '@/types/phase2.types'
import { STAGE_PROBABILITIES } from '@/types/phase2.types'

const opportunitySchema = z.object({
  name: z.string().min(1, 'Opportunity name is required'),
  accountId: z.string().min(1, 'Account is required'),
  salesStage: z.string(),
  amount: z.number().min(0, 'Amount must be positive'),
  probability: z.number().min(0).max(100),
  closeDate: z.date(),
  leadSource: z.string().optional(),
  nextStep: z.string().optional(),
  description: z.string().optional(),
})

type OpportunityFormData = z.infer<typeof opportunitySchema>

const stages: OpportunityStage[] = [
  'Qualification',
  'Needs Analysis',
  'Value Proposition',
  'Decision Makers',
  'Proposal',
  'Negotiation',
  'Closed Won',
  'Closed Lost',
]

export function OpportunityForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: opportunityData, isLoading: isLoadingOpportunity } = useOpportunity(id || '')
  const { data: accountsData } = useAccounts(1, 100)
  const createMutation = useCreateOpportunity()
  const updateMutation = useUpdateOpportunity(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<OpportunityFormData>({
    resolver: zodResolver(opportunitySchema),
    defaultValues: {
      salesStage: 'Qualification',
      probability: 10,
      closeDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // 30 days from now
    },
  })

  const selectedStage = watch('salesStage')

  // Update form when opportunity data loads
  useEffect(() => {
    if (opportunityData?.data && isEdit) {
      const opp = opportunityData.data
      setValue('name', opp.name)
      setValue('salesStage', opp.salesStage)
      setValue('amount', opp.amount)
      setValue('probability', opp.probability || 0)
      setValue('closeDate', new Date(opp.closeDate))
      // leadSource not available in Opportunity type
      setValue('nextStep', opp.nextStep || '')
      setValue('description', opp.description || '')
      // Note: accountId might need to be extracted from relationships
    }
  }, [opportunityData, isEdit, setValue])

  // Update probability when stage changes
  useEffect(() => {
    if (selectedStage && STAGE_PROBABILITIES[selectedStage as OpportunityStage] !== undefined) {
      setValue('probability', STAGE_PROBABILITIES[selectedStage as OpportunityStage])
    }
  }, [selectedStage, setValue])

  const onSubmit = async (data: OpportunityFormData) => {
    const formattedData = {
      name: data.name,
      salesStage: data.salesStage,
      amount: data.amount,
      probability: data.probability,
      closeDate: data.closeDate?.toISOString().split('T')[0] || '',
      nextStep: data.nextStep,
      description: data.description,
      // Note: In a real app, you'd need to handle the account relationship properly
    }

    try {
      if (isEdit) {
        await updateMutation.mutateAsync(formattedData)
      } else {
        await createMutation.mutateAsync(formattedData)
      }
      navigate('/opportunities')
    } catch {
      // Error is handled by the mutation
    }
  }

  if (isLoadingOpportunity && isEdit) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    )
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
          <Label htmlFor="accountId">Account</Label>
          <Select
            onValueChange={(value) => setValue('accountId', value)}
            defaultValue={watch('accountId')}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select an account" />
            </SelectTrigger>
            <SelectContent>
              {accountsData?.data.map((account) => (
                <SelectItem key={account.id} value={account.id || ''}>
                  {account.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.accountId && (
            <p className="text-sm text-destructive">{errors.accountId.message}</p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="salesStage">Sales Stage</Label>
            <Select
              onValueChange={(value) => setValue('salesStage', value)}
              defaultValue={watch('salesStage')}
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
            <Label htmlFor="closeDate">Expected Close Date</Label>
            <DatePicker
              date={watch('closeDate')}
              onDateChange={(date) => setValue('closeDate', date || new Date())}
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="leadSource">Lead Source</Label>
          <Select
            onValueChange={(value) => setValue('leadSource', value)}
            defaultValue={watch('leadSource')}
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
          <Label htmlFor="nextStep">Next Step</Label>
          <Input
            id="nextStep"
            {...register('nextStep')}
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
  )
}