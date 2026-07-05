<script setup lang="ts">
import type { SerieItem } from '@/types'

withDefaults(
  defineProps<{
    data: SerieItem[]
    total: number
    columna: string
    itemColor?: (label: string) => string
  }>(),
  {},
)
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 text-left text-xs text-gray-500 uppercase tracking-wide">
          <th class="py-2 pr-3 font-medium">{{ columna }}</th>
          <th class="py-2 pr-3 font-medium text-right">Cantidad</th>
          <th class="py-2 font-medium text-right">%</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <tr v-for="d in data" :key="d.label">
          <td class="py-2 pr-3 text-gray-800">
            <span class="flex items-center gap-2">
              <span v-if="itemColor" class="inline-block w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: itemColor(d.label) }" />
              {{ d.label }}
            </span>
          </td>
          <td class="py-2 pr-3 text-right tabular-nums text-gray-700">{{ d.value }}</td>
          <td class="py-2 text-right tabular-nums text-gray-500">{{ total ? Math.round((d.value / total) * 100) : 0 }}%</td>
        </tr>
        <tr v-if="!data.length">
          <td colspan="3" class="py-6 text-center text-gray-400">Sin datos para mostrar.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
