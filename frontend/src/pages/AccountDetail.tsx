import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft, Phone, Edit, Trash2, Users, Briefcase, AlertCircle, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useAccount, useDeleteAccount } from '@/hooks/use-accounts'
import { useContacts } from '@/hooks/use-contacts'
import { useOpportunities } from '@/hooks/use-opportunities'
import { useCases } from '@/hooks/use-cases'
import { formatDateTime, formatCurrency, formatDate } from '@/lib/utils'
import { ActivityTimeline } from '@/components/activities/ActivityTimeline'
import { useActivitiesByParent } from '@/hooks/use-activities'

const industryColors: Record<string, string> = {
  Technology: 'bg-blue-100 text-blue-700',
  Healthcare: 'bg-green-100 text-green-700',
  Finance: 'bg-purple-100 text-purple-700',
  Manufacturing: 'bg-orange-100 text-orange-700',
  Retail: 'bg-pink-100 text-pink-700',
  Other: 'bg-gray-100 text-gray-700',
}

const getOpportunityStageColor = (stage: string) => {
  const colors: Record<string, string> = {
    'Prospecting': 'bg-gray-100 text-gray-700',
    'Qualification': 'bg-blue-100 text-blue-700',
    'Needs Analysis': 'bg-indigo-100 text-indigo-700',
    'Value Proposition': 'bg-purple-100 text-purple-700',
    'Decision Makers': 'bg-pink-100 text-pink-700',
    'Proposal': 'bg-orange-100 text-orange-700',
    'Negotiation': 'bg-yellow-100 text-yellow-700',
    'Closed Won': 'bg-green-100 text-green-700',
    'Closed Lost': 'bg-red-100 text-red-700',
  }
  return colors[stage] || 'bg-gray-100 text-gray-700'
}

