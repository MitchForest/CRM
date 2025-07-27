import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Search, ThumbsUp, ThumbsDown, Home, ChevronRight, Book } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { knowledgeBaseService } from '@/services/knowledgeBase.service';
import ReactMarkdown from 'react-markdown';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';

export function KnowledgeBasePublic() {
  const { slug } = useParams();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategoryId, setSelectedCategoryId] = useState<string | null>(null);
  const [hasRated, setHasRated] = useState(false);

  // Fetch categories
  const { data: categories = [] } = useQuery({
    queryKey: ['kb-categories-public'],
    queryFn: () => knowledgeBaseService.getCategories()
  });

  // Fetch featured articles
  const { data: featuredArticles = [] } = useQuery({
    queryKey: ['kb-articles-featured'],
    queryFn: () => knowledgeBaseService.getFeaturedArticles(6)
  });

  // Search articles
  const { data: searchResults, isLoading: isSearching } = useQuery({
    queryKey: ['kb-search', searchQuery, selectedCategoryId],
    queryFn: () => knowledgeBaseService.searchArticles(searchQuery, {
      limit: 20,
      category_id: selectedCategoryId || undefined,
      is_public: true
    }),
    enabled: searchQuery.length > 2
  });

  // Fetch specific article if slug provided
  const { data: article } = useQuery({
    queryKey: ['kb-article-public', slug],
    queryFn: async () => {
      const article = await knowledgeBaseService.getPublicArticle(slug!);
      // Track view
      await knowledgeBaseService.trackView(article.id);
      return article;
    },
    enabled: !!slug
  });

  // Fetch related articles
  const { data: relatedArticles = [] } = useQuery({
    queryKey: ['kb-articles-related', article?.id],
    queryFn: () => knowledgeBaseService.getRelatedArticles(article!.id, 5),
    enabled: !!article?.id
  });

  // Rate article mutation
  const rateMutation = useMutation({
    mutationFn: ({ articleId, helpful }: { articleId: string; helpful: boolean }) =>
      knowledgeBaseService.rateArticle(articleId, helpful),
    onSuccess: () => {
      setHasRated(true);
    }
  });

  // Generate table of contents from article content
  const tableOfContents = article ? knowledgeBaseService.generateTableOfContents(article.content) : [];

  // If showing article detail
  if (slug && article) {
    return (
      <div className="min-h-screen bg-background">
        {/* Header */}
        <header className="border-b">
          <div className="container mx-auto px-4 py-4">
            <nav className="flex items-center space-x-2 text-sm">
              <a href="/kb/public" className="flex items-center hover:text-primary">
                <Home className="h-4 w-4" />
              </a>
              <ChevronRight className="h-4 w-4 text-muted-foreground" />
              {article.category_name && (
                <>
                  <a 
                    href={`/kb/public?category=${article.category_id}`}
                    className="hover:text-primary"
                  >
                    {article.category_name}
                  </a>
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                </>
              )}
              <span className="text-muted-foreground truncate">{article.title}</span>
            </nav>
          </div>
        </header>

        <div className="container mx-auto px-4 py-8">
          <div className="grid grid-cols-12 gap-8">
            {/* Table of Contents */}
            {tableOfContents.length > 0 && (
              <aside className="col-span-3 hidden lg:block">
                <Card className="sticky top-8">
                  <CardHeader>
                    <CardTitle className="text-sm">Table of Contents</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <nav className="space-y-2">
                      {tableOfContents.map((heading) => (
                        <a
                          key={heading.id}
                          href={`#${heading.id}`}
                          className={cn(
                            "block text-sm hover:text-primary transition-colors",
                            heading.level === 1 && "font-semibold",
                            heading.level === 2 && "ml-4",
                            heading.level === 3 && "ml-8 text-muted-foreground"
                          )}
                        >
                          {heading.text}
                        </a>
                      ))}
                    </nav>
                  </CardContent>
                </Card>
              </aside>
            )}

            {/* Article Content */}
            <article className={cn(
              "col-span-12",
              tableOfContents.length > 0 && "lg:col-span-6"
            )}>
              <div className="mb-6">
                <h1 className="text-4xl font-bold mb-4">{article.title}</h1>
                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                  <span>By {article.author_name}</span>
                  <span>•</span>
                  <span>{article.date_modified ? formatDistanceToNow(new Date(article.date_modified), { addSuffix: true }) : 'Recently'}</span>
                  <span>•</span>
                  <span>{article.views} views</span>
                </div>
                {article.tags && article.tags.length > 0 && (
                  <div className="flex gap-2 mt-4">
                    {article.tags.map(tag => (
                      <Badge key={tag} variant="secondary">{tag}</Badge>
                    ))}
                  </div>
                )}
              </div>

              <div className="prose prose-lg dark:prose-invert max-w-none">
                <ReactMarkdown>
                  {article.content}
                </ReactMarkdown>
              </div>

              {/* Article Rating */}
              <Card className="mt-8">
                <CardContent className="pt-6">
                  <div className="text-center">
                    <p className="text-lg font-medium mb-4">Was this article helpful?</p>
                    {hasRated ? (
                      <p className="text-muted-foreground">Thank you for your feedback!</p>
                    ) : (
                      <div className="flex items-center justify-center gap-4">
                        <Button
                          variant="outline"
                          onClick={() => rateMutation.mutate({ articleId: article.id, helpful: true })}
                          disabled={rateMutation.isPending}
                        >
                          <ThumbsUp className="mr-2 h-4 w-4" />
                          Yes ({article.helpful_yes || 0})
                        </Button>
                        <Button
                          variant="outline"
                          onClick={() => rateMutation.mutate({ articleId: article.id, helpful: false })}
                          disabled={rateMutation.isPending}
                        >
                          <ThumbsDown className="mr-2 h-4 w-4" />
                          No ({article.helpful_no || 0})
                        </Button>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            </article>

            {/* Related Articles */}
            {relatedArticles.length > 0 && (
              <aside className={cn(
                "col-span-12",
                tableOfContents.length > 0 && "lg:col-span-3"
              )}>
                <Card>
                  <CardHeader>
                    <CardTitle className="text-sm">Related Articles</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-3">
                      {relatedArticles.map(related => (
                        <a
                          key={related.id}
                          href={`/kb/public/${related.slug}`}
                          className="block hover:text-primary"
                        >
                          <h4 className="font-medium text-sm">{related.title}</h4>
                          {related.excerpt && (
                            <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                              {related.excerpt}
                            </p>
                          )}
                        </a>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              </aside>
            )}
          </div>
        </div>
      </div>
    );
  }

  // Knowledge Base Home
  return (
    <div className="min-h-screen bg-background">
      {/* Hero Section */}
      <section className="bg-muted/50 py-16">
        <div className="container mx-auto px-4 text-center">
          <h1 className="text-4xl font-bold mb-4">How can we help you?</h1>
          <p className="text-xl text-muted-foreground mb-8">
            Search our knowledge base or browse by category
          </p>
          
          {/* Search Bar */}
          <div className="max-w-2xl mx-auto relative">
            <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 h-5 w-5 text-muted-foreground" />
            <Input
              type="search"
              placeholder="Search for articles..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-12 pr-4 py-6 text-lg"
            />
          </div>
        </div>
      </section>

      <div className="container mx-auto px-4 py-12">
        {/* Search Results */}
        {searchQuery.length > 2 && (
          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">
              Search Results {isSearching && '...'}
            </h2>
            {searchResults && searchResults.length > 0 ? (
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {searchResults.map((result) => (
                  <Card key={result.article?.id || result.id} className="hover:shadow-md transition-shadow">
                    <CardHeader>
                      <a href={`/kb/public/${result.article?.slug || result.id}`}>
                        <CardTitle className="text-lg hover:text-primary">
                          {result.article?.title || result.title}
                        </CardTitle>
                      </a>
                    </CardHeader>
                    <CardContent>
                      <p className="text-sm text-muted-foreground line-clamp-3">
                        {result.article?.excerpt || result.content || 'No excerpt available'}
                      </p>
                      {result.similarity && (
                        <div className="mt-2">
                          <Badge variant="secondary" className="text-xs">
                            {Math.round(result.similarity * 100)}% match
                          </Badge>
                        </div>
                      )}
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : !isSearching ? (
              <p className="text-muted-foreground">No articles found matching your search.</p>
            ) : null}
            <Separator className="mt-8" />
          </section>
        )}

        {/* Categories */}
        <section className="mb-12">
          <h2 className="text-2xl font-bold mb-6">Browse by Category</h2>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {categories.map(category => (
              <Card 
                key={category.id} 
                className={cn(
                  "cursor-pointer hover:shadow-md transition-all",
                  selectedCategoryId === category.id && "ring-2 ring-primary"
                )}
                onClick={() => setSelectedCategoryId(
                  selectedCategoryId === category.id ? null : category.id
                )}
              >
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    {category.icon && <span className="text-2xl">{category.icon}</span>}
                    <span>{category.name}</span>
                  </CardTitle>
                  {category.description && (
                    <p className="text-sm text-muted-foreground mt-2">
                      {category.description}
                    </p>
                  )}
                </CardHeader>
                <CardContent>
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Book className="h-4 w-4" />
                    <span>{category.article_count} articles</span>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>

        {/* Featured Articles */}
        {!searchQuery && featuredArticles.length > 0 && (
          <section>
            <h2 className="text-2xl font-bold mb-6">Featured Articles</h2>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {featuredArticles.map(article => (
                <Card key={article.id} className="hover:shadow-md transition-shadow">
                  <CardHeader>
                    <a href={`/kb/public/${article.slug}`}>
                      <CardTitle className="text-lg hover:text-primary flex items-center gap-2">
                        {article.title}
                        <Badge variant="secondary" className="ml-auto">Featured</Badge>
                      </CardTitle>
                    </a>
                  </CardHeader>
                  <CardContent>
                    <p className="text-sm text-muted-foreground line-clamp-3">
                      {article.excerpt || 'No excerpt available'}
                    </p>
                    <div className="flex items-center gap-4 mt-4 text-xs text-muted-foreground">
                      <span>{article.views} views</span>
                      {article.category_name && (
                        <>
                          <span>•</span>
                          <span>{article.category_name}</span>
                        </>
                      )}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  );
}