<script setup lang="ts">
import type { PartResponse } from '~/types/parts'

const route = useRoute()
const { data, error } = await useFetch<PartResponse>(() => `/api/parts/${route.params.id}`)
const part = computed(() => data.value?.data)

if (import.meta.server && error.value) {
    setResponseStatus(useRequestEvent()!, 404)
}

useHead({ title: () => part.value?.name ?? 'Part' })

const { addLine, isOpen, pending } = useQuote()

const quantity = ref(1)
const colour = ref<string>()
const errors = ref<string[]>([])

const needsColour = computed(() => (part.value?.colours.length ?? 0) > 0)

async function addToQuote() {
    errors.value = []

    try {
        await addLine(part.value!.id, quantity.value, colour.value)
        isOpen.value = true
    } catch (failure) {
        errors.value = apiErrorMessages(failure)
    }
}
</script>

<template>
    <UContainer class="py-8">
        <UButton
            to="/"
            icon="i-lucide-arrow-left"
            label="All parts"
            color="neutral"
            variant="link"
            class="mb-4 -ms-2.5"
        />

        <UAlert
            v-if="error || !part"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Part not found"
            description="This part does not exist or could not be loaded."
            data-testid="part-error"
        />

        <div
            v-else
            class="grid gap-8 md:grid-cols-2"
        >
            <img
                :src="part.image"
                :alt="part.name"
                class="aspect-4/3 w-full rounded-lg object-cover"
            >

            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <UBadge
                        :label="part.brand"
                        color="neutral"
                        variant="subtle"
                    />
                    <UBadge
                        :label="part.category"
                        color="neutral"
                        variant="outline"
                    />
                    <UBadge
                        :label="part.condition === 'new' ? 'New' : 'Used'"
                        :color="part.condition === 'new' ? 'primary' : 'warning'"
                        variant="subtle"
                    />
                </div>

                <h1 class="text-2xl font-semibold">
                    {{ part.name }}
                </h1>
                <p class="text-muted">
                    OEM {{ part.oem }}
                </p>

                <div>
                    <p class="text-3xl font-semibold">
                        {{ formatPrice(part.priceCents) }}
                    </p>
                    <p class="text-sm text-muted">
                        excl. VAT
                    </p>
                </div>

                <UBadge
                    :label="part.inStock ? `In stock (${part.stockQuantity})` : 'Out of stock'"
                    :color="part.inStock ? 'success' : 'error'"
                    variant="subtle"
                    class="self-start"
                    data-testid="part-stock"
                />
                <p
                    v-if="!part.inStock"
                    class="text-sm text-muted"
                >
                    This part can still be added to a quote and will be backordered.
                </p>

                <form
                    class="mt-2 flex flex-col gap-4"
                    @submit.prevent="addToQuote"
                >
                    <UFormField
                        v-if="needsColour"
                        label="Colour"
                        required
                    >
                        <USelect
                            v-model="colour"
                            :items="part.colours"
                            placeholder="Choose a colour"
                            class="w-full"
                            data-testid="part-colour"
                        />
                    </UFormField>

                    <UFormField label="Quantity">
                        <UInputNumber
                            v-model="quantity"
                            :min="1"
                            class="w-40"
                            data-testid="part-quantity"
                        />
                    </UFormField>

                    <UAlert
                        v-if="errors.length"
                        color="error"
                        variant="subtle"
                        icon="i-lucide-circle-alert"
                        data-testid="part-add-errors"
                    >
                        <template #description>
                            <p
                                v-for="message in errors"
                                :key="message"
                            >
                                {{ message }}
                            </p>
                        </template>
                    </UAlert>

                    <UButton
                        type="submit"
                        label="Add to quote"
                        icon="i-lucide-plus"
                        size="lg"
                        :disabled="needsColour && !colour"
                        :loading="pending"
                    />
                </form>
            </div>
        </div>
    </UContainer>
</template>
