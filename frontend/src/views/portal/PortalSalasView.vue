<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import apiUsuario from '@/services/apiUsuario'
import { useToast } from '@/composables/useToast'
import { useUsuarioAuthStore } from '@/stores/usuarioAuth'
import { formatRut } from '@/composables/useRut'
import { reservasSalasMock, salasMock } from '@/data/mock'
import type { Reserva, Sala } from '@/types'

const toast = useToast()
const auth = useUsuarioAuthStore()
const router = useRouter()

const hoy = new Date().toISOString().slice(0, 10)

const horariosBloques = [
  { inicio: 8, fin: 10, label: '08:00 – 10:00' },
  { inicio: 10, fin: 12, label: '10:00 – 12:00' },
  { inicio: 12, fin: 14, label: '12:00 – 14:00' },
  { inicio: 14, fin: 16, label: '14:00 – 16:00' },
  { inicio: 16, fin: 18, label: '16:00 – 18:00' },
  { inicio: 18, fin: 20, label: '18:00 – 20:00' },
  { inicio: 20, fin: 21, label: '20:00 – 21:00' },
]

const salas = ref<Sala[]>([])
const reservas = ref<Reserva[]>([])
const usingMock = ref(false)
const selectedDate = ref(hoy)
const busqueda = ref('')

const CANTIDAD_MIN = 2
const CANTIDAD_MAX = 5

const modalOpen = ref(false)
const selectedSala = ref<Sala | null>(null)
const selectedBloque = ref<(typeof horariosBloques)[number] | null>(null)
const enviando = ref(false)
const cantidadPersonas = ref(CANTIDAD_MIN)
const rutsReserva = ref<string[]>(Array.from({ length: CANTIDAD_MIN }, () => ''))

watch(cantidadPersonas, (nueva) => {
  const actuales = rutsReserva.value.length
  if (nueva > actuales) {
    rutsReserva.value.push(...Array.from({ length: nueva - actuales }, () => ''))
  } else if (nueva < actuales) {
    rutsReserva.value.splice(nueva)
  }
})

async function cargar() {
  try {
    const { data } = await apiUsuario.get('/mi/salas', { params: { fecha: selectedDate.value } })
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
  return salas.value.filter((s) => s.nombre.toLowerCase().includes(q))
})

function getReserva(salaId: number, horaInicio: number) {
  return reservas.value.find((r) => r.sala_id === salaId && r.hora_inicio === horaInicio)
}
function esMia(reserva: Reserva) {
  return reserva.usuario_id === auth.usuario?.id
}

function openReservaModal(sala: Sala, bloque: (typeof horariosBloques)[number]) {
  selectedSala.value = sala
  selectedBloque.value = bloque
  cantidadPersonas.value = CANTIDAD_MIN
  rutsReserva.value = Array.from({ length: CANTIDAD_MIN }, () => '')
  modalOpen.value = true
}

function onRutInput(index: number, event: Event) {
  rutsReserva.value[index] = formatRut((event.target as HTMLInputElement).value)
}

function primerMensajeError(e: any): string | undefined {
  const errores = e?.response?.data?.errors
  if (errores) {
    const primero = Object.values(errores)[0]
    if (Array.isArray(primero) && typeof primero[0] === 'string') return primero[0]
  }
  return e?.response?.data?.message
}

async function confirmarReserva() {
  if (!selectedSala.value || !selectedBloque.value) return
  if (rutsReserva.value.some((r) => !r.trim())) {
    toast.error('Complete el RUT de cada persona')
    return
  }
  enviando.value = true
  try {
    await apiUsuario.post('/mi/reservas', {
      sala_id: selectedSala.value.id,
      fecha: selectedDate.value,
      hora_inicio: selectedBloque.value.inicio,
      hora_fin: selectedBloque.value.fin,
      cantidad_personas: cantidadPersonas.value,
      ruts: rutsReserva.value,
    })
    toast.success(`${selectedSala.value.nombre} reservada de ${selectedBloque.value.label}`)
    modalOpen.value = false
    await cargar()
  } catch (e: any) {
    toast.error(primerMensajeError(e) ?? 'No se pudo crear la reserva')
  } finally {
    enviando.value = false
  }
}

async function cancelarPropia(reserva: Reserva) {
  try {
    await apiUsuario.delete(`/mi/reservas/${reserva.id}`)
    toast.success('Reserva cancelada')
    await cargar()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo cancelar la reserva')
  }
}

function formatFechaLarga(fecha: string) {
  return fecha === hoy ? 'Hoy' : new Date(`${fecha}T12:00:00`).toLocaleDateString('es-CL')
}
</script>

