<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { formatRut } from '@/composables/useRut'
import { reservasSalasMock, salasMock } from '@/data/mock'
import type { Reserva, Sala } from '@/types'

const toast = useToast()

const hoy = new Date().toISOString().slice(0, 10)

const horariosBloques = [
  { inicio: 8, fin: 10, label: '08:00 – 10:00' },
  { inicio: 10, fin: 12, label: '10:00 – 12:00' },
  { inicio: 12, fin: 14, label: '12:00 – 14:00' },
  { inicio: 14, fin: 16, label: '14:00 – 16:00' },
  { inicio: 16, fin: 18, label: '16:00 – 18:00' },
  { inicio: 18, fin: 20, label: '18:00 – 20:00' },
]

const salas = ref<Sala[]>([])
const reservas = ref<Reserva[]>([])
const usingMock = ref(false)
const selectedDate = ref(hoy)
const busqueda = ref('')
const page = ref(0)
const salasPerPage = 10

const modalOpen = ref(false)
const selectedSala = ref<Sala | null>(null)
const selectedBloque = ref<(typeof horariosBloques)[number] | null>(null)
const rutReserva = ref('')
const nombreReserva = ref('')

async function cargar() {
  try {
    const { data } = await api.get('/salas', { params: { fecha: selectedDate.value } })
    salas.value = data.salas
    reservas.value = data.reservas
    usingMock.value = false
  } catch {
    usingMock.value = true
    salas.value = salasMock
    reservas.value = reservasSalasMock
  }
}

onMounted(cargar)
watch(selectedDate, cargar)

const filteredSalas = computed(() => {
  if (!busqueda.value.trim()) return salas.value
  const q = busqueda.value.toLowerCase()
  return salas.value.filter((s) => s.nombre.toLowerCase().includes(q) || String(s.capacidad).includes(q))
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredSalas.value.length / salasPerPage)))
const salasPage = computed(() => filteredSalas.value.slice(page.value * salasPerPage, (page.value + 1) * salasPerPage))

function getReserva(salaId: number, horaInicio: number) {
  return reservas.value.find((r) => r.sala_id === salaId && r.hora_inicio === horaInicio)
}
function isOcupado(salaId: number, horaInicio: number) {
  return !!getReserva(salaId, horaInicio)
}

const totalOcupadas = computed(() => reservas.value.length)
const totalBloques = computed(() => salas.value.length * horariosBloques.length)
const porcentajeOcupacion = computed(() => (totalBloques.value ? Math.round((totalOcupadas.value / totalBloques.value) * 100) : 0))

const horaActual = new Date().getHours()
const bloqueActual = horariosBloques.find((b) => horaActual >= b.inicio && horaActual < b.fin)
const disponiblesAhora = computed(() => {
  if (!bloqueActual) return salas.value.length
  return salas.value.filter((s) => !isOcupado(s.id, bloqueActual!.inicio)).length
})

function openReservaModal(sala: Sala, bloque: (typeof horariosBloques)[number]) {
  const existente = getReserva(sala.id, bloque.inicio)
  if (existente) {
    toast.info(`Reservada por ${existente.nombre_usuario} (${existente.rut_usuario})`)
    return
  }
  selectedSala.value = sala
  selectedBloque.value = bloque
  rutReserva.value = ''
  nombreReserva.value = ''
  modalOpen.value = true
}

function onRutInput(event: Event) {
  rutReserva.value = formatRut((event.target as HTMLInputElement).value)
}

async function confirmarReserva() {
  if (!rutReserva.value.trim() || !nombreReserva.value.trim()) {
    toast.error('Complete el RUT y nombre del usuario')
    return
  }
  if (!selectedSala.value || !selectedBloque.value) return

  try {
    await api.post('/reservas', {
      sala_id: selectedSala.value.id,
      fecha: selectedDate.value,
      hora_inicio: selectedBloque.value.inicio,
      hora_fin: selectedBloque.value.fin,
      rut_usuario: rutReserva.value,
      nombre_usuario: nombreReserva.value,
    })
    toast.success(`${selectedSala.value.nombre} reservada de ${selectedBloque.value.label} para ${nombreReserva.value}`)
    modalOpen.value = false
    await cargar()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo crear la reserva')
  }
}

async function cancelarReserva(salaId: number, horaInicio: number) {
  const reserva = getReserva(salaId, horaInicio)
  if (!reserva) return
  try {
    await api.delete(`/reservas/${reserva.id}`)
    toast.success('Reserva cancelada')
    await cargar()
  } catch {
    toast.error('No se pudo cancelar la reserva')
  }
}

function formatFechaLarga(fecha: string) {
  return fecha === hoy ? 'Hoy' : new Date(`${fecha}T12:00:00`).toLocaleDateString('es-CL')
}
</script>

