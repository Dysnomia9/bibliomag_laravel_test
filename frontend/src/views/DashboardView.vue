<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { resumenMock } from '@/data/mock'
import type { ResumenDashboard } from '@/types'

const router = useRouter()
const resumen = ref<ResumenDashboard>(resumenMock)
const usingMock = ref(false)
const aforo = 220

const shortcuts = [
  { key: 'F1', label: 'Registrar Entrada', desc: 'Ingreso por RUT o QR', name: 'entrada', icon: 'M11 16l-4-4m0 0l4-4m-4 4h14M5 5v14' },
  { key: 'F2', label: 'Préstamo Libro', desc: 'Préstamo y devolución', name: 'prestamo', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { key: 'F3', label: 'Reservar Sala', desc: 'Salas y logias de estudio', name: 'salas', icon: 'M4 6h16M4 12h16M4 18h7' },
  { key: 'F4', label: 'Usuario Externo', desc: 'Visitantes y comunidad', name: 'usuarios', icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3a4 4 0 00-3-3.87M9 12a4 4 0 100-8 4 4 0 000 8z' },
] as const

const shortcutMap: Record<string, string> = {
  F1: 'entrada',
  F2: 'prestamo',
  F3: 'salas',
  F4: 'usuarios',
}

function onKeyDown(event: KeyboardEvent) {
  const destino = shortcutMap[event.key]
  if (destino) {
    event.preventDefault()
    router.push({ name: destino })
  }
}

onMounted(async () => {
  window.addEventListener('keydown', onKeyDown)
  try {
    const { data } = await api.get<ResumenDashboard>('/dashboard/resumen')
    resumen.value = data
  } catch {
    usingMock.value = true
    resumen.value = resumenMock
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeyDown)
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
