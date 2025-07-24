import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Edit, MessageSquare, CheckCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { useCase, useResolveCase } from '@/hooks/use-cases'
import { useNotes, useCreateNote } from '@/hooks/use-activities'
import { formatDateTime } from '@/lib/utils'
import type { Note } from '@/types/api.generated'
import { PriorityBadge } from '@/components/ui/priority-badge'
import { useState } from 'react'
import { toast } from 'sonner'

export function CaseDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [newNote, setNewNote] = useState('')
  const [resolution, setResolution] = useState('')
  const [isResolving, setIsResolving] = useState(false)

  const { data: caseData, isLoading } = useCase(id || '')
  const { data: notes } = useNotes(1, 100, { parentId: id, parentType: 'Cases' })
  const resolveCase = useResolveCase(id || '')
  const createNote = useCreateNote()

  if (isLoading) {
    return <div className="p-6">Loading...</div>
  }

  if (!caseData?.data) {
    return <div className="p-6">Case not found</div>
  }

  const caseItem = caseData.data

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      'New': 'bg-blue-100 text-blue-800',
      'Assigned': 'bg-yellow-100 text-yellow-800',
      'In Progress': 'bg-purple-100 text-purple-800',
      'Pending Input': 'bg-orange-100 text-orange-800',
      'Closed': 'bg-gray-100 text-gray-800',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
  }

  const handleAddNote = async () => {
    if (!newNote.trim()) return

    try {
      await createNote.mutateAsync({
        name: `Note for Case #${caseItem.caseNumber}`,
        description: newNote,
        parentType: 'Cases',
        parentId: id,
        status: 'Active',
        dateEntered: '',
        dateModified: ''
      })
      setNewNote('')
      toast.success('Your note has been added to the case.')
    } catch {
      toast.error('Failed to add note. Please try again.')
    }
  }

  const handleResolveCase = async () => {
    if (!resolution.trim()) {
      toast.error('Please provide a resolution for the case.')
      return
    }

    setIsResolving(true)
    try {
      await resolveCase.mutateAsync(resolution)
      toast.success('The case has been marked as resolved.')
      navigate('/cases')
    } catch {
      toast.error('Failed to resolve case. Please try again.')
    } finally {
      setIsResolving(false)
    }
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate('/cases')}
            className="mr-4"
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-2xl font-semibold">Case #{caseItem.caseNumber}</h1>
            <p className="text-muted-foreground">{caseItem.name}</p>
          </div>
        </div>
        <div className="flex gap-2">
          {caseItem.status !== 'Closed' && (
            <Button variant="outline" asChild>
              <Link to={`/cases/${id}/edit`}>
                <Edit className="mr-2 h-4 w-4" />
                Edit
              </Link>
            </Button>
          )}
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <div className="md:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Case Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Status</p>
                  <Badge className={getStatusColor(caseItem.status)}>
                    {caseItem.status}
                  </Badge>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Priority</p>
                  <PriorityBadge priority={caseItem.priority as 'P1' | 'P2' | 'P3'} />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Type</p>
                  <p>{caseItem.type}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Created</p>
                  <p>{formatDateTime(new Date().toISOString())}</p>
                </div>
              </div>

              <Separator />

              <div>
                <p className="text-sm font-medium text-muted-foreground mb-2">Description</p>
                <p className="whitespace-pre-wrap">{caseItem.description || 'No description provided'}</p>
              </div>

              {caseItem.resolution && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground mb-2">Resolution</p>
                    <p className="whitespace-pre-wrap">{caseItem.resolution}</p>
                  </div>
                </>
              )}
            </CardContent>
          </Card>

          {caseItem.status !== 'Closed' && (
            <Card>
              <CardHeader>
                <CardTitle>Resolve Case</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <Textarea
                  placeholder="Describe how this case was resolved..."
                  value={resolution}
                  onChange={(e) => setResolution(e.target.value)}
                  rows={4}
                />
                <Button 
                  onClick={handleResolveCase} 
                  disabled={isResolving}
                  className="w-full"
                >
                  <CheckCircle className="mr-2 h-4 w-4" />
                  Mark as Resolved
                </Button>
              </CardContent>
            </Card>
          )}

          <Tabs defaultValue="notes">
            <TabsList>
              <TabsTrigger value="notes">Notes & Comments</TabsTrigger>
              <TabsTrigger value="activities">Activities</TabsTrigger>
            </TabsList>
            
            <TabsContent value="notes" className="mt-4">
              <Card>
                <CardHeader>
                  <CardTitle>Add Note</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Textarea
                    placeholder="Add a note or comment..."
                    value={newNote}
                    onChange={(e) => setNewNote(e.target.value)}
                    rows={3}
                  />
                  <Button onClick={handleAddNote} disabled={!newNote.trim()}>
                    <MessageSquare className="mr-2 h-4 w-4" />
                    Add Note
                  </Button>
                </CardContent>
              </Card>

              <div className="mt-4 space-y-4">
                {notes?.data.filter((note: Note) => note.parentId === id).map((note: Note) => (
                  <Card key={note.id}>
                    <CardContent className="pt-6">
                      <div className="flex items-start justify-between mb-2">
                        <div className="text-sm text-muted-foreground">
                          System • {formatDateTime(new Date().toISOString())}
                        </div>
                      </div>
                      <p className="whitespace-pre-wrap">{note.description}</p>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </TabsContent>

            <TabsContent value="activities" className="mt-4">
              <p className="text-muted-foreground">Activity timeline will be displayed here</p>
            </TabsContent>
          </Tabs>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Account Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {caseItem.accountId ? (
                <>
                  <p className="font-medium">Account Name</p>
                  <Button variant="outline" size="sm" className="w-full" asChild>
                    <Link to={`/accounts/123`}>View Account</Link>
                  </Button>
                </>
              ) : (
                <p className="text-muted-foreground">No account linked</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Contact Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {caseItem.contactId ? (
                <>
                  <p className="font-medium">{caseItem.contactName}</p>
                  <Button variant="outline" size="sm" className="w-full" asChild>
                    <Link to={`/contacts/${caseItem.contactId}`}>View Contact</Link>
                  </Button>
                </>
              ) : (
                <p className="text-muted-foreground">No contact linked</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Assigned To</CardTitle>
            </CardHeader>
            <CardContent>
              <p>{caseItem.assignedUserName || 'Unassigned'}</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}