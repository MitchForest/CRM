/**
 * Utility functions for error handling
 */

import type { AxiosError } from 'axios'

interface ApiErrorResponse {
  message?: string
  error?: string
  code?: string
  details?: unknown
}

/**
 * Type guard to check if error is an Axios error
 */
export function isAxiosError(error: unknown): error is AxiosError<ApiErrorResponse> {
  return (
    error instanceof Error &&
    'isAxiosError' in error &&
    (error as AxiosError).isAxiosError === true
  )
}

/**
 * Extract error message from various error types
 */
export function getErrorMessage(error: unknown, defaultMessage: string): string {
  if (isAxiosError(error)) {
    // Try different possible error message locations
    return (
      error.response?.data?.message ||
      error.response?.data?.error ||
      error.message ||
      defaultMessage
    )
  }
  
  if (error instanceof Error) {
    return error.message
  }
  
  if (typeof error === 'string') {
    return error
  }
  
  return defaultMessage
}

/**
 * Extract error code from various error types
 */
export function getErrorCode(error: unknown): string | undefined {
  if (isAxiosError(error)) {
    return error.response?.data?.code || error.code
  }
  
  return undefined
}