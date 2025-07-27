import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Search, FolderOpen, FileText, Eye, Edit, Trash2, Star } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/ui/data-table';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogHeader, 
  DialogTitle,
  DialogFooter 
} from '@/components/ui/dialog';
import { 
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/components/ui/use-toast';
import { useNavigate } from 'react-router-dom';
import { knowledgeBaseService } from '@/services/knowledgeBase.service';
import { formatDistanceToNow } from 'date-fns';
import type { KBCategory, KBArticle } from '@/types/api.types';

export function KnowledgeBaseAdmin() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  const [showCategoryDialog, setShowCategoryDialog] = useState(false);
  const [editingCategory, setEditingCategory] = useState<KBCategory | null>(null);
  const [deleteArticleId, setDeleteArticleId] = useState<string | null>(null);
  const [deleteCategoryId, setDeleteCategoryId] = useState<string | null>(null);
  
  // Category form state
  const [categoryName, setCategoryName] = useState('');
  const [categoryDescription, setCategoryDescription] = useState('');
  const [categoryIcon, setCategoryIcon] = useState('');

  // Fetch categories
  const { data: categories = [], isLoading: isLoadingCategories } = useQuery({
    queryKey: ['kb-categories'],
    queryFn: () => knowledgeBaseService.getCategories()
  });

  // Fetch articles
  const { data: articlesResponse, isLoading: isLoadingArticles } = useQuery({
    queryKey: ['kb-articles', searchQuery, selectedCategory],
    queryFn: () => knowledgeBaseService.getArticles({
      search: searchQuery || undefined,
      category_id: selectedCategory || undefined,
      limit: 50
    })
  });

  const articles = articlesResponse?.data || [];

  // Popular articles
  const { data: popularArticles = [] } = useQuery({
    queryKey: ['kb-articles-popular'],
    queryFn: () => knowledgeBaseService.getPopularArticles({ limit: 5 })
  });

  // Create/Update category mutation
  const categoryMutation = useMutation({
    mutationFn: (data: Partial<KBCategory>) => {
      if (editingCategory) {
        return knowledgeBaseService.updateCategory(editingCategory.id, data);
      }
      return knowledgeBaseService.createCategory(data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb-categories'] });
      toast({
        title: editingCategory ? 'Category updated' : 'Category created',
        description: 'The category has been saved successfully.'
      });
      setShowCategoryDialog(false);
      resetCategoryForm();
    },
    onError: () => {
      toast({
        title: 'Error',
        description: 'Failed to save category. Please try again.',
        variant: 'destructive'
      });
    }
  });

  // Delete article mutation
  const deleteArticleMutation = useMutation({
    mutationFn: (articleId: string) => knowledgeBaseService.deleteArticle(articleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb-articles'] });
      toast({
        title: 'Article deleted',
        description: 'The article has been deleted successfully.'
      });
      setDeleteArticleId(null);
    }
  });

  // Delete category mutation
  const deleteCategoryMutation = useMutation({
    mutationFn: (categoryId: string) => knowledgeBaseService.deleteCategory(categoryId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb-categories'] });
      toast({
        title: 'Category deleted',
        description: 'The category has been deleted successfully.'
      });
      setDeleteCategoryId(null);
    }
  });

  const resetCategoryForm = () => {
    setCategoryName('');
    setCategoryDescription('');
    setCategoryIcon('');
    setEditingCategory(null);
  };

  const handleEditCategory = (category: KBCategory) => {
    setEditingCategory(category);
    setCategoryName(category.name);
    setCategoryDescription(category.description || '');
    setCategoryIcon(category.icon || '');
    setShowCategoryDialog(true);
  };

  const handleSaveCategory = () => {
    if (!categoryName.trim()) {
      toast({
        title: 'Validation error',
        description: 'Category name is required.',
        variant: 'destructive'
      });
      return;
    }

    categoryMutation.mutate({
      name: categoryName,
      description: categoryDescription || undefined,
      icon: categoryIcon || undefined
    });
  };

  const articleColumns = [
    {
      accessorKey: 'title',
      header: 'Article',
      cell: ({ row }: { row: { original: KBArticle } }) => (
        <div className="flex items-start gap-2">
          <FileText className="h-4 w-4 text-muted-foreground mt-1" />
          <div>
            <div className="font-medium">{row.original.title}</div>
            {row.original.excerpt && (
              <p className="text-sm text-muted-foreground line-clamp-2">
                {row.original.excerpt}
              </p>
            )}
          </div>
        </div>
      )
    },
    {
      accessorKey: 'category_name',
      header: 'Category',
      cell: ({ row }: { row: { original: KBArticle } }) => (
        row.original.category_name ? (
          <Badge variant="secondary">{row.original.category_name}</Badge>
        ) : null
      )
    },
    {
      accessorKey: 'is_public',
      header: 'Status',
      cell: ({ row }: { row: { original: KBArticle } }) => (
        <div className="flex items-center gap-2">
          <Badge variant={row.original.is_public ? 'default' : 'secondary'}>
            {row.original.is_public ? 'Public' : 'Draft'}
          </Badge>
          {row.original.is_featured && (
            <Star className="h-4 w-4 text-yellow-500 fill-yellow-500" />
          )}
        </div>
      )
    },
    {
      accessorKey: 'views',
      header: 'Views',
      cell: ({ row }: { row: { original: KBArticle } }) => (
        <div className="flex items-center gap-1">
          <Eye className="h-4 w-4 text-muted-foreground" />
          <span>{row.original.views || 0}</span>
        </div>
      )
    },
    {
      accessorKey: 'helpful_yes',
      header: 'Helpful',
      cell: ({ row }: { row: { original: KBArticle } }) => {
        const helpful = row.original.helpful_yes || 0;
        const notHelpful = row.original.helpful_no || 0;
        const total = helpful + notHelpful;
        const percentage = total > 0 ? Math.round((helpful / total) * 100) : 0;
        
        return total > 0 ? (
          <div className="text-sm">
            <span className="font-medium">{percentage}%</span>
            <span className="text-muted-foreground"> ({total})</span>
          </div>
        ) : (
          <span className="text-muted-foreground">-</span>
        );
      }
    },
    {
      accessorKey: 'date_modified',
      header: 'Last Updated',
      cell: ({ row }: { row: { original: KBArticle } }) => (
        <span className="text-sm text-muted-foreground">
          {formatDistanceToNow(new Date(row.original.date_modified), { addSuffix: true })}
        </span>
      )
    },
    {
      id: 'actions',
      cell: ({ row }: { row: { original: KBArticle } }) => (
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => navigate(`/kb/edit/${row.original.id}`)}
          >
            <Edit className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => window.open(`/kb/public/${row.original.slug}`, '_blank')}
          >
            <Eye className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => setDeleteArticleId(row.original.id)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      )
    }
  ];

  const totalArticles = articles.length;
  const publicArticles = articles.filter(a => a.is_public).length;
  const totalViews = articles.reduce((sum, a) => sum + (a.views || 0), 0);

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Knowledge Base</h1>
          <p className="text-muted-foreground mt-1">
            Manage articles and categories for your help center
          </p>
        </div>
        <Button onClick={() => navigate('/kb/new')}>
          <Plus className="mr-2 h-4 w-4" />
          New Article
        </Button>
      </div>

      {/* Metrics */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Articles</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totalArticles}</div>
            <p className="text-xs text-muted-foreground">
              {publicArticles} public, {totalArticles - publicArticles} draft
            </p>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Categories</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{categories.length}</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Views</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totalViews.toLocaleString()}</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Popular Articles</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{popularArticles.length}</div>
            <p className="text-xs text-muted-foreground">Last 30 days</p>
          </CardContent>
        </Card>
      </div>

      <Tabs defaultValue="articles" className="space-y-4">
        <TabsList>
          <TabsTrigger value="articles">Articles</TabsTrigger>
          <TabsTrigger value="categories">Categories</TabsTrigger>
          <TabsTrigger value="analytics">Analytics</TabsTrigger>
        </TabsList>

        {/* Articles Tab */}
        <TabsContent value="articles" className="space-y-4">
          {/* Search and Filters */}
          <div className="flex items-center gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search articles..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>
            {categories.length > 0 && (
              <select
                value={selectedCategory || ''}
                onChange={(e) => setSelectedCategory(e.target.value || null)}
                className="px-3 py-2 border rounded-md"
              >
                <option value="">All Categories</option>
                {categories.map(cat => (
                  <option key={cat.id} value={cat.id}>
                    {cat.name} ({cat.article_count})
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* Articles Table */}
          <Card>
            <CardContent className="p-0">
              {isLoadingArticles ? (
                <div className="flex items-center justify-center h-32">
                  <div className="h-6 w-6 animate-spin rounded-full border-b-2 border-gray-900" />
                </div>
              ) : articles.length === 0 ? (
                <div className="text-center py-12">
                  <FileText className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
                  <p className="text-muted-foreground">No articles found</p>
                  <Button className="mt-4" onClick={() => navigate('/kb/new')}>
                    Create your first article
                  </Button>
                </div>
              ) : (
                <DataTable columns={articleColumns} data={articles} />
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Categories Tab */}
        <TabsContent value="categories" className="space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">Categories</h3>
            <Button onClick={() => setShowCategoryDialog(true)}>
              <Plus className="mr-2 h-4 w-4" />
              New Category
            </Button>
          </div>

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {isLoadingCategories ? (
              <div className="col-span-full flex items-center justify-center h-32">
                <div className="h-6 w-6 animate-spin rounded-full border-b-2 border-gray-900" />
              </div>
            ) : categories.length === 0 ? (
              <div className="col-span-full text-center py-12">
                <FolderOpen className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
                <p className="text-muted-foreground">No categories yet</p>
                <Button className="mt-4" onClick={() => setShowCategoryDialog(true)}>
                  Create your first category
                </Button>
              </div>
            ) : (
              categories.map(category => (
                <Card key={category.id} className="cursor-pointer hover:shadow-md transition-shadow">
                  <CardHeader>
                    <div className="flex items-start justify-between">
                      <div className="flex items-center gap-2">
                        {category.icon && (
                          <span className="text-2xl">{category.icon}</span>
                        )}
                        <div>
                          <CardTitle className="text-base">{category.name}</CardTitle>
                          {category.description && (
                            <p className="text-sm text-muted-foreground mt-1">
                              {category.description}
                            </p>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-1">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={(e) => {
                            e.stopPropagation();
                            handleEditCategory(category);
                          }}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={(e) => {
                            e.stopPropagation();
                            setDeleteCategoryId(category.id);
                          }}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                      <div className="flex items-center gap-1">
                        <FileText className="h-4 w-4" />
                        <span>{category.article_count} articles</span>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))
            )}
          </div>
        </TabsContent>

        {/* Analytics Tab */}
        <TabsContent value="analytics" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Popular Articles</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {popularArticles.map((article, index) => (
                  <div key={article.id} className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-sm font-medium">
                        {index + 1}
                      </div>
                      <div>
                        <p className="font-medium">{article.title}</p>
                        <p className="text-sm text-muted-foreground">
                          {article.views} views
                        </p>
                      </div>
                    </div>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => navigate(`/kb/edit/${article.id}`)}
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Category Dialog */}
      <Dialog open={showCategoryDialog} onOpenChange={(open) => {
        setShowCategoryDialog(open);
        if (!open) resetCategoryForm();
      }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {editingCategory ? 'Edit Category' : 'Create Category'}
            </DialogTitle>
            <DialogDescription>
              Categories help organize your knowledge base articles
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Name</Label>
              <Input
                value={categoryName}
                onChange={(e) => setCategoryName(e.target.value)}
                placeholder="e.g., Getting Started"
              />
            </div>
            <div>
              <Label>Description (optional)</Label>
              <Textarea
                value={categoryDescription}
                onChange={(e) => setCategoryDescription(e.target.value)}
                placeholder="Brief description of this category"
                rows={3}
              />
            </div>
            <div>
              <Label>Icon (optional)</Label>
              <Input
                value={categoryIcon}
                onChange={(e) => setCategoryIcon(e.target.value)}
                placeholder="e.g., 📚 or 🚀"
                maxLength={2}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowCategoryDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleSaveCategory} disabled={categoryMutation.isPending}>
              {categoryMutation.isPending ? 'Saving...' : 'Save'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Article Confirmation */}
      <AlertDialog open={!!deleteArticleId} onOpenChange={() => setDeleteArticleId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. This will permanently delete the article.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteArticleId && deleteArticleMutation.mutate(deleteArticleId)}
              className="bg-destructive text-destructive-foreground"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Delete Category Confirmation */}
      <AlertDialog open={!!deleteCategoryId} onOpenChange={() => setDeleteCategoryId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. Articles in this category will not be deleted but will have no category.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteCategoryId && deleteCategoryMutation.mutate(deleteCategoryId)}
              className="bg-destructive text-destructive-foreground"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}