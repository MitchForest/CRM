import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { 
  Save, 
  ArrowLeft, 
  Eye, 
  Bold, 
  Italic, 
  List, 
  ListOrdered,
  Quote,
  Code,
  Link2,
  Image as ImageIcon,
  Undo,
  Redo,
  Heading1,
  Heading2,
  Heading3
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/use-toast';
import { knowledgeBaseService } from '@/services/knowledgeBase.service';
import type { KBArticle } from '@/types/phase3.types';
import { cn } from '@/lib/utils';

// TipTap editor toolbar button
const ToolbarButton = ({ 
  onClick, 
  active = false, 
  disabled = false, 
  children, 
  title 
}: {
  onClick: () => void;
  active?: boolean;
  disabled?: boolean;
  children: React.ReactNode;
  title: string;
}) => (
  <button
    onClick={onClick}
    disabled={disabled}
    title={title}
    className={cn(
      "p-2 rounded hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed",
      active && "bg-muted"
    )}
  >
    {children}
  </button>
);

export function ArticleEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const isEditing = !!id;

  // Form state
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [tags, setTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [isFeatured, setIsFeatured] = useState(false);

  // Fetch categories
  const { data: categories = [] } = useQuery({
    queryKey: ['kb-categories'],
    queryFn: () => knowledgeBaseService.getCategories()
  });

  // Fetch article if editing
  const { data: article } = useQuery({
    queryKey: ['kb-article', id],
    queryFn: () => knowledgeBaseService.getArticle(id!),
    enabled: isEditing
  });

  // TipTap editor
  const editor = useEditor({
    extensions: [
      StarterKit,
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-primary underline'
        }
      }),
      Image.configure({
        HTMLAttributes: {
          class: 'max-w-full h-auto rounded-lg'
        }
      })
    ],
    content: '',
    editorProps: {
      attributes: {
        class: 'prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[400px] p-4'
      }
    }
  });

  // Load article data
  useEffect(() => {
    if (article && editor) {
      setTitle(article.title);
      setSlug(article.slug);
      setExcerpt(article.excerpt || '');
      setCategoryId(article.category_id || '');
      setTags(article.tags || []);
      setIsPublic(article.is_public);
      setIsFeatured(article.is_featured);
      editor.commands.setContent(article.content);
    }
  }, [article, editor]);

  // Auto-generate slug from title
  useEffect(() => {
    if (!isEditing && title) {
      const generatedSlug = title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
      setSlug(generatedSlug);
    }
  }, [title, isEditing]);

  // Save mutation
  const saveMutation = useMutation({
    mutationFn: async (data: Partial<KBArticle>) => {
      // Validate slug uniqueness
      const isSlugValid = await knowledgeBaseService.validateSlug(data.slug!, id);
      if (!isSlugValid) {
        throw new Error('This slug is already in use. Please choose a different one.');
      }
      
      if (isEditing) {
        return knowledgeBaseService.updateArticle(id!, data);
      }
      return knowledgeBaseService.createArticle(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb-articles'] });
      toast({
        title: 'Article saved',
        description: 'Your article has been saved successfully.'
      });
      navigate('/kb');
    },
    onError: (error: any) => {
      toast({
        title: 'Save failed',
        description: error.message || 'Unable to save the article. Please try again.',
        variant: 'destructive'
      });
    }
  });

  const handleSave = () => {
    if (!title || !slug || !editor?.getHTML()) {
      toast({
        title: 'Validation error',
        description: 'Please fill in all required fields.',
        variant: 'destructive'
      });
      return;
    }

    const articleData: Partial<KBArticle> = {
      title,
      slug,
      content: editor.getHTML(),
      excerpt: excerpt || undefined,
      category_id: categoryId || undefined,
      tags,
      is_public: isPublic,
      is_featured: isFeatured
    };

    saveMutation.mutate(articleData);
  };

  const addTag = () => {
    if (tagInput.trim() && !tags.includes(tagInput.trim())) {
      setTags([...tags, tagInput.trim()]);
      setTagInput('');
    }
  };

  const removeTag = (tagToRemove: string) => {
    setTags(tags.filter(tag => tag !== tagToRemove));
  };

  const setLink = () => {
    const url = prompt('Enter URL:');
    if (url) {
      editor?.chain().focus().setLink({ href: url }).run();
    }
  };

  const addImage = () => {
    const url = prompt('Enter image URL:');
    if (url) {
      editor?.chain().focus().setImage({ src: url }).run();
    }
  };

  if (!editor) return null;

  return (
    <div className="p-6 max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={() => navigate('/kb')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold">
              {isEditing ? 'Edit Article' : 'Create Article'}
            </h1>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {slug && (
            <Button 
              variant="outline" 
              onClick={() => window.open(`/kb/public/${slug}`, '_blank')}
            >
              <Eye className="mr-2 h-4 w-4" />
              Preview
            </Button>
          )}
          <Button onClick={handleSave} disabled={saveMutation.isPending}>
            <Save className="mr-2 h-4 w-4" />
            {saveMutation.isPending ? 'Saving...' : 'Save Article'}
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-12 gap-6">
        {/* Main Content */}
        <div className="col-span-8">
          <Card>
            <CardContent className="p-6 space-y-4">
              {/* Title */}
              <div>
                <Label>Title</Label>
                <Input
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Enter article title"
                  className="text-lg"
                />
              </div>

              {/* Slug */}
              <div>
                <Label>Slug</Label>
                <Input
                  value={slug}
                  onChange={(e) => setSlug(e.target.value)}
                  placeholder="article-url-slug"
                />
                <p className="text-sm text-muted-foreground mt-1">
                  URL: /kb/public/{slug || 'article-url-slug'}
                </p>
              </div>

              {/* Editor Toolbar */}
              <div className="border rounded-lg">
                <div className="flex items-center gap-1 p-2 border-b flex-wrap">
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
                    active={editor.isActive('heading', { level: 1 })}
                    title="Heading 1"
                  >
                    <Heading1 className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                    active={editor.isActive('heading', { level: 2 })}
                    title="Heading 2"
                  >
                    <Heading2 className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                    active={editor.isActive('heading', { level: 3 })}
                    title="Heading 3"
                  >
                    <Heading3 className="h-4 w-4" />
                  </ToolbarButton>
                  
                  <Separator orientation="vertical" className="h-6 mx-1" />
                  
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleBold().run()}
                    active={editor.isActive('bold')}
                    title="Bold"
                  >
                    <Bold className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                    active={editor.isActive('italic')}
                    title="Italic"
                  >
                    <Italic className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleCode().run()}
                    active={editor.isActive('code')}
                    title="Code"
                  >
                    <Code className="h-4 w-4" />
                  </ToolbarButton>
                  
                  <Separator orientation="vertical" className="h-6 mx-1" />
                  
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                    active={editor.isActive('bulletList')}
                    title="Bullet List"
                  >
                    <List className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                    active={editor.isActive('orderedList')}
                    title="Numbered List"
                  >
                    <ListOrdered className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().toggleBlockquote().run()}
                    active={editor.isActive('blockquote')}
                    title="Quote"
                  >
                    <Quote className="h-4 w-4" />
                  </ToolbarButton>
                  
                  <Separator orientation="vertical" className="h-6 mx-1" />
                  
                  <ToolbarButton
                    onClick={setLink}
                    active={editor.isActive('link')}
                    title="Add Link"
                  >
                    <Link2 className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={addImage}
                    title="Add Image"
                  >
                    <ImageIcon className="h-4 w-4" />
                  </ToolbarButton>
                  
                  <Separator orientation="vertical" className="h-6 mx-1" />
                  
                  <ToolbarButton
                    onClick={() => editor.chain().focus().undo().run()}
                    disabled={!editor.can().undo()}
                    title="Undo"
                  >
                    <Undo className="h-4 w-4" />
                  </ToolbarButton>
                  <ToolbarButton
                    onClick={() => editor.chain().focus().redo().run()}
                    disabled={!editor.can().redo()}
                    title="Redo"
                  >
                    <Redo className="h-4 w-4" />
                  </ToolbarButton>
                </div>
                
                {/* Editor Content */}
                <div
                  onClick={() => editor.chain().focus().run()}
                  className="min-h-[400px] cursor-text"
                >
                  <div
                    dangerouslySetInnerHTML={{ __html: editor.getHTML() }}
                    className="prose prose-sm dark:prose-invert max-w-none p-4"
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="col-span-4 space-y-4">
          {/* Publishing Options */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Publishing</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <Label htmlFor="public">Public</Label>
                <Switch
                  id="public"
                  checked={isPublic}
                  onCheckedChange={setIsPublic}
                />
              </div>
              <div className="flex items-center justify-between">
                <Label htmlFor="featured">Featured</Label>
                <Switch
                  id="featured"
                  checked={isFeatured}
                  onCheckedChange={setIsFeatured}
                />
              </div>
            </CardContent>
          </Card>

          {/* Category */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Category</CardTitle>
            </CardHeader>
            <CardContent>
              <Select value={categoryId} onValueChange={setCategoryId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select a category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">No category</SelectItem>
                  {categories.map(cat => (
                    <SelectItem key={cat.id} value={cat.id}>
                      {cat.icon && `${cat.icon} `}{cat.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </CardContent>
          </Card>

          {/* Excerpt */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Excerpt</CardTitle>
            </CardHeader>
            <CardContent>
              <Textarea
                value={excerpt}
                onChange={(e) => setExcerpt(e.target.value)}
                placeholder="Brief description of the article"
                rows={3}
              />
            </CardContent>
          </Card>

          {/* Tags */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Tags</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex gap-2">
                <Input
                  value={tagInput}
                  onChange={(e) => setTagInput(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addTag())}
                  placeholder="Add a tag"
                />
                <Button type="button" onClick={addTag}>Add</Button>
              </div>
              <div className="flex flex-wrap gap-2">
                {tags.map(tag => (
                  <Badge key={tag} variant="secondary" className="gap-1">
                    {tag}
                    <button
                      onClick={() => removeTag(tag)}
                      className="ml-1 hover:text-destructive"
                    >
                      ×
                    </button>
                  </Badge>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}