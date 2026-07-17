<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { formatRut } from '@/composables/useRut'
import { useStaffNombres } from '@/composables/useStaffNombres'
import type { Equipo, Prestamo, ReservaLibro, Usuario } from '@/types'

const toast = useToast()
const { nombresStaff, cargarStaffNombres } = useStaffNombres()
cargarStaffNombres()

const rut = ref('')
const usuario = ref<Usuario | null>(null)
const buscando = ref(false)
const apiError = ref(false)

const prestamos = ref<Prestamo[]>([])
const reservas = ref<ReservaLibro[]>([])

const showForm = ref(false)
const showReservaForm = ref(false)
const prestadoPor = ref('')
const activeTab = ref<'prestamos' | 'reservas'>('prestamos')

const today = new Date().toISOString().slice(0, 10)

const codigoBarrasPrestamo = ref('')
const libroEncontradoPrestamo = ref('')
const libroDisponiblePrestamo = ref(true)
const fechaPrestamo = ref(today)
const fechaDevolucion = ref('')

const codigoBarras = ref('')
const libroEncontrado = ref('')
const libroDisponible = ref(true)
const fechaReserva = ref('')
const fechaRetiro = ref('')

const prestamosLibros = computed(() => prestamos.value.filter((p) => p.tipo_item === 'libro' || !p.tipo_item))
const prestamosAudifonos = computed(() => prestamos.value.filter((p) => p.tipo_item === 'audifonos'))
const prestamosNotebooks = computed(() => prestamos.value.filter((p) => p.tipo_item === 'notebook'))

const codigoAudifonos = ref('')
const codigoNotebook = ref('')
const prestadoPorAudifonos = ref('')
const prestadoPorNotebook = ref('')

const equiposAudifonos = ref<Equipo[]>([])
const equiposNotebook = ref<Equipo[]>([])

async function cargarEquiposDisponibles() {
  try {
    const [{ data: audifonos }, { data: notebooks }] = await Promise.all([
      api.get<Equipo[]>('/equipos', { params: { tipo: 'audifonos', activo: 1, disponible: 1 } }),
      api.get<Equipo[]>('/equipos', { params: { tipo: 'notebook', activo: 1, disponible: 1 } }),
    ])
    equiposAudifonos.value = audifonos
    equiposNotebook.value = notebooks
  } catch {
    equiposAudifonos.value = []
    equiposNotebook.value = []
  }
}

