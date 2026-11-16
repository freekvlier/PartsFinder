<script setup lang="ts">
import type { QuoteLine } from '~/types/quotes'

const { quote, isOpen, pending, changeQuantity, removeLine, submit, startNew } = useQuote()
const { errors, run } = useApiAction()

const isSubmitted = computed(() => quote.value?.status === 'submitted')

function updateQuantity(line: QuoteLine, value: number | null | undefined) {
    if (value && value !== line.quantity) {
        run(() => changeQuantity(line.id, value))
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <UEmpty
            v-if="!quote?.lines.length"
            icon="i-lucide-file-text"
            title="Your quote is empty"
            description="Add parts from the overview to build a quote."
            data-testid="quote-empty"
        />

        <template v-else>
            <UBadge
                v-if="isSubmitted"
                label="Submitted"
                color="success"
                variant="subtle"
                class="self-start"
                data-testid="quote-submitted"
            />

            <ul class="divide-y divide-default">
                <li
                    v-for="line in quote.lines"
                    :key="line.id"
                    class="flex gap-3 py-3"
                    data-testid="quote-line"
                >
                    <img
                        :src="line.image"
                        :alt="line.name"
                        class="size-16 shrink-0 rounded object-cover"
                    >

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <NuxtLink
                            :to="`/parts/${line.part_id}`"
                            class="truncate font-medium hover:underline"
                            @click="isOpen = false"
                        >
                            {{ line.name }}
                        </NuxtLink>
                        <p class="text-xs text-muted">
                            OEM {{ line.oem }}<span v-if="line.colour"> · {{ line.colour }}</span>
                        </p>
                        <UBadge
                            v-if="line.backorder"
                            label="Backorder"
                            color="warning"
                            variant="subtle"
                            class="self-start"
                        />

                        <div class="mt-1 flex items-center justify-between gap-2">
                            <UInputNumber
                                v-if="!isSubmitted"
                                :model-value="line.quantity"
                                :min="1"
                                size="sm"
                                class="w-28"
                                :aria-label="`Quantity of ${line.name}`"
                                @update:model-value="updateQuantity(line, $event)"
                            />
                            <span
                                v-else
                                class="text-sm"
                            >
                                {{ line.quantity }} × {{ formatPrice(line.unit_price_cents) }}
                            </span>

                            <span class="font-medium">
                                {{ formatPrice(line.line_total_cents) }}
                            </span>

                            <UButton
                                v-if="!isSubmitted"
                                icon="i-lucide-trash-2"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                :aria-label="`Remove ${line.name}`"
                                :disabled="pending"
                                @click="run(() => removeLine(line.id))"
                            />
                        </div>
                    </div>
                </li>
            </ul>

            <UAlert
                v-for="warning in quote.warnings"
                :key="warning.code"
                color="warning"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                :description="warning.message"
                data-testid="quote-warning"
            />

            <dl
                class="space-y-1 text-sm"
                data-testid="quote-totals"
            >
                <div class="flex justify-between">
                    <dt class="text-muted">
                        Subtotal
                    </dt>
                    <dd>{{ formatPrice(quote.totals.subtotal_cents) }}</dd>
                </div>
                <div
                    v-if="quote.totals.discount_cents > 0"
                    class="flex justify-between"
                    data-testid="quote-discount"
                >
                    <dt class="text-muted">
                        Discount ({{ quote.discount_percentage }}%)
                    </dt>
                    <dd>-{{ formatPrice(quote.totals.discount_cents) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">
                        VAT ({{ quote.vat_percentage }}%)
                    </dt>
                    <dd>{{ formatPrice(quote.totals.vat_cents) }}</dd>
                </div>
                <div class="flex justify-between border-t border-default pt-2 text-base font-semibold">
                    <dt>Total</dt>
                    <dd>{{ formatPrice(quote.totals.total_cents) }}</dd>
                </div>
            </dl>
        </template>

        <UAlert
            v-if="errors.length"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            data-testid="quote-errors"
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
            v-if="isSubmitted"
            label="Start new quote"
            icon="i-lucide-plus"
            block
            :loading="pending"
            @click="run(startNew)"
        />
        <UButton
            v-else-if="quote?.lines.length"
            label="Submit quote"
            icon="i-lucide-send"
            block
            :loading="pending"
            @click="run(submit)"
        />
    </div>
</template>
