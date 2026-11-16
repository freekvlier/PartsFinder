interface ApiErrorBody {
    message?: string
    errors?: Record<string, string[]>
}

/**
 * Turn a failed API call into user messages: the validation messages, otherwise the API's error message.
 */
export function apiErrorMessages(error: unknown): string[] {
    const body = (error as { data?: ApiErrorBody } | null)?.data

    if (body?.errors) {
        return Object.values(body.errors).flat()
    }

    return [body?.message ?? 'Something went wrong. Please try again.']
}