export function AccountDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  
  const { data: accountResponse, isLoading: accountLoading } = useAccount(id!)
  const { data: contacts, isLoading: contactsLoading } = useContacts({ pageSize: 50 })
  const { data: opportunities, isLoading: opportunitiesLoading } = useOpportunities(1, 50)
  const { data: cases, isLoading: casesLoading } = useCases(1, 50)
  const { data: activities } = useActivitiesByParent('Account', id!)
  
  const account = accountResponse?.data
  
  const deleteAccount = useDeleteAccount()

  const handleDelete = async () => {
    await deleteAccount.mutateAsync(id!)
    navigate('/accounts')
  }

  if (accountLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Skeleton className="h-10 w-32" />
          <Skeleton className="h-8 w-48" />
        </div>
        <div className="grid gap-6 md:grid-cols-3">
          <Skeleton className="h-64 md:col-span-2" />
          <Skeleton className="h-64" />
        </div>
      </div>
    )
  }

  if (!account) {
    return (
      <div className="flex flex-col items-center justify-center h-64">
        <AlertCircle className="h-12 w-12 text-muted-foreground mb-4" />
        <p className="text-lg font-semibold">Account not found</p>
        <p className="text-muted-foreground">The account you're looking for doesn't exist.</p>
        <Button asChild className="mt-4">
          <Link to="/accounts">Back to Accounts</Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" asChild>
            <Link to="/accounts">
              <ArrowLeft className="h-4 w-4" />
            </Link>
          </Button>
          <div>
            <h1 className="text-3xl font-bold">{account.name}</h1>
            <p className="text-muted-foreground">Account Details</p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" asChild>
            <Link to={`/accounts/${id}/edit`}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </Button>
          <Button variant="outline" onClick={() => setShowDeleteDialog(true)}>
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <div className="md:col-span-2">
          <Tabs defaultValue="overview">
            <TabsList>
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="contacts">
                Contacts {contacts && `(${contacts.data.length})`}
              </TabsTrigger>
              <TabsTrigger value="opportunities">
                Opportunities {opportunities && `(${opportunities.data.length})`}
              </TabsTrigger>
              <TabsTrigger value="cases">
                Cases {cases && `(${cases.data.length})`}
              </TabsTrigger>
              <TabsTrigger value="activities">
                Activities {activities && `(${activities.length})`}
              </TabsTrigger>
            </TabsList>

            <TabsContent value="overview">
              <Card>
                <CardHeader>
                  <CardTitle>Account Information</CardTitle>
                  <CardDescription>
                    Key details about this account
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Industry</p>
                      <Badge className={industryColors[account.industry || 'Other'] || industryColors.Other}>
                        {account.industry || 'Not specified'}
                      </Badge>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Employees</p>
                      <p>{account.employees || 'Not specified'}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Website</p>
                      {account.website ? (
                        <a href={account.website} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                          {account.website}
                        </a>
                      ) : (
                        <p className="text-muted-foreground">Not provided</p>
                      )}
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Phone</p>
                      <p>{account.phone || 'Not provided'}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Address</p>
                      <p className="text-sm">
                        {account.shippingStreet && `${account.shippingStreet}, `}
                        {account.shippingCity && `${account.shippingCity}, `}
                        {account.shippingState && `${account.shippingState} `}
                        {account.shippingPostalCode}
                        {!account.shippingStreet && !account.shippingCity && 'Not provided'}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Assigned To</p>
                      <p>{account.assignedUserName || 'Unassigned'}</p>
                    </div>
                  </div>
                  
                  {account.description && (
                    <div className="pt-4 border-t">
                      <p className="text-sm font-medium text-muted-foreground mb-2">Description</p>
                      <p className="text-sm whitespace-pre-wrap">{account.description}</p>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="contacts">
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle>Related Contacts</CardTitle>
                      <CardDescription>
                        People associated with this account
                      </CardDescription>
                    </div>
                    <Button size="sm" asChild>
                      <Link to={`/contacts/new?accountId=${id}`}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Contact
                      </Link>
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  {contactsLoading ? (
                    <div className="space-y-2">
                      {[1, 2, 3].map((i) => (
                        <Skeleton key={i} className="h-16" />
                      ))}
                    </div>
                  ) : contacts && contacts.data.length > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Name</TableHead>
                          <TableHead>Title</TableHead>
                          <TableHead>Email</TableHead>
                          <TableHead>Phone</TableHead>
                          <TableHead></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {contacts.data.map((contact) => (
                          <TableRow key={contact.id}>
                            <TableCell className="font-medium">
                              {contact.firstName} {contact.lastName}
                            </TableCell>
                            <TableCell>{contact.title || '-'}</TableCell>
                            <TableCell>{contact.email || '-'}</TableCell>
                            <TableCell>{contact.phone || contact.email || '-'}</TableCell>
                            <TableCell>
                              <Button variant="ghost" size="sm" asChild>
                                <Link to={`/contacts/${contact.id}`}>View</Link>
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <div className="text-center py-8 text-muted-foreground">
                      <Users className="mx-auto h-12 w-12 mb-3 opacity-20" />
                      <p>No contacts yet</p>
                      <p className="text-sm mt-1">Add contacts to track relationships</p>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="opportunities">
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle>Related Opportunities</CardTitle>
                      <CardDescription>
                        Sales opportunities for this account
                      </CardDescription>
                    </div>
                    <Button size="sm" asChild>
                      <Link to={`/opportunities/new?accountId=${id}`}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Opportunity
                      </Link>
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  {opportunitiesLoading ? (
                    <div className="space-y-2">
                      {[1, 2, 3].map((i) => (
                        <Skeleton key={i} className="h-16" />
                      ))}
                    </div>
                  ) : opportunities && opportunities.data.length > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Name</TableHead>
                          <TableHead>Stage</TableHead>
                          <TableHead>Amount</TableHead>
                          <TableHead>Close Date</TableHead>
                          <TableHead></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {opportunities.data.map((opp) => (
                          <TableRow key={opp.id}>
                            <TableCell className="font-medium">{opp.name}</TableCell>
                            <TableCell>
                              <Badge className={getOpportunityStageColor(opp.salesStage)}>
                                {opp.salesStage}
                              </Badge>
                            </TableCell>
                            <TableCell>{formatCurrency(opp.amount)}</TableCell>
                            <TableCell>{formatDate(opp.closeDate)}</TableCell>
                            <TableCell>
                              <Button variant="ghost" size="sm" asChild>
                                <Link to={`/opportunities/${opp.id}`}>View</Link>
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <div className="text-center py-8 text-muted-foreground">
                      <Briefcase className="mx-auto h-12 w-12 mb-3 opacity-20" />
                      <p>No opportunities yet</p>
                      <p className="text-sm mt-1">Create opportunities to track sales</p>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="cases">
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle>Related Cases</CardTitle>
                      <CardDescription>
                        Support cases for this account
                      </CardDescription>
                    </div>
                    <Button size="sm" asChild>
                      <Link to={`/cases/new?accountId=${id}`}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Case
                      </Link>
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  {casesLoading ? (
                    <div className="space-y-2">
                      {[1, 2, 3].map((i) => (
                        <Skeleton key={i} className="h-16" />
                      ))}
                    </div>
                  ) : cases && cases.data.length > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Number</TableHead>
                          <TableHead>Subject</TableHead>
                          <TableHead>Priority</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {cases.data.map((caseItem) => (
                          <TableRow key={caseItem.id}>
                            <TableCell className="font-medium">#{caseItem.caseNumber || caseItem.id}</TableCell>
                            <TableCell>{caseItem.name}</TableCell>
                            <TableCell>
                              <Badge variant={
                                caseItem.priority === 'High' ? 'destructive' :
                                caseItem.priority === 'Medium' ? 'default' : 'secondary'
                              }>
                                {caseItem.priority}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <Badge>{caseItem.status}</Badge>
                            </TableCell>
                            <TableCell>
                              <Button variant="ghost" size="sm" asChild>
                                <Link to={`/cases/${caseItem.id}`}>View</Link>
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <div className="text-center py-8 text-muted-foreground">
                      <AlertCircle className="mx-auto h-12 w-12 mb-3 opacity-20" />
                      <p>No cases yet</p>
                      <p className="text-sm mt-1">Cases will appear here when created</p>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="activities">
              <Card>
                <CardHeader>
                  <CardTitle>Activity Timeline</CardTitle>
                  <CardDescription>
                    All activities related to this account
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ActivityTimeline parentType="Account" parentId={id!} />
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button className="w-full" size="sm" asChild>
                <Link to={`/contacts/new?accountId=${id}`}>
                  <Users className="mr-2 h-4 w-4" />
                  Add Contact
                </Link>
              </Button>
              <Button className="w-full" size="sm" variant="outline" asChild>
                <Link to={`/opportunities/new?accountId=${id}`}>
                  <Briefcase className="mr-2 h-4 w-4" />
                  Create Opportunity
                </Link>
              </Button>
              <Button className="w-full" size="sm" variant="outline" asChild>
                <Link to={`/cases/new?accountId=${id}`}>
                  <AlertCircle className="mr-2 h-4 w-4" />
                  Create Case
                </Link>
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                <Phone className="mr-2 h-4 w-4" />
                Log Call
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Account Summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Total Pipeline</p>
                <p className="text-2xl font-bold">
                  {opportunities ? formatCurrency(
                    opportunities.data.reduce((sum, opp) => sum + (opp.amount || 0), 0)
                  ) : '$0'}
                </p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Open Cases</p>
                <p className="text-2xl font-bold">
                  {cases ? cases.data.filter(c => c.status !== 'Closed').length : 0}
                </p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Created</p>
                <p className="text-sm">{formatDateTime(account.createdAt || account.updatedAt || '')}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Last Modified</p>
                <p className="text-sm">{formatDateTime(account.updatedAt || account.createdAt || '')}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Account</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete this account? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              Delete Account
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}