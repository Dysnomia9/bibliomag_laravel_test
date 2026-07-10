<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import apiUsuario from '@/services/apiUsuario'
import { useUsuarioAuthStore } from '@/stores/usuarioAuth'
import type { EstadoPortal } from '@/types'

const auth = useUsuarioAuthStore()

const personasEnSala = ref(0)
const capacidad = ref(220)
const apiError = ref(false)

const acciones = [
  {
    to: 'portal-entrada',
    titulo: 'Marcar entrada',
    detalle: 'Registra tu ingreso con QR o RUT',
    icon: 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h3m3 0h-3m0 0v3m0-3v-3',
  },
  {
    to: 'portal-catalogo',
    titulo: 'Ver catálogo',
    detalle: 'Consulta la disponibilidad de libros',
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
  },
  {
    to: 'portal-salas',
    titulo: 'Reservar logia',
    detalle: 'Solicita un bloque de estudio y gestiona tus reservas',
    icon: 'M4 6h16M4 12h16M4 18h7',
  },
]

async function cargar() {
  try {
    const { data } = await apiUsuario.get<EstadoPortal>('/mi/estado')
    personasEnSala.value = data.personasEnSala
    capacidad.value = data.capacidad
    apiError.value = false
  } catch {
    apiError.value = true
    personasEnSala.value = 0
  }
}

onMounted(cargar)
</script>

<template>
  <PortalLayout>
    <div class="max-w-lg mx-auto">
      <div class="relative overflow-hidden rounded-2xl bg-white border border-gray-100 shadow-sm p-5 mb-6">
        <div class="absolute -right-8 -top-10 w-32 h-32 rounded-full bg-indigo-50/70 pointer-events-none"></div>
        <div class="absolute -right-2 -bottom-12 w-20 h-20 rounded-full bg-purple-50/60 pointer-events-none"></div>

        <div class="relative flex items-center gap-3">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white shadow-sm"
            style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
          >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <p class="text-[26px] font-bold text-gray-900 leading-tight truncate">¡Bienvenido/a, {{ auth.usuario?.nombre }}!</p>
        </div>

        <div class="relative mt-4">
          <span class="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-100 rounded-full pl-2.5 pr-3 py-1.5">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
            <span class="text-xs font-medium text-emerald-700">{{ personasEnSala }} personas en biblioteca</span>
          </span>
          <div class="mt-2 h-1.5 w-40 max-w-full bg-gray-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-emerald-400 rounded-full transition-all"
              :style="{ width: `${Math.min(100, (personasEnSala / capacidad) * 100)}%` }"
            />
          </div>
        </div>
      </div>

      <ApiErrorBanner v-if="apiError" />

      <div class="space-y-3">
        <router-link
          v-for="a in acciones"
          :key="a.to"
          :to="{ name: a.to }"
          class="w-full flex items-center gap-4 p-4 bg-white border border-gray-200 rounded-2xl shadow-sm hover:border-indigo-300 hover:shadow-md transition-all"
        >
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50/80 text-indigo-600 shrink-0">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
            </svg>
          </div>
          <div class="text-left min-w-0">
            <p class="font-semibold text-gray-900">{{ a.titulo }}</p>
            <p class="text-xs text-gray-500 truncate mt-0.5">{{ a.detalle }}</p>
          </div>
          <svg class="ml-auto w-5 h-5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>
    </div>
  </PortalLayout>
</template>
