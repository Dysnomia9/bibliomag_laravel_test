<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import apiUsuario from '@/services/apiUsuario'
import type { Libro } from '@/types'

const router = useRouter()

const busqueda = ref('')
const libros = ref<Libro[]>([])
const apiError = ref(false)
const cargando = ref(false)

async function cargar() {
  cargando.value = true
  try {
    const { data } = await apiUsuario.get<Libro[]>('/mi/catalogo', { params: busqueda.value ? { q: busqueda.value } : {} })
    libros.value = data
    apiError.value = false
  } catch {
    apiError.value = true
    libros.value = []
  } finally {
    cargando.value = false
  }
}

let debounceTimer: ReturnType<typeof setTimeout> | undefined
watch(busqueda, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(cargar, 300)
})
onUnmounted(() => clearTimeout(debounceTimer))

onMounted(cargar)
</script>

<template>
  <PortalLayout>
    <div class="max-w-2xl mx-auto">
      <button
        @click="router.push({ name: 'portal-home' })"
        class="mb-5 flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-700"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Volver al inicio
      </button>

      <div class="mb-5">
        <h1 class="text-xl font-serif font-bold text-gray-900">Catálogo de biblioteca</h1>
        <p class="text-sm text-gray-500 mt-0.5">Consulta la disponibilidad de libros por título, autor o área</p>
      </div>

      <ApiErrorBanner v-if="apiError" />

      <div class="relative mb-5">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-gray-400" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
        </svg>
        <input
          v-model="busqueda"
          placeholder="Buscar por título, autor o área..."
          class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        />
      </div>

      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="hidden sm:grid grid-cols-[1fr_140px_100px] gap-4 px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wide">
          <span>Título / Autor</span>
          <span>Área</span>
          <span class="text-right">Estado</span>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-for="libro in libros" :key="libro.id" class="grid grid-cols-1 sm:grid-cols-[1fr_140px_100px] gap-1 sm:gap-4 px-4 py-3 hover:bg-gray-50/60 transition-colors">
            <div class="min-w-0">
              <p class="font-medium text-gray-900 text-sm truncate">{{ libro.titulo }}</p>
              <p class="text-xs text-gray-500 truncate">{{ libro.autor }}</p>
            </div>
            <div class="text-xs text-gray-600 flex items-center sm:block">
              <span class="sm:hidden text-gray-400 mr-1">Área:</span>{{ libro.categoria }}
            </div>
            <div class="sm:text-right">
              <span
                class="inline-block text-xs font-medium px-2 py-0.5 rounded border"
                :class="libro.disponible ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
              >
                {{ libro.disponible ? 'Disponible' : 'Prestado' }}
              </span>
            </div>
          </div>
          <div v-if="!cargando && !libros.length" class="px-4 py-10 text-center text-sm text-gray-400">
            Sin resultados para tu búsqueda.
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
