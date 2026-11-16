interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Turn a failed API call into messages for the user: the validation messages when there are any,
 * otherwise the error message of the API.
 */
export function apiErrorMessages(error: unknown): string[] {
  const body = (error as { data?: ApiErrorBody } | null)?.data

  if (body?.errors) {
    return Object.values(body.errors).flat()
  }

  return [body?.message ?? 'Something went wrong. Please try again.']
}
