import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, X, Settings, Copy } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type { FormField as FormFieldType } from '@/types/api.types';
import { cn } from '@/lib/utils';

interface FormFieldProps {
  field: FormFieldType;
  onUpdate: (field: FormFieldType) => void;
  onDelete: (id: string) => void;
  onDuplicate: (field: FormFieldType) => void;
  isPreview?: boolean;
}

export function FormField({ 
  field, 
  onUpdate, 
  onDelete, 
  onDuplicate,
  isPreview 
}: FormFieldProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ 
    id: field.id,
    disabled: isPreview 
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  // Preview mode - render the actual form field
  if (isPreview) {
    return (
      <div className="space-y-2">
        <Label htmlFor={field.id} className="flex items-center gap-1">
          {field.label}
          {field.required && <span className="text-red-500">*</span>}
        </Label>
        {renderFieldInput(field)}
        {field.validation?.pattern && (
          <p className="text-xs text-muted-foreground">
            Format: {field.validation.pattern}
          </p>
        )}
      </div>
    );
  }

  // Edit mode - render the field editor
  return (
    <div ref={setNodeRef} style={style}>
      <Card className={cn(
        "p-4 transition-all",
        isDragging && "shadow-lg ring-2 ring-primary"
      )}>
        <div className="flex items-start gap-3">
          {/* Drag Handle */}
          <div
            {...attributes}
            {...listeners}
            className="cursor-grab active:cursor-grabbing mt-1"
          >
            <GripVertical className="h-5 w-5 text-muted-foreground" />
          </div>
          
          {/* Field Content */}
          <div className="flex-1 space-y-3">
            {/* Header */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Badge variant="secondary" className="text-xs">
                  {field.type}
                </Badge>
                {field.required && (
                  <Badge variant="outline" className="text-xs">
                    Required
                  </Badge>
                )}
              </div>
              <div className="flex items-center gap-1">
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => onDuplicate(field)}
                  className="h-8 w-8 p-0"
                >
                  <Copy className="h-4 w-4" />
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {/* Open field settings modal */}}
                  className="h-8 w-8 p-0"
                >
                  <Settings className="h-4 w-4" />
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => onDelete(field.id)}
                  className="h-8 w-8 p-0 text-red-500 hover:text-red-600"
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            </div>
            
            {/* Field Properties */}
            <div className="grid gap-3 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor={`${field.id}-label`} className="text-xs">
                  Label
                </Label>
                <Input
                  id={`${field.id}-label`}
                  value={field.label}
                  onChange={(e) => onUpdate({ ...field, label: e.target.value })}
                  placeholder="Field Label"
                />
              </div>
              
              <div className="space-y-2">
                <Label htmlFor={`${field.id}-name`} className="text-xs">
                  Field Name
                </Label>
                <Input
                  id={`${field.id}-name`}
                  value={field.name}
                  onChange={(e) => onUpdate({ ...field, name: e.target.value })}
                  placeholder="field_name"
                />
              </div>
            </div>
            
            {/* Additional Properties */}
            {(field.type === 'text' || field.type === 'email' || field.type === 'tel' || field.type === 'textarea') && (
              <div className="space-y-2">
                <Label htmlFor={`${field.id}-placeholder`} className="text-xs">
                  Placeholder
                </Label>
                <Input
                  id={`${field.id}-placeholder`}
                  value={field.placeholder || ''}
                  onChange={(e) => onUpdate({ ...field, placeholder: e.target.value })}
                  placeholder="Enter placeholder text"
                />
              </div>
            )}
            
            {/* Options for select, radio, checkbox */}
            {(field.type === 'select' || field.type === 'radio' || field.type === 'checkbox') && (
              <div className="space-y-2">
                <Label className="text-xs">Options</Label>
                <div className="space-y-2">
                  {field.options?.map((option, index) => (
                    <div key={index} className="flex items-center gap-2">
                      <Input
                        value={option.label}
                        onChange={(e) => {
                          const newOptions = [...(field.options || [])];
                          newOptions[index] = { ...option, label: e.target.value };
                          onUpdate({ ...field, options: newOptions });
                        }}
                        placeholder="Option label"
                      />
                      <Input
                        value={option.value}
                        onChange={(e) => {
                          const newOptions = [...(field.options || [])];
                          newOptions[index] = { ...option, value: e.target.value };
                          onUpdate({ ...field, options: newOptions });
                        }}
                        placeholder="Option value"
                        className="w-32"
                      />
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                          const newOptions = field.options?.filter((_, i) => i !== index);
                          onUpdate({ ...field, options: newOptions });
                        }}
                        className="h-8 w-8 p-0"
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => {
                      const newOptions = [...(field.options || []), { label: '', value: '' }];
                      onUpdate({ ...field, options: newOptions });
                    }}
                  >
                    Add Option
                  </Button>
                </div>
              </div>
            )}
            
            {/* Required Toggle */}
            <div className="flex items-center justify-between">
              <Label htmlFor={`${field.id}-required`} className="text-xs">
                Required Field
              </Label>
              <Switch
                id={`${field.id}-required`}
                checked={field.required}
                onCheckedChange={(checked) => onUpdate({ ...field, required: checked })}
              />
            </div>
          </div>
        </div>
      </Card>
    </div>
  );
}

// Helper function to render field input in preview mode
function renderFieldInput(field: FormFieldType) {
  const commonProps = {
    id: field.id,
    name: field.name,
    placeholder: field.placeholder,
    required: field.required,
  };

  switch (field.type) {
    case 'text':
    case 'email':
    case 'tel':
    case 'number':
    case 'date':
      return (
        <Input
          {...commonProps}
          type={field.type}
        />
      );
      
    case 'textarea':
      return (
        <textarea
          {...commonProps}
          className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
          rows={4}
        />
      );
      
    case 'select':
      return (
        <Select name={field.name} required={field.required}>
          <SelectTrigger id={field.id}>
            <SelectValue placeholder={field.placeholder || 'Select an option'} />
          </SelectTrigger>
          <SelectContent>
            {field.options?.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      );
      
    case 'checkbox':
      return (
        <div className="space-y-2">
          {field.options?.map((option) => (
            <div key={option.value} className="flex items-center space-x-2">
              <input
                type="checkbox"
                id={`${field.id}-${option.value}`}
                name={field.name}
                value={option.value}
                className="rounded border-gray-300"
              />
              <Label 
                htmlFor={`${field.id}-${option.value}`}
                className="text-sm font-normal"
              >
                {option.label}
              </Label>
            </div>
          ))}
        </div>
      );
      
    case 'radio':
      return (
        <div className="space-y-2">
          {field.options?.map((option) => (
            <div key={option.value} className="flex items-center space-x-2">
              <input
                type="radio"
                id={`${field.id}-${option.value}`}
                name={field.name}
                value={option.value}
                required={field.required}
                className="border-gray-300"
              />
              <Label 
                htmlFor={`${field.id}-${option.value}`}
                className="text-sm font-normal"
              >
                {option.label}
              </Label>
            </div>
          ))}
        </div>
      );
      
    default:
      return null;
  }
}