import { useParams, Link } from 'react-router-dom'
import { ArrowLeft, Mail, Phone, Edit, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { useContact } from '@/hooks/use-contacts'
import { formatDate, formatDateTime } from '@/lib/utils'

export function ContactDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { data: contactData, isLoading: contactLoading } = useContact(id!)
  // const { data: activitiesData, isLoading: activitiesLoading } = useContactActivities(id!)

  if (contactLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Skeleton className="h-10 w-32" />
        </div>
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-96 w-full" />
      </div>
    )
  }

  if (!contactData?.success || !contactData.data) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Link to="/contacts">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Contacts
            </Button>
          </Link>
        </div>
        <Card>
          <CardContent className="p-6">
            <p className="text-center text-muted-foreground">Contact not found</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const contact = contactData.data
  // const activities = activitiesData?.data || []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link to="/contacts">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Contacts
            </Button>
          </Link>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" asChild>
            <Link to={`/contacts/${id}/edit`}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </Button>
          <Button variant="outline" size="sm" className="text-destructive">
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <div className="md:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-2xl">
                {contact.firstName} {contact.lastName}
              </CardTitle>
              <CardDescription>
                Customer since {contact.createdAt ? formatDate(contact.createdAt) : 'Unknown'}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Email</p>
                  <a href={`mailto:${contact.email}`} className="flex items-center gap-2 text-sm hover:underline">
                    <Mail className="h-4 w-4" />
                    {contact.email}
                  </a>
                </div>
                {contact.phone && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Phone</p>
                    <a href={`tel:${contact.phone}`} className="flex items-center gap-2 text-sm hover:underline">
                      <Phone className="h-4 w-4" />
                      {contact.phone}
                    </a>
                  </div>
                )}
                {contact.mobile && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Mobile</p>
                    <a href={`tel:${contact.mobile}`} className="flex items-center gap-2 text-sm hover:underline">
                      <Phone className="h-4 w-4" />
                      {contact.mobile}
                    </a>
                  </div>
                )}
                {contact.title && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Title</p>
                    <p className="text-sm">{contact.title}</p>
                  </div>
                )}
                {contact.preferredContactMethod && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Preferred Contact Method</p>
                    <Badge variant="outline" className="mt-1">
                      {contact.preferredContactMethod}
                    </Badge>
                  </div>
                )}
                {contact.customerNumber && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Customer Number</p>
                    <p className="text-sm font-mono">{contact.customerNumber}</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          <Tabs defaultValue="activities" className="w-full">
            <TabsList>
              <TabsTrigger value="activities">Activities</TabsTrigger>
              <TabsTrigger value="cases">Cases</TabsTrigger>
            </TabsList>
            
            <TabsContent value="activities" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Recent Activities</CardTitle>
                  <CardDescription>
                    All interactions and activities with this contact
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <p className="text-center text-muted-foreground">
                    Activity timeline coming soon
                  </p>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="cases">
              <Card>
                <CardContent className="p-6">
                  <p className="text-center text-muted-foreground">
                    Cases feature coming soon
                  </p>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Tags</CardTitle>
            </CardHeader>
            <CardContent>
              {contact.tags && contact.tags.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {contact.tags.map((tag, index) => (
                    <Badge key={index} variant="secondary">
                      {tag}
                    </Badge>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No tags assigned</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button className="w-full" size="sm">
                <Mail className="mr-2 h-4 w-4" />
                Send Email
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                <Phone className="mr-2 h-4 w-4" />
                Log Call
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Create Task
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Schedule Meeting
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div>
                <p className="text-muted-foreground">Created</p>
                <p>{contact.createdAt ? formatDateTime(contact.createdAt) : 'Unknown'}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Last Updated</p>
                <p>{contact.updatedAt ? formatDateTime(contact.updatedAt) : 'Unknown'}</p>
              </div>
              {contact.assignedUserName && (
                <div>
                  <p className="text-muted-foreground">Assigned To</p>
                  <p>{contact.assignedUserName}</p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}