<script setup lang="ts">
import { ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import type { EstadoProcesoLibro, Libro } from '@/types'

const toast = useToast()

const busqueda = ref('')
const resultados = ref<Libro[]>([])
const buscando = ref(false)
const apiError = ref(false)
const buscado = ref(false)

const libroSeleccionado = ref<Libro | null>(null)
const nuevoEstado = ref<EstadoProcesoLibro>('inventario')
const guardando = ref(false)

const estados: { value: EstadoProcesoLibro; label: string; descripcion: string }[] = [
  { value: 'inventario', label: 'Inventario', descripcion: 'Recién ingresado, aún no procesado' },
  { value: 'procesos_tecnicos', label: 'En procesos técnicos', descripcion: 'Siendo plastificado o denominado' },
  { value: 'por_colocar', label: 'Por colocar', descripcion: 'Catalogado, organizado y plastificado, pendiente de ubicar' },
  { value: 'en_estante', label: 'En estante', descripcion: 'Acomodado y disponible para servicio bibliotecario' },
  { value: 'estanteria_auxiliar', label: 'Estantería auxiliar', descripcion: 'Ubicación secundaria' },
  { value: 'de_baja', label: 'De baja', descripcion: 'Obsoleto / retirado de la colección' },
]

const estadoBadges: Record<string, { label: string; cls: string }> = {
  inventario: { label: 'Inventario', cls: 'bg-gray-100 text-gray-700' },
  procesos_tecnicos: { label: 'En procesos técnicos', cls: 'bg-amber-100 text-amber-700' },
  por_colocar: { label: 'Por colocar', cls: 'bg-sky-100 text-sky-700' },
  en_estante: { label: 'En estante', cls: 'bg-emerald-100 text-emerald-700' },
  estanteria_auxiliar: { label: 'Estantería auxiliar', cls: 'bg-indigo-100 text-indigo-700' },
  de_baja: { label: 'De baja', cls: 'bg-red-100 text-red-700' },
}

async function buscar() {
  if (!busqueda.value.trim()) {
    toast.error('Ingrese un título o código de barras para buscar')
    return
  }
  buscando.value = true
  buscado.value = true
  try {
    const { data } = await api.get<Libro[]>('/libros', { params: { q: busqueda.value.trim() } })
    resultados.value = data
    apiError.value = false
  } catch {
    apiError.value = true
    resultados.value = []
  } finally {
    buscando.value = false
  }
}

function seleccionar(libro: Libro) {
  libroSeleccionado.value = libro
  nuevoEstado.value = libro.estado_proceso
}

async function guardarEstado() {
  if (!libroSeleccionado.value) return
  guardando.value = true
  try {
    const { data } = await api.patch<Libro>(`/libros/${libroSeleccionado.value.id}/estado`, {
      estado_proceso: nuevoEstado.value,
    })
    toast.success('Estado actualizado')
    const idx = resultados.value.findIndex((l) => l.id === data.id)
    if (idx !== -1) resultados.value[idx] = data
    libroSeleccionado.value = data
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo actualizar el estado')
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <StaffLayout>
    <div class="max-w-4xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Estado de Libro</h1>
          <p class="text-sm text-white/60 mt-1">Gestiona el ciclo de proceso físico de un ejemplar ya catalogado</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Buscar por título o código de barras</label>
        <div class="flex gap-3">
          <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Título o código de barras"
              class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              @keydown.enter="buscar"
            />
          </div>
          <button
            @click="buscar"
            :disabled="buscando"
            class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-60"
          >
            Buscar
          </button>
        </div>
      </div>

      <ApiErrorBanner v-if="apiError" />

      <div v-if="buscado && !buscando" class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden mb-6">
        <div class="divide-y divide-gray-100">
          <button
            v-for="l in resultados"
            :key="l.id"
            @click="seleccionar(l)"
            class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-indigo-50/50 transition-colors"
            :class="libroSeleccionado?.id === l.id ? 'bg-indigo-50' : ''"
          >
            <div>
              <p class="text-sm font-medium text-gray-900">{{ l.titulo }}</p>
              <p class="text-xs text-gray-500 font-mono">{{ l.codigo_barras }} · {{ l.autor ?? 'sin autor' }}</p>
            </div>
            <span class="text-xs px-2.5 py-1 rounded-full font-medium shrink-0" :class="estadoBadges[l.estado_proceso]?.cls">
              {{ estadoBadges[l.estado_proceso]?.label }}
            </span>
          </button>
          <p v-if="!resultados.length" class="px-4 py-8 text-center text-sm text-gray-400">Sin libros que coincidan con la búsqueda.</p>
        </div>
      </div>

      <div v-if="libroSeleccionado" class="bg-white rounded-xl shadow-md p-6">
        <h3 class="font-semibold text-gray-900 mb-1">{{ libroSeleccionado.titulo }}</h3>
        <p class="text-sm text-gray-500 mb-4 font-mono">{{ libroSeleccionado.codigo_barras }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-5">
          <button
            v-for="e in estados"
            :key="e.value"
            @click="nuevoEstado = e.value"
            class="text-left px-3 py-2.5 rounded-lg border text-sm transition-colors"
            :class="nuevoEstado === e.value ? 'border-indigo-500 bg-indigo-50 text-indigo-900' : 'border-gray-200 hover:bg-gray-50 text-gray-700'"
          >
            <span class="font-medium block">{{ e.label }}</span>
            <span class="text-xs text-gray-500">{{ e.descripcion }}</span>
          </button>
        </div>

        <div class="flex justify-end">
          <button
            @click="guardarEstado"
            :disabled="guardando || nuevoEstado === libroSeleccionado.estado_proceso"
            class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ guardando ? 'Guardando…' : 'Guardar estado' }}
          </button>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