onMounted(cargarEquiposDisponibles)

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
    apiError.value = false
  } catch {
    apiError.value = true
    prestamos.value = []
    reservas.value = []
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

async function buscarLibroApi(codigo: string): Promise<{ titulo: string; disponible: boolean } | null> {
  try {
    const { data } = await api.get(`/libros/${codigo}`)
    return { titulo: data.titulo, disponible: data.disponible }
  } catch {
    return null
  }
}

let buscarLibroPrestamoTimer: ReturnType<typeof setTimeout> | undefined
function buscarLibroPorCodigoPrestamo(valor: string) {
  codigoBarrasPrestamo.value = valor.replace(/\D/g, '')
  libroEncontradoPrestamo.value = ''
  libroDisponiblePrestamo.value = true
  clearTimeout(buscarLibroPrestamoTimer)
  if (codigoBarrasPrestamo.value.length < 10) return
  buscarLibroPrestamoTimer = setTimeout(async () => {
    const libro = await buscarLibroApi(codigoBarrasPrestamo.value)
    libroEncontradoPrestamo.value = libro?.titulo ?? ''
    libroDisponiblePrestamo.value = libro?.disponible ?? true
  }, 250)
}

async function crearPrestamo() {
  if (!codigoBarrasPrestamo.value.trim() || !libroEncontradoPrestamo.value) {
    toast.error('Escanee o ingrese un código de barras válido')
    return
  }
  if (!libroDisponiblePrestamo.value) {
    toast.error('Este libro ya está reservado/prestado por otra persona')
    return
  }
  if (!fechaPrestamo.value || !fechaDevolucion.value) {
    toast.error('Seleccione la fecha de préstamo y de devolución')
    return
  }
  try {
    await api.post('/prestamos', {
      usuario_id: usuario.value!.id,
      codigo_barras: codigoBarrasPrestamo.value,
      fecha_prestamo: fechaPrestamo.value,
      fecha_devolucion: fechaDevolucion.value,
      prestado_por: prestadoPor.value.trim() || undefined,
    })
    toast.success(`Préstamo registrado: "${libroEncontradoPrestamo.value}"`)
    codigoBarrasPrestamo.value = ''
    libroEncontradoPrestamo.value = ''
    fechaPrestamo.value = today
    fechaDevolucion.value = ''
    prestadoPor.value = ''
    showForm.value = false
    await cargarPrestamosYReservas()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo crear el préstamo')
  }
}

const devolucionPendiente = ref<Prestamo | null>(null)
const devolviendo = ref(false)
const devueltoPor = ref('')

function pedirConfirmacionDevolucion(prestamo: Prestamo) {
  devolucionPendiente.value = prestamo
  devueltoPor.value = ''
}

async function confirmarDevolucion() {
  if (!devolucionPendiente.value) return
  devolviendo.value = true
  try {
    const { data } = await api.patch<Prestamo>(`/prestamos/${devolucionPendiente.value.id}/devolver`, {
      devuelto_por: devueltoPor.value.trim() || undefined,
    })
    toast.success(
      data.multa_monto
        ? `Devolución registrada con multa de ${formatMonto(data.multa_monto)} por atraso`
        : 'Devolución registrada',
    )
    devolucionPendiente.value = null
    await Promise.all([cargarPrestamosYReservas(), cargarEquiposDisponibles()])
  } catch {
    toast.error('No se pudo registrar la devolución')
  } finally {
    devolviendo.value = false
  }
}

function formatMonto(monto: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(monto)
}

const pagoMultaPendiente = ref<Prestamo | null>(null)
const pagandoMulta = ref(false)
const multaPagadaPor = ref('')

function pedirConfirmacionPagoMulta(prestamo: Prestamo) {
  pagoMultaPendiente.value = prestamo
  multaPagadaPor.value = ''
}

async function confirmarPagoMulta() {
  if (!pagoMultaPendiente.value) return
  pagandoMulta.value = true
  try {
    await api.patch(`/prestamos/${pagoMultaPendiente.value.id}/multa/pagar`, {
      multa_pagada_por: multaPagadaPor.value.trim() || undefined,
    })
    toast.success('Multa marcada como pagada')
    pagoMultaPendiente.value = null
    await cargarPrestamosYReservas()
  } catch {
    toast.error('No se pudo registrar el pago de la multa')
  } finally {
    pagandoMulta.value = false
  }
}

async function crearPrestamoEquipo(tipo: 'audifonos' | 'notebook') {
  const codigo = tipo === 'audifonos' ? codigoAudifonos : codigoNotebook
  const prestadoPorEquipo = tipo === 'audifonos' ? prestadoPorAudifonos : prestadoPorNotebook

  if (!codigo.value.trim()) {
    toast.error(`Ingrese el código del ${tipo === 'audifonos' ? 'audífono' : 'notebook'}`)
    return
  }
  try {
    await api.post('/prestamos', {
      usuario_id: usuario.value!.id,
      libro_titulo: codigo.value,
      tipo_item: tipo,
      prestado_por: prestadoPorEquipo.value.trim() || undefined,
    })
    toast.success('Préstamo de equipo registrado')
    codigo.value = ''
    prestadoPorEquipo.value = ''
    await Promise.all([cargarPrestamosYReservas(), cargarEquiposDisponibles()])
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo registrar el préstamo del equipo')
  }
}

let buscarLibroTimer: ReturnType<typeof setTimeout> | undefined
function buscarLibroPorCodigo(valor: string) {
  codigoBarras.value = valor.replace(/\D/g, '')
  libroEncontrado.value = ''
  libroDisponible.value = true
  clearTimeout(buscarLibroTimer)
  if (codigoBarras.value.length < 10) return
  buscarLibroTimer = setTimeout(async () => {
    const libro = await buscarLibroApi(codigoBarras.value)
    libroEncontrado.value = libro?.titulo ?? ''
    libroDisponible.value = libro?.disponible ?? true
  }, 250)
}

async function crearReserva() {
  if (!codigoBarras.value.trim() || !libroEncontrado.value) {
    toast.error('Escanee o ingrese un código de barras válido')
    return
  }
  if (!libroDisponible.value) {
    toast.error('Este libro ya está reservado/prestado por otra persona')
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
    toast.success(`Reserva registrada: "${libroEncontrado.value}"`)
    codigoBarras.value = ''
    libroEncontrado.value = ''
    fechaReserva.value = ''
    fechaRetiro.value = ''
    showReservaForm.value = false
    await cargarPrestamosYReservas()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo registrar la reserva')
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
  // Los equipos (audífonos, notebooks) no tienen fecha de vencimiento: se devuelven
  // al término de la estadía en la biblioteca, así que nunca quedan "atrasados".
  if (!p.fecha_devolucion) return { label: 'En uso', cls: 'bg-indigo-100 text-indigo-700' }
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

function formatFecha(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('es-CL') : '—'
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

      <ApiErrorBanner v-if="apiError" />

      <div v-if="usuario">
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
              <h3 class="font-medium text-gray-900 text-lg">{{ usuario.nombre }} {{ usuario.apellido }}</h3>
              <p class="text-sm text-gray-500">RUT: <span class="font-mono">{{ usuario.rut }}</span> · {{ usuario.tipo }} · {{ usuario.email ?? 'sin email' }}</p>
            </div>
            <div class="flex gap-2">
              <button
                @click="showForm = !showForm; showReservaForm = false"
                class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                Nuevo Préstamo
              </button>
              <button
                @click="showReservaForm = !showReservaForm; showForm = false"
                class="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Reservar Libro
              </button>
            </div>
          </div>

          <div v-if="showForm" class="mt-4 pt-4 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              Nuevo Préstamo por Código de Barras
            </h4>
            <div class="space-y-3">
              <div class="flex gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px]">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h1m0 0h1M5 6v12M8 6v12M11 6v12m3-12v12m3-12v12m3-12h1v12h-1" />
                  </svg>
                  <input
                    :value="codigoBarrasPrestamo"
                    @input="buscarLibroPorCodigoPrestamo(($event.target as HTMLInputElement).value)"
                    placeholder="Escanear o ingresar código de barras"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none font-mono"
                  />
                </div>
              </div>
              <div
                v-if="codigoBarrasPrestamo.length >= 10"
                class="text-sm px-3 py-2 rounded-lg"
                :class="libroEncontradoPrestamo && libroDisponiblePrestamo ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
              >
                <span v-if="libroEncontradoPrestamo && libroDisponiblePrestamo" class="flex items-center gap-2">
                  Libro encontrado: <strong>{{ libroEncontradoPrestamo }}</strong>
                </span>
                <span v-else-if="libroEncontradoPrestamo && !libroDisponiblePrestamo">
                  <strong>{{ libroEncontradoPrestamo }}</strong> ya está reservado/prestado por otra persona
                </span>
                <span v-else>Código de barras no encontrado en el sistema</span>
              </div>
              <div class="flex gap-3 flex-wrap items-end">
                <div class="flex-1 min-w-[160px]">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Fecha de préstamo</label>
                  <input
                    v-model="fechaPrestamo"
                    type="date"
                    :min="today"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>
                <div class="flex-1 min-w-[160px]">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Fecha de devolución</label>
                  <input
                    v-model="fechaDevolucion"
                    type="date"
                    :min="fechaPrestamo || today"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>
                <input
                  v-model="prestadoPor"
                  type="text"
                  list="staff-nombres"
                  placeholder="Prestado por (opcional)"
                  class="flex-1 min-w-[160px] px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                />
                <button
                  @click="crearPrestamo"
                  :disabled="!libroEncontradoPrestamo || !libroDisponiblePrestamo || !fechaPrestamo || !fechaDevolucion"
                  class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Confirmar Préstamo
                </button>
              </div>
            </div>
          </div>

          <div v-if="showReservaForm" class="mt-4 pt-4 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
              </svg>
              Nueva Reserva por Código de Barras
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
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none font-mono"
                  />
                </div>
              </div>
              <div
                v-if="codigoBarras.length >= 10"
                class="text-sm px-3 py-2 rounded-lg"
                :class="libroEncontrado && libroDisponible ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
              >
                <span v-if="libroEncontrado && libroDisponible" class="flex items-center gap-2">
                  Libro encontrado: <strong>{{ libroEncontrado }}</strong>
                </span>
                <span v-else-if="libroEncontrado && !libroDisponible">
                  <strong>{{ libroEncontrado }}</strong> ya está reservado/prestado por otra persona
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
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"
                  />
                </div>
                <div class="flex-1 min-w-[180px]">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Fecha límite de retiro</label>
                  <input
                    v-model="fechaRetiro"
                    type="date"
                    :min="fechaReserva || today"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"
                  />
                </div>
                <button
                  @click="crearReserva"
                  :disabled="!libroEncontrado || !libroDisponible || !fechaReserva || !fechaRetiro"
                  class="px-5 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Confirmar Reserva
                </button>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="usuario.multas_pendientes?.cantidad"
          class="mb-6 flex items-center gap-2 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800"
        >
          ⚠ Este usuario tiene {{ usuario.multas_pendientes.cantidad }} multa(s) pendiente(s) por
          {{ formatMonto(usuario.multas_pendientes.monto_total) }}. Puede continuar con el préstamo igualmente.
        </div>

        <div class="flex gap-1 mb-4 bg-white rounded-xl shadow-sm p-1">
          <button
            @click="activeTab = 'prestamos'"
            class="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-colors"
            :class="activeTab === 'prestamos' ? 'bg-purple-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
          >
            Préstamos ({{ prestamosLibros.length }})
          </button>
          <button
            @click="activeTab = 'reservas'"
            class="flex-1 py-2.5 px-4 rounded-lg text-sm font-medium transition-colors"
            :class="activeTab === 'reservas' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
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
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Multa</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="p in prestamosLibros" :key="p.id" class="hover:bg-gray-50">
                  <td class="px-6 py-3 text-sm text-gray-900">{{ p.libro_titulo }}</td>
                  <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(p.fecha_prestamo) }}</td>
                  <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ formatFecha(p.fecha_devolucion) }}</td>
                  <td class="px-6 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="getBadge(p).cls">
                      {{ getBadge(p).label }}
                    </span>
                  </td>
                  <td class="px-6 py-3">
                    <div v-if="p.multa_monto" class="flex items-center gap-2">
                      <span
                        class="text-xs px-2.5 py-1 rounded-full font-medium"
                        :class="p.multa_estado === 'pagada' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                      >
                        {{ formatMonto(p.multa_monto) }}
                      </span>
                      <button
                        v-if="p.multa_estado === 'pendiente'"
                        @click="pedirConfirmacionPagoMulta(p)"
                        class="text-xs text-indigo-700 hover:text-indigo-800 font-medium"
                      >
                        Marcar pagada
                      </button>
                    </div>
                    <span v-else class="text-xs text-gray-400">—</span>
                  </td>
                  <td class="px-6 py-3">
                    <button
                      v-if="p.estado !== 'devuelto'"
                      @click="pedirConfirmacionDevolucion(p)"
                      class="flex items-center gap-1 text-sm text-indigo-700 hover:text-indigo-800 font-medium"
                    >
                      Devolver
                    </button>
                  </td>
                </tr>
                <tr v-if="!prestamosLibros.length">
                  <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">Sin préstamos registrados.</td>
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
          <div class="bg-white rounded-xl shadow-md p-6">
            <h4 class="font-semibold text-gray-900 mb-1 flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Préstamo de Audífonos
            </h4>
            <p class="text-xs text-gray-400 mb-4">Se identifican por código de inventario · se devuelven al término de la estadía</p>
            <div class="flex gap-2 flex-wrap mb-4">
              <input
                v-model="codigoAudifonos"
                list="equipos-audifonos"
                placeholder="Código (ej: AUD-003)"
                class="flex-1 min-w-[160px] px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-mono"
              />
              <input
                v-model="prestadoPorAudifonos"
                type="text"
                list="staff-nombres"
                placeholder="Prestado por (opcional)"
                class="flex-1 min-w-[160px] px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              />
              <button
                @click="crearPrestamoEquipo('audifonos')"
                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"
              >
                Pedir
              </button>
            </div>
            <div class="space-y-2">
              <div
                v-for="p in prestamosAudifonos"
                :key="p.id"
                class="flex items-center justify-between text-sm border-t border-gray-100 pt-2 first:border-t-0 first:pt-0"
              >
                <span class="text-gray-900 font-mono">{{ p.libro_titulo }}</span>
                <div class="flex items-center gap-2">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="getBadge(p).cls">{{ getBadge(p).label }}</span>
                  <button
                    v-if="p.estado !== 'devuelto'"
                    @click="pedirConfirmacionDevolucion(p)"
                    class="text-xs text-indigo-700 hover:text-indigo-800 font-medium"
                  >
                    Devolver
                  </button>
                </div>
              </div>
              <p v-if="!prestamosAudifonos.length" class="text-xs text-gray-400 text-center py-2">Sin préstamos de audífonos.</p>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-md p-6">
            <h4 class="font-semibold text-gray-900 mb-1 flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Préstamo de Notebooks
            </h4>
            <p class="text-xs text-gray-400 mb-4">Se identifican por código de inventario · se devuelven al término de la estadía</p>
            <div class="flex gap-2 flex-wrap mb-4">
              <input
                v-model="codigoNotebook"
                list="equipos-notebook"
                placeholder="Código (ej: NB-012)"
                class="flex-1 min-w-[160px] px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-mono"
              />
              <input
                v-model="prestadoPorNotebook"
                type="text"
                list="staff-nombres"
                placeholder="Prestado por (opcional)"
                class="flex-1 min-w-[160px] px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              />
              <button
                @click="crearPrestamoEquipo('notebook')"
                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"
              >
                Pedir
              </button>
            </div>
            <div class="space-y-2">
              <div
                v-for="p in prestamosNotebooks"
                :key="p.id"
                class="flex items-center justify-between text-sm border-t border-gray-100 pt-2 first:border-t-0 first:pt-0"
              >
                <span class="text-gray-900 font-mono">{{ p.libro_titulo }}</span>
                <div class="flex items-center gap-2">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="getBadge(p).cls">{{ getBadge(p).label }}</span>
                  <button
                    v-if="p.estado !== 'devuelto'"
                    @click="pedirConfirmacionDevolucion(p)"
                    class="text-xs text-indigo-700 hover:text-indigo-800 font-medium"
                  >
                    Devolver
                  </button>
                </div>
              </div>
              <p v-if="!prestamosNotebooks.length" class="text-xs text-gray-400 text-center py-2">Sin préstamos de notebooks.</p>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="devolucionPendiente"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="devolucionPendiente = null"
      >
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm">
          <h3 class="text-lg font-bold text-gray-900 mb-1">¿Confirmar devolución?</h3>
          <p class="text-sm text-gray-500 mb-4">
            Se registrará la devolución de <strong class="font-mono">{{ devolucionPendiente.libro_titulo }}</strong>.
          </p>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Devuelto por (opcional)</label>
            <input
              v-model="devueltoPor"
              type="text"
              list="staff-nombres"
              placeholder="Nombre de quien recibe"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
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

      <div
        v-if="pagoMultaPendiente"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="pagoMultaPendiente = null"
      >
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm">
          <h3 class="text-lg font-bold text-gray-900 mb-1">¿Confirmar pago de multa?</h3>
          <p class="text-sm text-gray-500 mb-4">
            Se registrará el pago de <strong>{{ formatMonto(pagoMultaPendiente.multa_monto ?? 0) }}</strong> por
            <strong class="font-mono">{{ pagoMultaPendiente.libro_titulo }}</strong>.
          </p>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Cobrada por (opcional)</label>
            <input
              v-model="multaPagadaPor"
              type="text"
              list="staff-nombres"
              placeholder="Nombre de quien cobra"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div class="flex gap-3">
            <button
              @click="pagoMultaPendiente = null"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="confirmarPagoMulta"
              :disabled="pagandoMulta"
              class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm disabled:opacity-60"
            >
              {{ pagandoMulta ? 'Confirmando…' : 'Sí, pagada' }}
            </button>
          </div>
        </div>
      </div>

      <datalist id="staff-nombres">
        <option v-for="n in nombresStaff" :key="n" :value="n" />
      </datalist>
      <datalist id="equipos-audifonos">
        <option v-for="e in equiposAudifonos" :key="e.id" :value="e.codigo_inventario" />
      </datalist>
      <datalist id="equipos-notebook">
        <option v-for="e in equiposNotebook" :key="e.id" :value="e.codigo_inventario" />
      </datalist>
    </div>
  </StaffLayout>
</template>
