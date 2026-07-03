<script setup lang="ts">
import { onMounted, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { formatRut } from '@/composables/useRut'
import { entradasMock } from '@/data/mock'
import type { Entrada } from '@/types'

const toast = useToast()

const rut = ref('')
const entradas = ref<Entrada[]>([])
const personasEnSala = ref(0)
const usingMock = ref(false)
const registrando = ref(false)

const VIA_LABELS: Record<string, string> = {
  qr: 'QR Móvil',
  manual: 'Manual',
}

const VIA_STYLES: Record<string, string> = {
  qr: 'bg-violet-50 text-violet-700 border border-violet-200',
  manual: 'bg-slate-100 text-slate-700 border border-slate-200',
}

function formatHora(iso: string) {
  return new Date(iso).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })
}

async function cargar() {
  try {
    const { data } = await api.get('/entrada')
    entradas.value = data.entradas
    personasEnSala.value = data.personasEnSala
    usingMock.value = false
  } catch {
    usingMock.value = true
    entradas.value = entradasMock
    personasEnSala.value = entradasMock.length
  }
}

onMounted(cargar)

async function registrarEntrada(via: 'manual' | 'qr' = 'manual') {
  if (!rut.value.trim()) {
    toast.error('Ingrese un RUT para buscar')
    return
  }

  registrando.value = true
  try {
    const { data } = await api.post('/entrada', { rut: rut.value, via })
    toast.success(`Entrada registrada: ${data.usuario.nombre} ${data.usuario.apellido}`)
    rut.value = ''
    await cargar()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo registrar la entrada')
  } finally {
    registrando.value = false
  }
}

function onRutInput(event: Event) {
  rut.value = formatRut((event.target as HTMLInputElement).value)
}

function handleQrScan() {
  const codigo = window.prompt('Simulación de escáner QR: ingrese el RUT leído')
  if (codigo) {
    rut.value = formatRut(codigo)
    registrarEntrada('qr')
  }
}
</script>

<template>
  <StaffLayout>
    <div class="max-w-5xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="flex items-center justify-between px-6 py-5">
          <div>
            <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Registro de Entrada</h1>
            <p class="text-sm text-white/60 mt-1">Control de acceso a la biblioteca</p>
          </div>
          <div class="bg-white/15 backdrop-blur-sm border border-white/20 rounded-xl px-5 py-3 text-center">
            <p class="text-xs text-white/60">En sala</p>
            <p class="text-2xl font-mono font-bold text-white">{{ personasEnSala }} <span class="text-sm text-white/70">/ 220</span></p>
          </div>
        </div>
      </div>

      <p v-if="usingMock" class="mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </span>
      </p>

      <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <div class="flex gap-4 flex-wrap">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">RUT</label>
            <div class="relative">
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
                @keydown.enter="registrarEntrada('manual')"
              />
            </div>
          </div>
          <div class="flex items-end gap-2">
            <button
              @click="registrarEntrada('manual')"
              :disabled="registrando"
              class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-60"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M12.5 7a4 4 0 11-8 0 4 4 0 018 0zM20 8v6m3-3h-6" />
              </svg>
              Registrar Entrada
            </button>
            <button
              @click="handleQrScan"
              class="flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h3m3 0h-3m0 0v3m0-3v-3" />
              </svg>
              Escanear QR
            </button>
          </div>
        </div>
      </div>

      <div class="bg-[#FCFBF8] rounded-xl shadow-md border border-stone-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-stone-200 bg-white">
          <h3 class="font-serif font-semibold text-gray-900">Historial de hoy</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-[#F4F1EA] border-b border-stone-300">
                <th class="px-6 py-3 text-left text-xs font-semibold text-stone-600 uppercase tracking-wide border-r border-stone-200">Hora</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-stone-600 uppercase tracking-wide border-r border-stone-200">RUT</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-stone-600 uppercase tracking-wide border-r border-stone-200">Nombre</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-stone-600 uppercase tracking-wide">Vía</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(e, idx) in entradas"
                :key="e.id"
                class="border-b border-stone-200 last:border-b-0 transition-colors hover:bg-[#F4F1EA]/70"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-[#FAF8F3]'"
              >
                <td class="px-6 py-3 text-sm font-mono text-stone-500 border-r border-stone-100">
                  {{ formatHora(e.fecha_hora_entrada) }}
                </td>
                <td class="px-6 py-3 text-sm font-mono text-gray-900 border-r border-stone-100">{{ e.usuario?.rut }}</td>
                <td class="px-6 py-3 text-sm text-gray-900 border-r border-stone-100">{{ e.usuario?.nombre }} {{ e.usuario?.apellido }}</td>
                <td class="px-6 py-3">
                  <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="VIA_STYLES[e.via]">
                    {{ VIA_LABELS[e.via] }}
                  </span>
                </td>
              </tr>
              <tr v-if="!entradas.length">
                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">Sin registros de entrada hoy.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
