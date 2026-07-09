<script setup lang="ts">
import { onMounted, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import api from '@/services/api'
import type { Libro } from '@/types'

const libros = ref<Libro[]>([])
const cargando = ref(true)
const apiError = ref(false)
const busqueda = ref('')

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get<Libro[]>('/libros', {
      params: { q: busqueda.value.trim() || undefined },
    })
    libros.value = data
    apiError.value = false
  } catch {
    apiError.value = true
    libros.value = []
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

let buscarTimer: ReturnType<typeof setTimeout> | undefined
function onBuscarInput() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(cargar, 250)
}
</script>

<template>
  <StaffLayout>
    <div class="max-w-5xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Listado de Libros</h1>
          <p class="text-sm text-white/60 mt-1">Catálogo completo con código de barras, para pruebas de escaneo</p>
        </div>
      </div>

      <ApiErrorBanner v-if="apiError" />

      <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <div class="relative max-w-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
          </svg>
          <input
            v-model="busqueda"
            @input="onBuscarInput"
            type="text"
            placeholder="Buscar por título, autor o código de barras..."
            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
          />
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-100 border-b-2 border-gray-200">
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Código de Barras</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Título</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Autor</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Categoría</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Disponible</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="(l, idx) in libros"
                :key="l.id"
                class="hover:bg-indigo-50/40 transition-colors"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-100'"
              >
                <td class="px-6 py-3 text-sm font-mono text-gray-900">{{ l.codigo_barras }}</td>
                <td class="px-6 py-3 text-sm text-gray-900">{{ l.titulo }}</td>
                <td class="px-6 py-3 text-sm text-gray-600">{{ l.autor ?? '—' }}</td>
                <td class="px-6 py-3 text-sm text-gray-600">{{ l.categoria ?? '—' }}</td>
                <td class="px-6 py-3">
                  <span
                    class="text-xs px-2.5 py-1 rounded-full font-medium"
                    :class="l.disponible ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ l.disponible ? 'Sí' : 'No' }}
                  </span>
                </td>
              </tr>
              <tr v-if="!cargando && !libros.length">
                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">Sin libros que coincidan con la búsqueda.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
