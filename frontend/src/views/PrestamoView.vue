<script setup lang="ts">
import { reactive, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { formatRut } from '@/composables/useRut'
import { prestamosMock, reservasLibroMock } from '@/data/mock'
import type { Prestamo, ReservaLibro, Usuario } from '@/types'

const toast = useToast()

const rut = ref('')
const usuario = ref<Usuario | null>(null)
const buscando = ref(false)
const usingMock = ref(false)

const prestamos = ref<Prestamo[]>([])
const reservas = ref<ReservaLibro[]>([])

const showForm = ref(false)
const showReservaForm = ref(false)
const nuevoLibro = ref('')
const diasPrestamo = ref(14)
const activeTab = ref<'prestamos' | 'reservas'>('prestamos')

const codigoBarras = ref('')
const libroEncontrado = ref('')
const fechaReserva = ref('')
const fechaRetiro = ref('')
const today = new Date().toISOString().slice(0, 10)

function onRutInput(event: Event) {
  rut.value = formatRut((event.target as HTMLInputElement).value)
}

async function cargarPrestamosYReservas() {
  if (!usuario.value) return
  try {
    const [{ data: p }, { data: r }] = await Promise.all([
      api.get<Prestamo[]>('/prestamos', { params: { usuario_id: usuario.value.id } }),
      api.get<ReservaLibro[]>('/reservas-libro', { params: { usuario_id: usuario.value.id } }),
    ])
    prestamos.value = p
    reservas.value = r
    usingMock.value = false
  } catch {
    usingMock.value = true
    prestamos.value = prestamosMock
    reservas.value = reservasLibroMock
  }
}

async function buscarUsuario() {
  if (!rut.value.trim()) {
    toast.error('Ingrese un RUT para buscar')
    return
  }
  buscando.value = true
  try {
    const { data } = await api.get<Usuario>(`/usuarios/rut/${rut.value}`)
    usuario.value = data
    toast.success('Usuario encontrado')
    await cargarPrestamosYReservas()
  } catch (e: any) {
    usuario.value = null
    toast.error(e?.response?.data?.message ?? 'Usuario no encontrado')
  } finally {
    buscando.value = false
  }
}

async function crearPrestamo() {
  if (!nuevoLibro.value.trim()) {
    toast.error('Ingrese el título del libro')
    return
  }
  try {
    await api.post('/prestamos', {
      usuario_id: usuario.value!.id,
      libro_titulo: nuevoLibro.value,
      dias_prestamo: diasPrestamo.value,
    })
    toast.success('Préstamo registrado')
    nuevoLibro.value = ''
    showForm.value = false
    await cargarPrestamosYReservas()
  } catch {
    toast.error('No se pudo registrar el préstamo')
  }
}

async function devolverPrestamo(prestamo: Prestamo) {
  try {
    await api.patch(`/prestamos/${prestamo.id}/devolver`)
    toast.success('Devolución registrada')
    await cargarPrestamosYReservas()
  } catch {
    toast.error('No se pudo registrar la devolución')
  }
}

let buscarLibroTimer: ReturnType<typeof setTimeout> | undefined
function buscarLibroPorCodigo(valor: string) {
  codigoBarras.value = valor.replace(/\D/g, '')
  libroEncontrado.value = ''
  clearTimeout(buscarLibroTimer)
  if (codigoBarras.value.length < 10) return
  buscarLibroTimer = setTimeout(async () => {
    try {
      const { data } = await api.get(`/libros/${codigoBarras.value}`)
      libroEncontrado.value = data.titulo
    } catch {
      libroEncontrado.value = ''
    }
  }, 250)
}

async function crearReserva() {
  if (!codigoBarras.value.trim() || !libroEncontrado.value) {
    toast.error('Escanee o ingrese un código de barras válido')
    return
  }
  if (!fechaReserva.value || !fechaRetiro.value) {
    toast.error('Seleccione las fechas de reserva y retiro')
    return
  }
  try {
    await api.post('/reservas-libro', {
      usuario_id: usuario.value!.id,
      codigo_barras: codigoBarras.value,
      fecha_reserva: fechaReserva.value,
      fecha_retiro: fechaRetiro.value,
    })
    toast.success(`Reserva creada: "${libroEncontrado.value}"`)
    codigoBarras.value = ''
    libroEncontrado.value = ''
    fechaReserva.value = ''
    fechaRetiro.value = ''
    showReservaForm.value = false
    await cargarPrestamosYReservas()
  } catch {
    toast.error('No se pudo crear la reserva')
  }
}

async function cancelarReserva(reserva: ReservaLibro) {
  try {
    await api.patch(`/reservas-libro/${reserva.id}/cancelar`)
    toast.success('Reserva cancelada')
    await cargarPrestamosYReservas()
  } catch {
    toast.error('No se pudo cancelar la reserva')
  }
}

function getBadge(p: Prestamo) {
  if (p.estado === 'devuelto') return { label: 'Devuelto', cls: 'bg-gray-100 text-gray-700' }
  const fechaDevolucion = new Date(p.fecha_devolucion)
  const ahora = new Date()
  if (p.estado === 'atrasado' || fechaDevolucion < ahora) return { label: 'Atrasado', cls: 'bg-red-100 text-red-700' }
  const diffDays = Math.ceil((fechaDevolucion.getTime() - ahora.getTime()) / (1000 * 60 * 60 * 24))
  if (diffDays <= 1) return { label: 'Vence hoy', cls: 'bg-amber-100 text-amber-700' }
  return { label: 'Vigente', cls: 'bg-emerald-100 text-emerald-700' }
}

const reservaBadges: Record<string, { label: string; cls: string }> = {
  pendiente: { label: 'Pendiente', cls: 'bg-amber-100 text-amber-700' },
  retirado: { label: 'Retirado', cls: 'bg-emerald-100 text-emerald-700' },
  cancelado: { label: 'Cancelado', cls: 'bg-gray-100 text-gray-700' },
}

function formatFecha(iso: string) {
  return new Date(iso).toLocaleDateString('es-CL')
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
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Préstamos, Devoluciones y Reservas</h1>
          <p class="text-sm text-white/60 mt-1">Gestionar préstamos y reservas de material bibliográfico</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Buscar usuario por RUT</label>
        <div class="flex gap-3">
          <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
            </svg>
            <input
              :value="rut"
              @input="onRutInput"
              type="text"
              placeholder="12.345.678-5"
              maxlength="12"
              class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              @keydown.enter="buscarUsuario"
            />
          </div>
          <button
            @click="buscarUsuario"
            :disabled="buscando"
            class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-60"
          >
            Buscar
          </button>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div v-if="usuario">
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
              <h3 class="font-medium text-gray-900 text-lg">{{ usuario.nombre }} {{ usuario.apellido }}</h3>
              <p class="text-sm text-gray-500">RUT: <span class="font-mono">{{ usuario.rut }}</span> · {{ usuario.tipo }} · {{ usuario.email ?? 'sin email' }}</p>
            </div>
            <div class="flex gap-2">
              <button
                @click="showReservaForm = !showReservaForm; showForm = false"
                class="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                Reservar Libro
              </button>
              <button
                @click="showForm = !showForm; showReservaForm = false"
                class="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Préstamo
              </button>
            </div>
          </div>

          <div v-if="showForm" class="mt-4 pt-4 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Registrar Préstamo</h4>
            <div class="flex gap-3 flex-wrap">
              <input
                v-model="nuevoLibro"
                placeholder="Título del libro"
                class="flex-1 min-w-[200px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              />
              <select
                v-model.number="diasPrestamo"
                class="px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              >
                <option :value="7">7 días</option>
                <option :value="14">14 días</option>
                <option :value="30">30 días</option>
              </select>
              <button @click="crearPrestamo" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                Registrar
              </button>
            </div>
          </div>

          <div v-if="showReservaForm" class="mt-4 pt-4 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
              </svg>
              Reservar Libro por Código de Barras
            </h4>
            <div class="space-y-3">
              <div class="flex gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px]">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h1m0 0h1M5 6v12M8 6v12M11 6v12m3-12v12m3-12v12m3-12h1v12h-1" />
                  </svg>
                  <input
                    :value="codigoBarras"
                    @input="buscarLibroPorCodigo(($event.target as HTMLInputElement).value)"
                    placeholder="Escanear o ingresar código de barras"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none font-mono"
                  />
                </div>
              </div>
              <div
                v-if="codigoBarras.length >= 10"
                class="text-sm px-3 py-2 rounded-lg"
                :class="libroEncontrado ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
              >
                <span v-if="libroEncontrado" class="flex items-center gap-2">
                  Libro encontrado: <strong>{{ libroEncontrado }}</strong>
                </span>
                <span v-else>Código de barras no encontrado en el sistema</span>
              </div>
              <div class="flex gap-3 flex-wrap items-end">
                <div class="flex-1 min-w-[180px]">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Fecha de reserva</label>
                  <input
                    v-model="fechaReserva"
                    type="date"
                    :min="today"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                  />
                </div>
                <div class="flex-1 min-w-[180px]">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Fecha límite de retiro</label>
                  <input
                    v-model="fechaRetiro"
                    type="date"
                    :min="fechaReserva || today"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                  />
                </div>
                <button
                  @click="crearReserva"
                  :disabled="!libroEncontrado || !fechaReserva || !fechaRetiro"
                  class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Confirmar Reserva
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex gap-1 mb-4 bg-white rounded-xl shadow-sm p-1">
          <button
            @click="activeTab = 'prestamos'"
            class="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-colors"
            :class="activeTab === 'prestamos' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
          >
            Préstamos ({{ prestamos.length }})
          </button>
          <button
            @click="activeTab = 'reservas'"
            class="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-colors"
            :class="activeTab === 'reservas' ? 'bg-purple-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
          >
            Reservas ({{ reservas.length }})
          </button>
        </div>

        <div v-if="activeTab === 'prestamos'" class="bg-white rounded-xl shadow-md overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libro</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Préstamo</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Devolución</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="p in prestamos" :key="p.id" class="hover:bg-gray-50">
                  <td class="px-6 py-3 text-sm text-gray-900">{{ p.libro_titulo }}</td>
                  <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(p.fecha_prestamo) }}</td>
                  <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(p.fecha_devolucion) }}</td>
                  <td class="px-6 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="getBadge(p).cls">
                      {{ getBadge(p).label }}
                    </span>
                  </td>
                  <td class="px-6 py-3">
                    <button
                      v-if="p.estado !== 'devuelto'"
                      @click="devolverPrestamo(p)"
                      class="flex items-center gap-1 text-sm text-indigo-700 hover:text-indigo-800 font-medium"
                    >
                      Devolver
                    </button>
                  </td>
                </tr>
                <tr v-if="!prestamos.length">
                  <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">Sin préstamos registrados.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="activeTab === 'reservas'" class="bg-white rounded-xl shadow-md overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libro</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código Barras</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Reserva</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retiro Hasta</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="r in reservas" :key="r.id" class="hover:bg-gray-50">
                  <td class="px-6 py-3 text-sm text-gray-900">{{ r.libro?.titulo }}</td>
                  <td class="px-6 py-3 text-xs font-mono text-gray-500">{{ r.libro?.codigo_barras }}</td>
                  <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(r.fecha_reserva) }}</td>
                  <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(r.fecha_retiro) }}</td>
                  <td class="px-6 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="reservaBadges[r.estado]?.cls">
                      {{ reservaBadges[r.estado]?.label }}
                    </span>
                  </td>
                  <td class="px-6 py-3">
                    <button
                      v-if="r.estado === 'pendiente'"
                      @click="cancelarReserva(r)"
                      class="text-sm text-red-600 hover:text-red-700 font-medium"
                    >
                      Cancelar
                    </button>
                  </td>
                </tr>
                <tr v-if="!reservas.length">
                  <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">Sin reservas registradas.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
