import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { DefaultEditor } from 'react-simple-wysiwyg';
import { 
  Save, 
  ArrowLeft, 
  Eye
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
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/use-toast';
import { knowledgeBaseService } from '@/services/knowledgeBase.service';
import type { KBArticle } from '@/types/api.types';

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
  const [categoryId, setCategoryId] = useState('none');
  const [tags, setTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [isFeatured, setIsFeatured] = useState(false);
  const [content, setContent] = useState('');

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

  // Load article data
  useEffect(() => {
    if (article) {
      setTitle(article.title);
      setSlug(article.slug || '');
      setExcerpt(article.excerpt || '');
      setCategoryId(article.category_id || 'none');
      setTags(article.tags || []);
      setIsPublic(article.is_public || false);
      setIsFeatured(article.is_featured || false);
      setContent(article.content || '');
    }
  }, [article]);

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
    onError: (error: Error) => {
      toast({
        title: 'Save failed',
        description: error.message || 'Unable to save the article. Please try again.',
        variant: 'destructive'
      });
    }
  });

  const handleSave = () => {
    if (!title || !slug || !content) {
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
      content,
      excerpt: excerpt || undefined,
      category_id: categoryId === 'none' ? undefined : categoryId || undefined,
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
                <Label htmlFor="title">Title</Label>
                <Input
                  id="title"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Enter article title"
                  className="text-lg"
                />
              </div>

              {/* Slug */}
              <div>
                <Label htmlFor="slug">Slug</Label>
                <Input
                  id="slug"
                  value={slug}
                  onChange={(e) => setSlug(e.target.value)}
                  placeholder="article-url-slug"
                />
                <p className="text-sm text-muted-foreground mt-1">
                  URL: /kb/public/{slug || 'article-url-slug'}
                </p>
              </div>

              {/* Content Editor */}
              <div className="space-y-2">
                <Label>Content</Label>
                <div className="border rounded-md overflow-hidden">
                  <DefaultEditor 
                    value={content} 
                    onChange={(e) => setContent(e.target.value)}
                    placeholder="Write your article content here..."
                    containerProps={{
                      style: {
                        minHeight: '400px',
                        maxHeight: '600px',
                        overflowY: 'auto'
                      }
                    }}
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
                  <SelectItem value="none">No category</SelectItem>
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
                <Button type="button" onClick={addTag} size="sm">
                  Add
                </Button>
              </div>
              <div className="flex flex-wrap gap-2">
                {tags.map(tag => (
                  <Badge 
                    key={tag} 
                    variant="secondary" 
                    className="cursor-pointer"
                    onClick={() => removeTag(tag)}
                  >
                    {tag} ×
                  </Badge>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Article Info */}
          {article && (
            <Card>
              <CardHeader>
                <CardTitle className="text-sm">Article Info</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Views</span>
                  <span>{article.views || 0}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Helpful</span>
                  <span>{article.helpful_yes || 0}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Created</span>
                  <span>{article.date_created ? new Date(article.date_created).toLocaleDateString() : 'Unknown'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Updated</span>
                  <span>{article.date_modified ? new Date(article.date_modified).toLocaleDateString() : 'Unknown'}</span>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}