import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {
  Plus,
  Save,
  ArrowLeft,
  Eye,
  Settings,
  Type,
  Mail,
  Phone,
  List,
  CheckSquare,
  Radio,
  FileText,
  Hash,
  Calendar
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from '@/components/ui/select';
import { useToast } from '@/components/ui/use-toast';
import { FormField } from '@/components/features/form-builder/FormField';
import { formBuilderService } from '@/services/formBuilder.service';
import type { Form, FormField as FormFieldType } from '@/types/api.types';

const fieldTypes = [
  { type: 'text', label: 'Text Input', icon: Type },
  { type: 'email', label: 'Email', icon: Mail },
  { type: 'tel', label: 'Phone', icon: Phone },
  { type: 'number', label: 'Number', icon: Hash },
  { type: 'date', label: 'Date', icon: Calendar },
  { type: 'select', label: 'Dropdown', icon: List },
  { type: 'checkbox', label: 'Checkbox', icon: CheckSquare },
  { type: 'radio', label: 'Radio', icon: Radio },
  { type: 'textarea', label: 'Text Area', icon: FileText },
];

export function FormBuilderPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const isEditing = !!id;

  const [formName, setFormName] = useState('');
  const [formDescription, setFormDescription] = useState('');
  const [fields, setFields] = useState<FormFieldType[]>([]);
  const [selectedFieldId, setSelectedFieldId] = useState<string | null>(null);
  const [submitButtonText, setSubmitButtonText] = useState('Submit');
  const [successMessage, setSuccessMessage] = useState('Thank you for your submission!');
  const [redirectUrl, setRedirectUrl] = useState('');
  const [notificationEmail, setNotificationEmail] = useState('');
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  const [primaryColor, setPrimaryColor] = useState('#3b82f6');

  // Fetch form if editing
  const { data: existingForm } = useQuery({
    queryKey: ['form', id],
    queryFn: () => formBuilderService.getForm(id!),
    enabled: isEditing
  });

  useEffect(() => {
    if (existingForm) {
      setFormName(existingForm.name);
      setFormDescription(existingForm.description || '');
      setFields(existingForm.fields);
      setSubmitButtonText(existingForm.settings.submitButtonText || 'Submit');
      setSuccessMessage(existingForm.settings.successMessage || 'Thank you for your submission!');
      setRedirectUrl(existingForm.settings.redirectUrl || '');
      // These fields might be added in the future but aren't in the current type
      setNotificationEmail('');
      setTheme('light');
      setPrimaryColor('#3b82f6');
    }
  }, [existingForm]);

  // Save form mutation
  const saveMutation = useMutation({
    mutationFn: (data: Partial<Form>) => {
      if (isEditing) {
        return formBuilderService.updateForm(id!, data);
      }
      return formBuilderService.createForm(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['forms'] });
      toast({
        title: 'Form saved',
        description: 'Your form has been saved successfully.'
      });
      navigate('/forms');
    },
    onError: () => {
      toast({
        title: 'Save failed',
        description: 'Unable to save the form. Please try again.',
        variant: 'destructive'
      });
    }
  });

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (active.id !== over?.id) {
      setFields((items) => {
        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over?.id);
        return arrayMove(items, oldIndex, newIndex);
      });
    }
  };

  const addField = (type: string) => {
    const newField: FormFieldType = {
      id: Date.now().toString(),
      name: `field_${fields.length + 1}`,
      type: type as FormFieldType['type'],
      label: `${type.charAt(0).toUpperCase() + type.slice(1)} Field`,
      placeholder: '',
      required: false,
      options: type === 'select' || type === 'radio' 
        ? [{ label: 'Option 1', value: 'option1' }] 
        : undefined
    };
    setFields([...fields, newField]);
    setSelectedFieldId(newField.id);
  };

  const updateField = (fieldId: string, updates: Partial<FormFieldType> | FormFieldType) => {
    setFields(fields.map(field => 
      field.id === fieldId ? { ...field, ...updates } : field
    ));
  };

  const deleteField = (fieldId: string) => {
    setFields(fields.filter(field => field.id !== fieldId));
    if (selectedFieldId === fieldId) {
      setSelectedFieldId(null);
    }
  };

  const selectedField = fields.find(f => f.id === selectedFieldId);

  const handleSave = () => {
    if (!formName || fields.length === 0) {
      toast({
        title: 'Validation error',
        description: 'Please provide a form name and at least one field.',
        variant: 'destructive'
      });
      return;
    }

    const formData: Partial<Form> = {
      name: formName,
      description: formDescription,
      fields,
      settings: {
        submitButtonText: submitButtonText,
        successMessage: successMessage,
        redirectUrl: redirectUrl
      }
    };

    saveMutation.mutate(formData);
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={() => navigate('/forms')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">
              {isEditing ? 'Edit Form' : 'Create Form'}
            </h1>
            <p className="text-muted-foreground mt-1">
              Build dynamic forms with drag-and-drop
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => window.open(`/forms/preview/${id || 'new'}`, '_blank')}>
            <Eye className="mr-2 h-4 w-4" />
            Preview
          </Button>
          <Button onClick={handleSave} disabled={saveMutation.isPending}>
            <Save className="mr-2 h-4 w-4" />
            {saveMutation.isPending ? 'Saving...' : 'Save Form'}
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-12 gap-6">
        {/* Field Types Sidebar */}
        <div className="col-span-3">
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Field Types</CardTitle>
            </CardHeader>
            <CardContent className="p-2">
              <div className="space-y-2">
                {fieldTypes.map((fieldType) => (
                  <Button
                    key={fieldType.type}
                    variant="outline"
                    className="w-full justify-start"
                    onClick={() => addField(fieldType.type)}
                  >
                    <fieldType.icon className="mr-2 h-4 w-4" />
                    {fieldType.label}
                  </Button>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Form Builder */}
        <div className="col-span-6">
          <Card>
            <CardHeader>
              <div className="space-y-4">
                <Input
                  placeholder="Form Name"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  className="text-lg font-semibold"
                />
                <Textarea
                  placeholder="Form Description (optional)"
                  value={formDescription}
                  onChange={(e) => setFormDescription(e.target.value)}
                  rows={2}
                />
              </div>
            </CardHeader>
            <CardContent>
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
              >
                <SortableContext
                  items={fields}
                  strategy={verticalListSortingStrategy}
                >
                  <div className="space-y-3">
                    {fields.length === 0 ? (
                      <div className="text-center py-12 text-muted-foreground">
                        <Plus className="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>Add fields from the sidebar to start building your form</p>
                      </div>
                    ) : (
                      fields.map((field) => (
                        <FormField
                          key={field.id}
                          field={field}
                          onUpdate={(updatedField) => updateField(field.id, updatedField)}
                          onDelete={() => deleteField(field.id)}
                          onDuplicate={(fieldToDuplicate) => {
                            const newField = {
                              ...fieldToDuplicate,
                              id: Date.now().toString(),
                              name: `${fieldToDuplicate.name}_copy`
                            };
                            setFields([...fields, newField]);
                          }}
                        />
                      ))
                    )}
                  </div>
                </SortableContext>
              </DndContext>
            </CardContent>
          </Card>
        </div>

        {/* Field Settings */}
        <div className="col-span-3">
          <Card>
            <CardHeader>
              <CardTitle className="text-sm flex items-center gap-2">
                <Settings className="h-4 w-4" />
                {selectedField ? 'Field Settings' : 'Form Settings'}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {selectedField ? (
                <div className="space-y-4">
                  <div>
                    <Label>Field Label</Label>
                    <Input
                      value={selectedField.label}
                      onChange={(e) => updateField(selectedField.id, { label: e.target.value })}
                    />
                  </div>
                  
                  <div>
                    <Label>Field Name</Label>
                    <Input
                      value={selectedField.name}
                      onChange={(e) => updateField(selectedField.id, { name: e.target.value })}
                    />
                  </div>
                  
                  {selectedField.type !== 'checkbox' && (
                    <div>
                      <Label>Placeholder</Label>
                      <Input
                        value={selectedField.placeholder || ''}
                        onChange={(e) => updateField(selectedField.id, { placeholder: e.target.value })}
                      />
                    </div>
                  )}
                  
                  <div className="flex items-center space-x-2">
                    <Switch
                      checked={selectedField.required}
                      onCheckedChange={(checked) => updateField(selectedField.id, { required: checked })}
                    />
                    <Label>Required</Label>
                  </div>
                  
                  {(selectedField.type === 'select' || selectedField.type === 'radio') && (
                    <div>
                      <Label>Options</Label>
                      {selectedField.options?.map((option, index) => (
                        <div key={index} className="flex items-center gap-2 mt-2">
                          <Input
                            value={option.label}
                            onChange={(e) => {
                              const newOptions = [...(selectedField.options || [])];
                              newOptions[index] = { ...option, label: e.target.value };
                              updateField(selectedField.id, { options: newOptions });
                            }}
                            placeholder="Label"
                          />
                          <Input
                            value={option.value}
                            onChange={(e) => {
                              const newOptions = [...(selectedField.options || [])];
                              newOptions[index] = { ...option, value: e.target.value };
                              updateField(selectedField.id, { options: newOptions });
                            }}
                            placeholder="Value"
                          />
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                              const newOptions = selectedField.options?.filter((_, i) => i !== index);
                              updateField(selectedField.id, { options: newOptions });
                            }}
                          >
                            ×
                          </Button>
                        </div>
                      ))}
                      <Button
                        size="sm"
                        variant="outline"
                        className="mt-2 w-full"
                        onClick={() => {
                          const newOptions = [
                            ...(selectedField.options || []),
                            { label: `Option ${(selectedField.options?.length || 0) + 1}`, value: `option${(selectedField.options?.length || 0) + 1}` }
                          ];
                          updateField(selectedField.id, { options: newOptions });
                        }}
                      >
                        Add Option
                      </Button>
                    </div>
                  )}
                </div>
              ) : (
                <Tabs defaultValue="settings">
                  <TabsList className="grid w-full grid-cols-2">
                    <TabsTrigger value="settings">Settings</TabsTrigger>
                    <TabsTrigger value="styling">Styling</TabsTrigger>
                  </TabsList>
                  
                  <TabsContent value="settings" className="space-y-4">
                    <div>
                      <Label>Submit Button Text</Label>
                      <Input
                        value={submitButtonText}
                        onChange={(e) => setSubmitButtonText(e.target.value)}
                      />
                    </div>
                    
                    <div>
                      <Label>Success Message</Label>
                      <Textarea
                        value={successMessage}
                        onChange={(e) => setSuccessMessage(e.target.value)}
                        rows={3}
                      />
                    </div>
                    
                    <div>
                      <Label>Redirect URL (optional)</Label>
                      <Input
                        value={redirectUrl}
                        onChange={(e) => setRedirectUrl(e.target.value)}
                        placeholder="https://example.com/thank-you"
                      />
                    </div>
                    
                    <div>
                      <Label>Notification Email (optional)</Label>
                      <Input
                        value={notificationEmail}
                        onChange={(e) => setNotificationEmail(e.target.value)}
                        placeholder="admin@example.com"
                      />
                    </div>
                  </TabsContent>
                  
                  <TabsContent value="styling" className="space-y-4">
                    <div>
                      <Label>Theme</Label>
                      <Select value={theme} onValueChange={(value: 'light' | 'dark') => setTheme(value)}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="light">Light</SelectItem>
                          <SelectItem value="dark">Dark</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    
                    <div>
                      <Label>Primary Color</Label>
                      <div className="flex items-center gap-2">
                        <Input
                          type="color"
                          value={primaryColor}
                          onChange={(e) => setPrimaryColor(e.target.value)}
                          className="w-16 h-10"
                        />
                        <Input
                          value={primaryColor}
                          onChange={(e) => setPrimaryColor(e.target.value)}
                        />
                      </div>
                    </div>
                  </TabsContent>
                </Tabs>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}