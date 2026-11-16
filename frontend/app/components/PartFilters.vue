<script setup lang="ts">
import type { Brand, PartCondition, PartSort } from '~/types/parts'

defineProps<{
  brands: Brand[]
  total?: number
}>()

const { q, brands: selectedBrands, condition, sort, hasFilters, change, reset } = usePartFilters()

const conditionOptions: { label: string, value: PartCondition | undefined }[] = [
  { label: 'All', value: undefined },
  { label: 'New', value: 'new' },
  { label: 'Used', value: 'used' }
]
const sortItems = [
  { label: 'Name', value: 'name' },
  { label: 'Price: low to high', value: 'price_asc' },
  { label: 'Price: high to low', value: 'price_desc' }
]

const search = ref(q.value ?? '')
let timer: ReturnType<typeof setTimeout> | undefined

watch(q, (value) => {
  search.value = value ?? ''
})

function onSearch(value: string) {
  search.value = value
  clearTimeout(timer)
  timer = setTimeout(() => change({ q: value.trim() || undefined }, { replace: true }), 300)
}

function toggleBrand(name: string) {
  change({
    brand: selectedBrands.value.includes(name)
      ? selectedBrands.value.filter(brand => brand !== name)
      : [...selectedBrands.value, name]
  })
}

function onSort(value: string) {
  change({ sort: value === 'name' ? undefined : value as NonNullable<PartSort> })
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div
    class="mb-6 space-y-4"
    data-testid="part-filters"
  >
    <UInput
      :model-value="search"
      icon="i-lucide-search"
      placeholder="Search by name or OEM number"
      aria-label="Search parts"
      size="lg"
      class="w-full"
      data-testid="filter-search"
      @update:model-value="onSearch"
    />

    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
      <div
        class="flex flex-wrap gap-2"
        role="group"
        aria-label="Brand"
      >
        <UButton
          v-for="brand in brands"
          :key="brand.name"
          :color="selectedBrands.includes(brand.name) ? 'primary' : 'neutral'"
          :variant="selectedBrands.includes(brand.name) ? 'solid' : 'outline'"
          :aria-pressed="selectedBrands.includes(brand.name)"
          size="sm"
          data-testid="filter-brand"
          :data-brand="brand.name"
          @click="toggleBrand(brand.name)"
        >
          {{ brand.name }}
          <span class="text-xs opacity-70">{{ brand.count }}</span>
        </UButton>
      </div>

      <UFieldGroup
        role="group"
        aria-label="Condition"
        data-testid="filter-condition"
      >
        <UButton
          v-for="option in conditionOptions"
          :key="option.label"
          :label="option.label"
          :color="condition === option.value ? 'primary' : 'neutral'"
          :variant="condition === option.value ? 'solid' : 'outline'"
          :aria-pressed="condition === option.value"
          size="sm"
          :data-condition="option.value ?? 'all'"
          @click="change({ condition: option.value })"
        />
      </UFieldGroup>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3 text-sm text-muted">
        <span
          v-if="total !== undefined"
          data-testid="filter-total"
        >
          {{ total }} parts
        </span>
        <UButton
          v-if="hasFilters"
          label="Reset filters"
          icon="i-lucide-x"
          color="neutral"
          variant="ghost"
          size="xs"
          data-testid="filter-reset"
          @click="reset()"
        />
      </div>

      <div class="flex items-center gap-2 text-sm text-muted">
        <label for="part-sort">Sort</label>
        <USelect
          id="part-sort"
          :model-value="sort ?? 'name'"
          :items="sortItems"
          size="sm"
          class="w-48"
          data-testid="filter-sort"
          @update:model-value="onSort"
        />
      </div>
    </div>
  </div>
</template>
