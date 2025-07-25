import { toast as sonnerToast } from "sonner"

interface ToastProps {
  title?: string
  description?: string
  variant?: "default" | "destructive"
  duration?: number
}

export const useToast = () => {
  const toast = ({ title, description, variant = "default", duration = 4000 }: ToastProps) => {
    const message = title || description || ""
    const descriptionText = title && description ? description : undefined

    if (variant === "destructive") {
      sonnerToast.error(message, {
        description: descriptionText,
        duration,
      })
    } else {
      sonnerToast.success(message, {
        description: descriptionText,
        duration,
      })
    }
  }

  return { toast }
}