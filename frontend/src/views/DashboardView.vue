<script setup lang="ts">
import { onMounted, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { resumenMock } from '@/data/mock'
import type { ResumenDashboard } from '@/types'

const resumen = ref<ResumenDashboard>(resumenMock)
const loading = ref(true)
const usingMock = ref(false)

function formatFecha(iso: string) {
  return new Date(iso).toLocaleString('es-CL', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

const estadoBadge: Record<string, string> = {
  activo: 'bg-biblioteca-100 text-biblioteca-700',
  atrasado: 'bg-red-100 text-red-700',
  devuelto: 'bg-biblioteca-50 text-biblioteca-500',
}

onMounted(async () => {
  try {
    const { data } = await api.get<ResumenDashboard>('/dashboard/resumen')
    resumen.value = data
  } catch {
    usingMock.value = true
    resumen.value = resumenMock
  } finally {
    loading.value = false
  }
})

const tarjetas = [
  { key: 'usuariosActivos', label: 'Usuarios activos', color: 'text-biblioteca-700' },
  { key: 'entradasHoy', label: 'Entradas hoy', color: 'text-biblioteca-700' },
  { key: 'personasEnSala', label: 'Personas en sala', color: 'text-acento-600' },
  { key: 'prestamosActivos', label: 'Préstamos activos', color: 'text-biblioteca-700' },
  { key: 'prestamosAtrasados', label: 'Préstamos atrasados', color: 'text-red-600' },
] as const
</script>

<template>
  <StaffLayout>
    <div class="mb-5 sm:mb-6">
      <h1 class="text-xl sm:text-2xl font-serif font-semibold text-biblioteca-900">Inicio</h1>
      <p class="text-sm text-biblioteca-500 mt-0.5">Resumen general del sistema de biblioteca</p>
      <p v-if="usingMock" class="mt-2 text-xs inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
        <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
        Mostrando datos de ejemplo (API no disponible)
      </p>
    </div>

    <!-- Tarjetas de métricas: 2 col mobile, 3 sm, 5 lg -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6 sm:mb-8">
      <div
        v-for="t in tarjetas"
        :key="t.key"
        class="bg-white border border-biblioteca-200 rounded-xl p-4 sm:p-5"
      >
        <p class="text-xs sm:text-sm text-biblioteca-500 mb-1.5">{{ t.label }}</p>
        <p class="text-2xl sm:text-3xl font-serif font-semibold" :class="t.color">
          {{ resumen[t.key] }}
        </p>
      </div>
    </div>

    <!-- Listas: 1 col mobile, 2 col desde lg -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
      <div class="bg-white border border-biblioteca-200 rounded-xl overflow-hidden">
        <div class="px-4 sm:px-5 py-3.5 border-b border-biblioteca-100">
          <h3 class="font-serif font-semibold text-biblioteca-900">Últimas entradas</h3>
        </div>
        <ul class="divide-y divide-biblioteca-100">
          <li
            v-for="e in resumen.ultimasEntradas"
            :key="e.id"
            class="px-4 sm:px-5 py-3 flex items-center justify-between gap-3"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-biblioteca-900 truncate">
                {{ e.usuario?.nombre }} {{ e.usuario?.apellido }}
              </p>
              <p class="text-xs text-biblioteca-500">{{ e.usuario?.rut }} · {{ e.via === 'qr' ? 'QR' : 'Manual' }}</p>
            </div>
            <span class="text-xs text-biblioteca-500 shrink-0">{{ formatFecha(e.fecha_hora_entrada) }}</span>
          </li>
          <li v-if="!resumen.ultimasEntradas.length" class="px-5 py-6 text-center text-sm text-biblioteca-400">
            Sin registros de entrada aún.
          </li>
        </ul>
      </div>

      <div class="bg-white border border-biblioteca-200 rounded-xl overflow-hidden">
        <div class="px-4 sm:px-5 py-3.5 border-b border-biblioteca-100">
          <h3 class="font-serif font-semibold text-biblioteca-900">Últimos préstamos</h3>
        </div>
        <ul class="divide-y divide-biblioteca-100">
          <li
            v-for="p in resumen.ultimosPrestamos"
            :key="p.id"
            class="px-4 sm:px-5 py-3 flex items-center justify-between gap-3"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-biblioteca-900 truncate">{{ p.libro_titulo }}</p>
              <p class="text-xs text-biblioteca-500 truncate">{{ p.usuario?.nombre }} {{ p.usuario?.apellido }}</p>
            </div>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0" :class="estadoBadge[p.estado]">
              {{ p.estado }}
            </span>
          </li>
          <li v-if="!resumen.ultimosPrestamos.length" class="px-5 py-6 text-center text-sm text-biblioteca-400">
            Sin préstamos registrados aún.
          </li>
        </ul>
      </div>
    </div>
  </StaffLayout>
</template>