<template>
  <PortalLayout>
    <div class="max-w-[1100px] mx-auto">
      <button
        @click="router.push({ name: 'portal-home' })"
        class="mb-5 flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-700"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Volver al inicio
      </button>

      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Reservar Logia de Estudio</h1>
          <p class="text-sm text-white/60 mt-1">Puedes solicitar un bloque libre y cancelar únicamente tus propias reservas</p>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div class="bg-white border border-gray-200 rounded-lg p-4 mb-5 flex flex-col sm:flex-row gap-3 sm:items-end">
        <div class="flex-1">
          <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Buscar sala</label>
          <input
            v-model="busqueda"
            type="text"
            placeholder="Ej: Logia 03"
            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Fecha</label>
          <input
            v-model="selectedDate"
            type="date"
            :min="hoy"
            class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm"
          />
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50 z-10 min-w-[130px]">Sala</th>
                <th v-for="b in horariosBloques" :key="b.inicio" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase min-w-[110px]">
                  {{ b.label }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="sala in filteredSalas" :key="sala.id" class="hover:bg-gray-50/50">
                <td class="px-4 py-3 sticky left-0 bg-white z-10 border-r border-gray-100">
                  <div class="font-medium text-sm text-gray-900">{{ sala.nombre }}</div>
                  <div class="text-xs text-gray-400">{{ sala.capacidad }} personas</div>
                </td>
                <td v-for="bloque in horariosBloques" :key="bloque.inicio" class="px-2 py-2 text-center">
                  <template v-if="getReserva(sala.id, bloque.inicio)">
                    <div
                      v-if="esMia(getReserva(sala.id, bloque.inicio)!)"
                      class="border border-indigo-200 bg-indigo-50 rounded-md px-2 py-2"
                    >
                      <div class="text-xs font-medium text-indigo-700">Tu reserva</div>
                      <button
                        @click="cancelarPropia(getReserva(sala.id, bloque.inicio)!)"
                        class="mt-1 text-[11px] font-medium text-red-600 hover:text-red-700 hover:underline"
                      >
                        Cancelar
                      </button>
                    </div>
                    <div v-else class="border border-gray-200 bg-gray-50 rounded-md px-2 py-2">
                      <div class="text-xs font-medium text-gray-500">Ocupada</div>
                    </div>
                  </template>
                  <button
                    v-else
                    @click="openReservaModal(sala, bloque)"
                    class="w-full border border-emerald-200 bg-emerald-50 rounded-md px-2 py-2 hover:bg-emerald-100 hover:border-emerald-300 transition-colors"
                  >
                    <div class="text-xs font-medium text-emerald-700">Disponible</div>
                  </button>
                </td>
              </tr>
              <tr v-if="!filteredSalas.length">
                <td :colspan="horariosBloques.length + 1" class="px-4 py-8 text-center text-sm text-gray-400">
                  Sin salas que coincidan con la búsqueda.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex gap-4 mt-4 text-xs text-gray-500">
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded border border-emerald-200 bg-emerald-50" /> Disponible</div>
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded border border-indigo-200 bg-indigo-50" /> Tu reserva</div>
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded border border-gray-200 bg-gray-50" /> Ocupada</div>
      </div>

      <div
        v-if="modalOpen && selectedSala && selectedBloque"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="modalOpen = false"
      >
        <div class="bg-white rounded-xl shadow-2xl border border-gray-200 p-6 w-full max-w-sm">
          <h3 class="text-lg font-semibold text-gray-900 mb-1">Confirmar reserva</h3>
          <p class="text-sm text-gray-500 mb-5">
            {{ selectedSala.nombre }} · {{ selectedBloque.label }} · {{ formatFechaLarga(selectedDate) }}
          </p>

          <div class="space-y-4 mb-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad de personas</label>
              <select
                v-model.number="cantidadPersonas"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              >
                <option v-for="n in CANTIDAD_MAX - CANTIDAD_MIN + 1" :key="n" :value="CANTIDAD_MIN + n - 1">
                  {{ CANTIDAD_MIN + n - 1 }} personas
                </option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">RUT de cada persona</label>
              <p class="text-xs text-gray-400 -mt-1 mb-1">Deben ser RUT de usuarios registrados en el sistema</p>
              <input
                v-for="(_, idx) in rutsReserva"
                :key="idx"
                :value="rutsReserva[idx]"
                @input="onRutInput(idx, $event)"
                type="text"
                :placeholder="`RUT persona ${idx + 1}`"
                maxlength="12"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              />
            </div>
          </div>

          <div class="flex gap-3">
            <button
              @click="modalOpen = false"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="confirmarReserva"
              :disabled="enviando"
              class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm disabled:opacity-60"
            >
              {{ enviando ? 'Reservando…' : 'Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
