import { useState, useRef, useEffect } from 'react';
// import { useQueryClient } from '@tanstack/react-query'; // TODO: Use when implementing lead capture UI updates
import { MessageCircle, X, Send, Loader2, Minimize2, Maximize2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { aiService } from '@/services/ai.service';
import { activityTrackingService } from '@/services/activityTracking.service';
import ReactMarkdown from 'react-markdown';
import type { ChatMessage } from '@/types/api.types';
import { cn } from '@/lib/utils';
import { motion, AnimatePresence } from 'framer-motion';

interface LeadInfo {
  name?: string;
  email?: string;
  phone?: string;
  company?: string;
  message?: string;
}

interface ExtractedLeadInfo {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  company?: string;
  account_name?: string;
  title?: string;
  extraction_confidence?: number;
}

interface ChatWidgetProps {
  onLeadCapture?: (leadInfo: LeadInfo) => void;
  position?: 'bottom-right' | 'bottom-left';
  theme?: 'light' | 'dark' | 'auto';
  primaryColor?: string;
  greeting?: string;
}

export function ChatWidget({ 
  onLeadCapture, 
  position = 'bottom-right',
  primaryColor = '#3b82f6',
  greeting = "Hi! I'm here to help. What can I assist you with today?"
}: ChatWidgetProps) {
  // const queryClient = useQueryClient(); // TODO: Use when implementing lead capture UI updates
  const [isOpen, setIsOpen] = useState(false);
  const [isMinimized, setIsMinimized] = useState(false);
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      id: '1',
      role: 'assistant',
      content: greeting,
      timestamp: new Date().toISOString(),
    },
  ]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [hasInteracted, setHasInteracted] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages]);

  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    }
  }, [isOpen]);

  // Listen for custom chat events
  useEffect(() => {
    const handleChatOpen = (event: CustomEvent) => {
      setIsOpen(true);
      if (event.detail?.message) {
        // Set the message in input to be sent when chat opens
        setInput(event.detail.message);
        // Trigger send after a delay to ensure chat is open
        setTimeout(() => {
          const sendButton = document.querySelector('[type="submit"]') as HTMLButtonElement;
          if (sendButton && !sendButton.disabled) {
            sendButton.click();
          }
        }, 500);
      }
    };

    window.addEventListener('chat-open', handleChatOpen as EventListener);
    return () => {
      window.removeEventListener('chat-open', handleChatOpen as EventListener);
    };
  }, []);

  const sendMessage = async () => {
    if (!input.trim() || isLoading) return;

    const userMessage: ChatMessage = {
      id: Date.now().toString(),
      role: 'user',
      content: input,
      timestamp: new Date().toISOString(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setIsLoading(true);
    setHasInteracted(true);

    try {
      const response = await aiService.sendChatMessage(
        conversationId,
        input,
        activityTrackingService.getVisitorId() || undefined
      );

      setConversationId(response.conversation_id);

      const assistantMessage: ChatMessage = {
        id: Date.now().toString(),
        role: 'assistant',
        content: response.message,
        timestamp: new Date().toISOString(),
        metadata: {
          intent: response.intent as "support" | "sales" | "general" | "qualification" | undefined,
          sentiment: response.sentiment,
          suggested_actions: response.suggested_actions,
          confidence: response.confidence,
          ...response.metadata
        },
      };

      setMessages((prev) => [...prev, assistantMessage]);

      // Check if lead was captured
      if (response.metadata?.lead_captured && response.metadata?.lead_info) {
        const leadInfo = response.metadata.lead_info as ExtractedLeadInfo;
        
        // Prepare lead info for callback
        const callbackData = {
          name: [leadInfo.first_name, leadInfo.last_name].filter(Boolean).join(' ') || 'Unknown',
          email: leadInfo.email || '',
          company: leadInfo.company || leadInfo.account_name || undefined,
          phone: leadInfo.phone || undefined,
          title: leadInfo.title || undefined,
          leadId: response.metadata.lead_id,
          leadScore: response.metadata.lead_score,
          confidence: leadInfo.extraction_confidence
        };
        
        onLeadCapture?.(callbackData);
        
        // Show success notification in chat
        const notificationMessage: ChatMessage = {
          id: Date.now().toString() + '_notification',
          role: 'system',
          content: '✓ Your information has been saved. A team member will follow up with you soon.',
          timestamp: new Date().toISOString(),
        };
        setMessages((prev) => [...prev, notificationMessage]);
      }

      // Check if support ticket action was suggested
      if (response.intent === 'support' && response.suggested_actions?.some(action => action.includes('support ticket'))) {
        // Automatically offer to create ticket
        const ticketMessage: ChatMessage = {
          id: Date.now().toString() + '_ticket_offer',
          role: 'assistant',
          content: 'Would you like me to create a support ticket for you? Just describe your issue and I\'ll handle it right away.',
          timestamp: new Date().toISOString(),
          metadata: {
            suggested_actions: ['Yes, create a ticket', 'No, I\'ll browse the knowledge base']
          }
        };
        setMessages((prev) => [...prev, ticketMessage]);
      }

      // Track engagement
      activityTrackingService.trackEvent({
        type: 'chat_message_sent',
        data: { conversation_id: response.conversation_id, visitor_id: activityTrackingService.getVisitorId() || 'anonymous' },
        timestamp: new Date().toISOString()
      });
    } catch (error) {
      console.error('Chat error:', error);
      const errorMessage: ChatMessage = {
        id: Date.now().toString(),
        role: 'assistant',
        content: "I'm sorry, I encountered an error. Please try again or contact support directly.",
        timestamp: new Date().toISOString(),
      };
      setMessages((prev) => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const positionClasses = {
    'bottom-right': 'bottom-4 right-4',
    'bottom-left': 'bottom-4 left-4',
  };

  const chatButtonVariants = {
    initial: { scale: 0, opacity: 0 },
    animate: { scale: 1, opacity: 1 },
    tap: { scale: 0.9 },
  };

  const chatWindowVariants = {
    initial: { opacity: 0, y: 20, scale: 0.95 },
    animate: { opacity: 1, y: 0, scale: 1 },
    exit: { opacity: 0, y: 20, scale: 0.95 },
  };

  return (
    <>
      {/* Chat Button */}
      <AnimatePresence>
        {!isOpen && (
          <motion.div
            className={cn('fixed z-50', positionClasses[position])}
            variants={chatButtonVariants}
            initial="initial"
            animate="animate"
            exit="initial"
            whileTap="tap"
          >
            <Button
              onClick={() => {
                setIsOpen(true);
                activityTrackingService.trackEvent({
                  type: 'chat_opened',
                  data: { visitor_id: activityTrackingService.getVisitorId() || 'anonymous' },
                  timestamp: new Date().toISOString()
                });
              }}
              className="h-14 w-14 rounded-full shadow-lg"
              style={{ backgroundColor: primaryColor }}
            >
              <MessageCircle className="h-6 w-6" />
            </Button>
            {!hasInteracted && (
              <div className="absolute -top-2 -right-2">
                <span className="relative flex h-3 w-3">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                </span>
              </div>
            )}
          </motion.div>
        )}
      </AnimatePresence>

      {/* Chat Window */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            className={cn(
              'fixed z-50',
              positionClasses[position],
              isMinimized ? 'h-14' : 'h-[600px] w-[400px]'
            )}
            variants={chatWindowVariants}
            initial="initial"
            animate="animate"
            exit="exit"
            transition={{ type: 'spring', damping: 25, stiffness: 300 }}
          >
            <Card className="flex flex-col h-full shadow-2xl">
              {/* Header */}
              <div 
                className="flex items-center justify-between p-4 border-b"
                style={{ backgroundColor: primaryColor }}
              >
                <div className="flex items-center gap-3 text-white">
                  <Avatar className="h-8 w-8">
                    <AvatarFallback style={{ backgroundColor: 'rgba(255,255,255,0.2)' }}>
                      AI
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <h3 className="font-semibold">AI Assistant</h3>
                    <p className="text-xs opacity-90">Always here to help</p>
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => setIsMinimized(!isMinimized)}
                    className="h-8 w-8 text-white hover:bg-white/20"
                  >
                    {isMinimized ? (
                      <Maximize2 className="h-4 w-4" />
                    ) : (
                      <Minimize2 className="h-4 w-4" />
                    )}
                  </Button>
                  <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => {
                      setIsOpen(false);
                      activityTrackingService.trackEvent({
                        type: 'chat_closed',
                        data: conversationId ? { conversation_id: conversationId, visitor_id: activityTrackingService.getVisitorId() || 'anonymous' } : { visitor_id: activityTrackingService.getVisitorId() || 'anonymous' },
                        timestamp: new Date().toISOString()
                      });
                    }}
                    className="h-8 w-8 text-white hover:bg-white/20"
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              {!isMinimized && (
                <>
                  {/* Messages */}
                  <ScrollArea className="flex-1 p-4" ref={scrollRef}>
                    <div className="space-y-4">
                      {messages.map((message) => (
                        <motion.div
                          key={message.id}
                          initial={{ opacity: 0, y: 10 }}
                          animate={{ opacity: 1, y: 0 }}
                          transition={{ duration: 0.3 }}
                          className={cn(
                            'flex',
                            message.role === 'user' ? 'justify-end' : 
                            message.role === 'system' ? 'justify-center' : 'justify-start'
                          )}
                        >
                          <div
                            className={cn(
                              'max-w-[80%] rounded-lg px-4 py-2',
                              message.role === 'user'
                                ? 'bg-primary text-primary-foreground'
                                : message.role === 'system'
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 text-sm'
                                : 'bg-muted'
                            )}
                          >
                            {message.role === 'assistant' ? (
                              <div className="text-sm prose prose-sm dark:prose-invert max-w-none">
                                <ReactMarkdown
                                  components={{
                                    p: ({children}) => <p className="mb-2">{children}</p>,
                                    a: ({children, href}) => <a href={href} className="text-primary underline">{children}</a>
                                  }}
                                >
                                  {message.content}
                                </ReactMarkdown>
                              </div>
                            ) : (
                              <p className="text-sm">{message.content}</p>
                            )}
                            
                            {/* Suggested Actions */}
                            {message.metadata?.suggested_actions && (
                              <div className="flex flex-wrap gap-1 mt-2">
                                {message.metadata.suggested_actions.map((action: any, index: any) => (
                                  <Badge
                                    key={index}
                                    variant="secondary"
                                    className="cursor-pointer text-xs"
                                    onClick={() => setInput(action)}
                                  >
                                    {action}
                                  </Badge>
                                ))}
                              </div>
                            )}
                          </div>
                        </motion.div>
                      ))}
                      
                      {isLoading && (
                        <motion.div
                          initial={{ opacity: 0 }}
                          animate={{ opacity: 1 }}
                          className="flex justify-start"
                        >
                          <div className="bg-muted rounded-lg px-4 py-2">
                            <Loader2 className="h-4 w-4 animate-spin" />
                          </div>
                        </motion.div>
                      )}
                    </div>
                  </ScrollArea>

                  {/* Input */}
                  <div className="p-4 border-t">
                    <form
                      onSubmit={(e) => {
                        e.preventDefault();
                        sendMessage();
                      }}
                      className="flex gap-2"
                    >
                      <Input
                        ref={inputRef}
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        placeholder="Type your message..."
                        disabled={isLoading}
                        className="flex-1"
                      />
                      <Button 
                        type="submit" 
                        size="icon" 
                        disabled={isLoading || !input.trim()}
                        style={{ backgroundColor: primaryColor }}
                      >
                        <Send className="h-4 w-4" />
                      </Button>
                    </form>
                    <p className="text-xs text-muted-foreground mt-2 text-center">
                      Powered by AI • Your data is secure
                    </p>
                  </div>
                </>
              )}
            </Card>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}