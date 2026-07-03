<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import apiUsuario from '@/services/apiUsuario'
import { catalogoMock } from '@/data/mock'
import type { Libro } from '@/types'

const router = useRouter()

const busqueda = ref('')
const libros = ref<Libro[]>([])
const usingMock = ref(false)
const cargando = ref(false)

async function cargar() {
  cargando.value = true
  try {
    const { data } = await apiUsuario.get<Libro[]>('/mi/catalogo', { params: busqueda.value ? { q: busqueda.value } : {} })
    libros.value = data
    usingMock.value = false
  } catch {
    usingMock.value = true
    const q = busqueda.value.toLowerCase()
    libros.value = q
      ? catalogoMock.filter(
          (l) => l.titulo.toLowerCase().includes(q) || (l.autor ?? '').toLowerCase().includes(q) || (l.categoria ?? '').toLowerCase().includes(q)
        )
      : catalogoMock
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
    <div class="max-w-lg mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <button
          @click="router.push({ name: 'portal-home' })"
          class="w-10 h-10 flex items-center justify-center rounded-lg bg-white shadow-md text-biblioteca-600 hover:text-biblioteca-800"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-xl font-serif font-bold text-biblioteca-900 flex items-center gap-2">
            <svg class="w-5 h-5 text-biblioteca-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Catálogo
          </h1>
          <p class="text-xs text-biblioteca-500">Consulta disponibilidad de libros</p>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div class="relative mb-4">
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-biblioteca-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
        </svg>
        <input
          v-model="busqueda"
          placeholder="Buscar libro, autor o área..."
          class="w-full pl-12 pr-4 py-4 bg-white rounded-xl shadow-md text-lg border-0 focus:ring-2 focus:ring-biblioteca-500 outline-none"
        />
      </div>

      <div class="space-y-3">
        <div v-for="libro in libros" :key="libro.id" class="bg-white rounded-xl shadow-md p-4 flex items-center gap-4">
          <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" :class="libro.disponible ? 'bg-emerald-100' : 'bg-red-100'">
            <svg v-if="libro.disponible" class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg v-else class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-biblioteca-900 text-sm truncate">{{ libro.titulo }}</p>
            <p class="text-xs text-biblioteca-500">{{ libro.autor }} · {{ libro.categoria }}</p>
          </div>
          <span
            class="text-xs px-2 py-1 rounded-full font-medium shrink-0"
            :class="libro.disponible ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
          >
            {{ libro.disponible ? 'Disponible' : 'Prestado' }}
          </span>
        </div>
        <div v-if="!cargando && !libros.length" class="text-center py-12 text-biblioteca-400">Sin resultados</div>
      </div>
    </div>
  </PortalLayout>
</template>
