/**
 * Run API actions from a component and keep the messages of the last failure for display.
 */
export function useApiAction() {
    const errors = ref<string[]>([])

    /**
     * Run the action; on failure its messages are kept and `undefined` is returned, so callers can act on success.
     */
    async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
        errors.value = []

        try {
            return await action()
        } catch (error) {
            errors.value = apiErrorMessages(error)
        }
    }

    return { errors, run }
}
