import React, { useState } from 'react';
import { Bot, Loader2, Sparkles } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { knowledgeBaseService } from '@/services';

interface AIArticleDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: 'generate' | 'rewrite';
  articleId?: string;
  currentContent?: string;
  onGenerated?: (data: any) => void;
  categories?: Array<{ id: string; name: string }>;
}

export function AIArticleDialog({
  open,
  onOpenChange,
  mode,
  articleId,
  currentContent,
  onGenerated,
  categories = []
}: AIArticleDialogProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // Generate mode state
  const [topic, setTopic] = useState('');
  const [category, setCategory] = useState('');
  
  // Rewrite mode state
  const [instructions, setInstructions] = useState('');
  const [updateSummary, setUpdateSummary] = useState(false);
  
  // Shared state
  const [tone, setTone] = useState<string>('professional');
  const [style, setStyle] = useState<string>('informative');
  const [wordCount, setWordCount] = useState(800);

  const handleSubmit = async () => {
    setIsLoading(true);
    setError(null);
    
    try {
      if (mode === 'generate') {
        const result = await knowledgeBaseService.generateArticle({
          topic,
          tone: tone as any,
          style: style as any,
          word_count: wordCount,
          category
        });
        onGenerated?.(result);
        onOpenChange(false);
      } else if (mode === 'rewrite' && articleId) {
        const result = await knowledgeBaseService.rewriteArticle(articleId, {
          instructions,
          tone: tone as any,
          style: style as any,
          update_summary: updateSummary
        });
        onGenerated?.(result);
        onOpenChange(false);
      }
    } catch (err: any) {
      setError(err.message || 'Failed to process AI request');
    } finally {
      setIsLoading(false);
    }
  };

  const isValid = mode === 'generate' ? topic.length > 3 : instructions.length > 10;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-purple-600" />
            {mode === 'generate' ? 'Generate Article with AI' : 'Rewrite Article with AI'}
          </DialogTitle>
          <DialogDescription>
            {mode === 'generate' 
              ? 'Provide a topic and let AI create a comprehensive knowledge base article.'
              : 'Give instructions on how to improve or modify the existing article.'}
          </DialogDescription>
        </DialogHeader>
        
        <div className="space-y-4 py-4">
          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          
          {mode === 'generate' ? (
            <>
              <div className="space-y-2">
                <Label htmlFor="topic">Topic *</Label>
                <Input
                  id="topic"
                  placeholder="e.g., How to set up email automation"
                  value={topic}
                  onChange={(e) => setTopic(e.target.value)}
                />
              </div>
              
              {categories.length > 0 && (
                <div className="space-y-2">
                  <Label htmlFor="category">Category</Label>
                  <Select value={category} onValueChange={setCategory}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select a category" />
                    </SelectTrigger>
                    <SelectContent>
                      {categories.map(cat => (
                        <SelectItem key={cat.id} value={cat.name}>
                          {cat.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
            </>
          ) : (
            <>
              <div className="space-y-2">
                <Label htmlFor="instructions">Instructions *</Label>
                <Textarea
                  id="instructions"
                  placeholder="e.g., Make it more beginner-friendly, add more examples, improve the introduction..."
                  value={instructions}
                  onChange={(e) => setInstructions(e.target.value)}
                  rows={4}
                />
              </div>
              
              <div className="flex items-center space-x-2">
                <Switch
                  id="update-summary"
                  checked={updateSummary}
                  onCheckedChange={setUpdateSummary}
                />
                <Label htmlFor="update-summary">Update article summary</Label>
              </div>
            </>
          )}
          
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="tone">Tone</Label>
              <Select value={tone} onValueChange={setTone}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="professional">Professional</SelectItem>
                  <SelectItem value="casual">Casual</SelectItem>
                  <SelectItem value="technical">Technical</SelectItem>
                  <SelectItem value="friendly">Friendly</SelectItem>
                  {mode === 'rewrite' && (
                    <SelectItem value="maintain current">Maintain Current</SelectItem>
                  )}
                </SelectContent>
              </Select>
            </div>
            
            <div className="space-y-2">
              <Label htmlFor="style">Style</Label>
              <Select value={style} onValueChange={setStyle}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="informative">Informative</SelectItem>
                  <SelectItem value="tutorial">Tutorial</SelectItem>
                  <SelectItem value="guide">Guide</SelectItem>
                  <SelectItem value="reference">Reference</SelectItem>
                  {mode === 'rewrite' && (
                    <SelectItem value="maintain current">Maintain Current</SelectItem>
                  )}
                </SelectContent>
              </Select>
            </div>
          </div>
          
          {mode === 'generate' && (
            <div className="space-y-2">
              <Label htmlFor="word-count">Target Word Count</Label>
              <Input
                id="word-count"
                type="number"
                min={100}
                max={5000}
                step={100}
                value={wordCount}
                onChange={(e) => setWordCount(parseInt(e.target.value) || 800)}
              />
            </div>
          )}
        </div>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isLoading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={!isValid || isLoading}>
            {isLoading ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {mode === 'generate' ? 'Generating...' : 'Rewriting...'}
              </>
            ) : (
              <>
                <Bot className="mr-2 h-4 w-4" />
                {mode === 'generate' ? 'Generate Article' : 'Rewrite Article'}
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
} 