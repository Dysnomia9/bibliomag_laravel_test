<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { resumenMock } from '@/data/mock'
import { STAFF_SHORTCUTS } from '@/composables/useStaffShortcuts'
import type { ResumenDashboard } from '@/types'

const router = useRouter()
const resumen = ref<ResumenDashboard>(resumenMock)
const usingMock = ref(false)
const aforo = 220

const shortcuts = STAFF_SHORTCUTS

onMounted(async () => {
  try {
    const { data } = await api.get<ResumenDashboard>('/dashboard/resumen')
    resumen.value = data
  } catch {
    usingMock.value = true
    resumen.value = resumenMock
  }
})
</script>

<template>
  <StaffLayout>
    <div class="flex min-h-[calc(100vh-8.5rem)] items-center px-2 sm:px-6 py-10">
      <div class="mx-auto flex w-full max-w-[1080px] flex-col justify-center">
        <div class="mb-12 flex justify-center">
          <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-8 py-4 shadow-sm">
            <div class="h-3 w-3 animate-pulse rounded-full bg-emerald-500" />
            <div class="text-center">
              <div class="text-[11px] font-medium uppercase tracking-widest text-gray-500">Personas en biblioteca</div>
              <div class="mt-1 flex items-baseline justify-center gap-1">
                <span class="text-4xl font-semibold tabular-nums">{{ resumen.personasEnSala }}</span>
                <span class="text-lg text-gray-400">/ {{ aforo }}</span>
              </div>
            </div>
            <div class="mx-2 h-12 w-px bg-gray-200" />
            <div class="text-center">
              <div class="text-[11px] font-medium uppercase tracking-widest text-gray-500">Entradas hoy</div>
              <div class="mt-1 text-4xl font-semibold tabular-nums">{{ resumen.entradasHoy }}</div>
            </div>
          </div>
        </div>

        <p v-if="usingMock" class="mb-6 text-center text-xs">
          <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
            <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
            Mostrando datos de ejemplo (API no disponible)
          </span>
        </p>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
          <button
            v-for="shortcut in shortcuts"
            :key="shortcut.key"
            @click="router.push({ name: shortcut.name })"
            class="group flex min-h-[260px] flex-col items-center justify-center rounded-xl border-2 border-gray-200 bg-white p-8 transition-all hover:border-indigo-600 hover:bg-indigo-50/30 hover:shadow-lg active:scale-[0.98]"
          >
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 transition-colors group-hover:bg-indigo-600 group-hover:text-white">
              <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" :d="shortcut.icon" />
              </svg>
            </div>
            <div class="text-center text-[17px] font-semibold text-gray-900">{{ shortcut.label }}</div>
            <div class="mt-1 text-center text-[12px] text-gray-500">{{ shortcut.desc }}</div>
            <kbd class="mt-4 rounded border border-gray-200 bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-500">
              {{ shortcut.key }}
            </kbd>
          </button>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
