<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { prestamosListadoMock } from '@/data/mock'
import type { Prestamo } from '@/types'

const toast = useToast()

const prestamos = ref<Prestamo[]>([])
const cargando = ref(true)
const usingMock = ref(false)

const filtros = reactive({
  tipo_item: '',
  estado: '',
})

const TIPO_LABELS: Record<string, string> = {
  libro: 'Libro',
  audifonos: 'Audífonos',
  notebook: 'Notebook',
}

const TIPO_BADGES: Record<string, string> = {
  libro: 'bg-purple-100 text-purple-700',
  audifonos: 'bg-indigo-100 text-indigo-700',
  notebook: 'bg-sky-100 text-sky-700',
}

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get<Prestamo[]>('/prestamos', {
      params: {
        tipo_item: filtros.tipo_item || undefined,
        estado: filtros.estado || undefined,
      },
    })
    prestamos.value = data
    usingMock.value = false
  } catch {
    usingMock.value = true
    prestamos.value = prestamosListadoMock
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

const devolucionPendiente = ref<Prestamo | null>(null)
const devolviendo = ref(false)

function pedirConfirmacionDevolucion(prestamo: Prestamo) {
  devolucionPendiente.value = prestamo
}

async function confirmarDevolucion() {
  if (!devolucionPendiente.value) return
  devolviendo.value = true
  try {
    await api.patch(`/prestamos/${devolucionPendiente.value.id}/devolver`)
    toast.success('Devolución registrada')
    devolucionPendiente.value = null
    await cargar()
  } catch {
    toast.error('No se pudo registrar la devolución')
  } finally {
    devolviendo.value = false
  }
}

function getBadge(p: Prestamo) {
  if (p.estado === 'devuelto') return { label: 'Devuelto', cls: 'bg-gray-100 text-gray-700' }
  if (!p.fecha_devolucion) return { label: 'En uso', cls: 'bg-indigo-100 text-indigo-700' }
  const fechaDevolucion = new Date(p.fecha_devolucion)
  if (p.estado === 'atrasado' || fechaDevolucion < new Date()) return { label: 'Atrasado', cls: 'bg-red-100 text-red-700' }
  return { label: 'Vigente', cls: 'bg-emerald-100 text-emerald-700' }
}

function formatFecha(iso: string) {
  return new Date(iso).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const filtrados = computed(() => prestamos.value)
</script>

<template>
  <StaffLayout>
    <div class="max-w-6xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Listado de Préstamos</h1>
          <p class="text-sm text-white/60 mt-1">Libros y equipos tecnológicos prestados, de todos los usuarios</p>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex flex-wrap gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
          <select
            v-model="filtros.tipo_item"
            @change="cargar"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
          >
            <option value="">Todos</option>
            <option value="libro">Libro</option>
            <option value="audifonos">Audífonos</option>
            <option value="notebook">Notebook</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
          <select
            v-model="filtros.estado"
            @change="cargar"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
          >
            <option value="">Todos</option>
            <option value="activo">Activo</option>
            <option value="atrasado">Atrasado</option>
            <option value="devuelto">Devuelto</option>
          </select>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-100 border-b-2 border-gray-200">
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Usuario</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Ítem / Código</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Tipo</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Préstamo</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Devolución</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="(p, idx) in filtrados"
                :key="p.id"
                class="hover:bg-indigo-50/40 transition-colors"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-100'"
              >
                <td class="px-6 py-3 text-sm text-gray-900">
                  <div class="font-medium">{{ p.usuario?.nombre }} {{ p.usuario?.apellido }}</div>
                  <div class="text-xs font-mono text-gray-500">{{ p.usuario?.rut }}</div>
                </td>
                <td class="px-6 py-3 text-sm font-mono text-gray-900">{{ p.libro_titulo }}</td>
                <td class="px-6 py-3">
                  <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="TIPO_BADGES[p.tipo_item] ?? TIPO_BADGES.libro">
                    {{ TIPO_LABELS[p.tipo_item] ?? 'Libro' }}
                  </span>
                </td>
                <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(p.fecha_prestamo) }}</td>
                <td class="px-6 py-3 text-sm font-mono text-gray-600">
                  {{ p.fecha_devolucion ? formatFecha(p.fecha_devolucion) : 'Estadía en biblioteca' }}
                </td>
                <td class="px-6 py-3">
                  <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="getBadge(p).cls">
                    {{ getBadge(p).label }}
                  </span>
                </td>
                <td class="px-6 py-3">
                  <button
                    v-if="p.estado !== 'devuelto'"
                    @click="pedirConfirmacionDevolucion(p)"
                    class="text-sm text-indigo-700 hover:text-indigo-800 font-medium"
                  >
                    Devolver
                  </button>
                </td>
              </tr>
              <tr v-if="!cargando && !filtrados.length">
                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">Sin préstamos que coincidan con los filtros.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="devolucionPendiente"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="devolucionPendiente = null"
      >
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm">
          <h3 class="text-lg font-bold text-gray-900 mb-1">¿Confirmar devolución?</h3>
          <p class="text-sm text-gray-500 mb-6">
            Se registrará la devolución de <strong class="font-mono">{{ devolucionPendiente.libro_titulo }}</strong>
            ({{ TIPO_LABELS[devolucionPendiente.tipo_item] ?? 'Libro' }}) a nombre de
            <strong>{{ devolucionPendiente.usuario?.nombre }} {{ devolucionPendiente.usuario?.apellido }}</strong>.
          </p>
          <div class="flex gap-3">
            <button
              @click="devolucionPendiente = null"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="confirmarDevolucion"
              :disabled="devolviendo"
              class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm disabled:opacity-60"
            >
              {{ devolviendo ? 'Confirmando…' : 'Sí, devolver' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
