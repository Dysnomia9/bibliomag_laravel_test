<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import apiUsuario from '@/services/apiUsuario'
import { useUsuarioAuthStore } from '@/stores/usuarioAuth'
import type { EstadoPortal } from '@/types'

const auth = useUsuarioAuthStore()

const personasEnSala = ref(0)
const capacidad = ref(220)
const usingMock = ref(false)

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
    usingMock.value = false
  } catch {
    usingMock.value = true
    personasEnSala.value = 47
    capacidad.value = 220
  }
}

onMounted(cargar)
</script>

<template>
  <PortalLayout>
    <div class="max-w-lg mx-auto">
      <div class="text-center mb-8">
        <div
          class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-md"
          style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
        >
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <h1 class="text-2xl font-serif font-bold text-gray-900">¡Bienvenido/a, {{ auth.usuario?.nombre }}!</h1>
        <div class="flex items-center justify-center gap-2 mt-2 text-sm text-gray-500">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3a4 4 0 00-3-3.87M9 12a4 4 0 100-8 4 4 0 000 8z" />
          </svg>
          <span>En sala: <strong class="text-gray-800">{{ personasEnSala }}</strong> / {{ capacidad }}</span>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs text-center">
        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div class="space-y-3">
        <router-link
          v-for="a in acciones"
          :key="a.to"
          :to="{ name: a.to }"
          class="w-full flex items-center gap-4 p-4 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-indigo-300 hover:shadow-md transition-all"
        >
          <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
            </svg>
          </div>
          <div class="text-left min-w-0">
            <p class="font-semibold text-gray-900">{{ a.titulo }}</p>
            <p class="text-xs text-gray-500 truncate">{{ a.detalle }}</p>
          </div>
          <svg class="ml-auto w-5 h-5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>
    </div>
  </PortalLayout>
</template>