<template>
  <StaffLayout>
    <div class="max-w-[1200px] mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Reserva de Salas</h1>
          <p class="text-sm text-white/60 mt-1">25 salas de estudio · 1er Piso · Bloques de 2 horas</p>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-md p-5">
          <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Ocupación Hoy</div>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-bold text-indigo-700">{{ porcentajeOcupacion }}%</span>
            <span class="text-sm text-gray-400 mb-1">{{ totalOcupadas }}/{{ totalBloques }} bloques</span>
          </div>
          <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-indigo-600 rounded-full transition-all" :style="{ width: `${porcentajeOcupacion}%` }" />
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-5">
          <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Salas Disponibles Ahora</div>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-bold text-emerald-600">{{ disponiblesAhora }}</span>
            <span class="text-sm text-gray-400 mb-1">de {{ salas.length }} salas</span>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-5">
          <label class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1 block">Fecha</label>
          <input
            v-model="selectedDate"
            type="date"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
          />
        </div>
      </div>

      <div class="mb-4">
        <div class="relative max-w-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
          </svg>
          <input
            v-model="busqueda"
            @input="page = 0"
            type="text"
            placeholder="Buscar sala..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
          />
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50 z-10 min-w-[140px]">Sala</th>
                <th v-for="b in horariosBloques" :key="b.inicio" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase min-w-[120px]">
                  {{ b.label }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="sala in salasPage" :key="sala.id" class="hover:bg-gray-50/50">
                <td class="px-4 py-3 sticky left-0 bg-white z-10">
                  <div class="font-medium text-sm text-gray-900">{{ sala.nombre }}</div>
                  <div class="text-xs text-gray-400">{{ sala.capacidad }} personas</div>
                </td>
                <td v-for="bloque in horariosBloques" :key="bloque.inicio" class="px-2 py-2 text-center">
                  <div
                    v-if="isOcupado(sala.id, bloque.inicio)"
                    class="group relative bg-red-50 border border-red-200 rounded-lg px-2 py-2 cursor-pointer hover:bg-red-100 transition-colors"
                    @click="toast.info(`Reservada por ${getReserva(sala.id, bloque.inicio)?.nombre_usuario} (${getReserva(sala.id, bloque.inicio)?.rut_usuario})`)"
                  >
                    <div class="text-xs font-medium text-red-700 truncate">{{ getReserva(sala.id, bloque.inicio)?.nombre_usuario }}</div>
                    <div class="text-[10px] text-red-500 font-mono">{{ getReserva(sala.id, bloque.inicio)?.rut_usuario }}</div>
                    <button
                      @click.stop="cancelarReserva(sala.id, bloque.inicio)"
                      class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs"
                      title="Cancelar reserva"
                    >
                      ×
                    </button>
                  </div>
                  <button
                    v-else
                    @click="openReservaModal(sala, bloque)"
                    class="w-full bg-emerald-50 border border-emerald-200 rounded-lg px-2 py-2 hover:bg-emerald-100 transition-colors group"
                  >
                    <div class="text-xs font-medium text-emerald-700">Disponible</div>
                    <div class="text-[10px] text-emerald-500 opacity-0 group-hover:opacity-100 transition-opacity">Click para reservar</div>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
          <span class="text-sm text-gray-500">
            Mostrando {{ page * salasPerPage + 1 }}–{{ Math.min((page + 1) * salasPerPage, filteredSalas.length) }} de {{ filteredSalas.length }} salas
          </span>
          <div class="flex gap-1">
            <button
              @click="page = Math.max(0, page - 1)"
              :disabled="page === 0"
              class="p-1.5 rounded-lg border border-gray-300 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              ‹
            </button>
            <button
              @click="page = Math.min(totalPages - 1, page + 1)"
              :disabled="page === totalPages - 1"
              class="p-1.5 rounded-lg border border-gray-300 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              ›
            </button>
          </div>
        </div>
      </div>

      <div class="flex gap-4 mt-4 text-xs text-gray-500">
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200" /> Disponible</div>
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded bg-red-100 border border-red-200" /> Ocupada</div>
      </div>

      <div
        v-if="modalOpen && selectedSala && selectedBloque"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="modalOpen = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md">
          <h3 class="text-lg font-bold text-gray-900 mb-1">Reservar {{ selectedSala.nombre }}</h3>
          <p class="text-sm text-gray-500 mb-5">
            {{ selectedBloque.label }} · {{ selectedSala.capacidad }} personas · {{ formatFechaLarga(selectedDate) }}
          </p>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">RUT del usuario</label>
              <input
                :value="rutReserva"
                @input="onRutInput"
                type="text"
                placeholder="12.345.678-5"
                maxlength="12"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del usuario</label>
              <input
                v-model="nombreReserva"
                type="text"
                placeholder="Nombre completo"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              />
            </div>
          </div>

          <div class="flex gap-3 mt-6">
            <button
              @click="modalOpen = false"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="confirmarReserva"
              class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm"
            >
              Confirmar
            </button>
          </div>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
