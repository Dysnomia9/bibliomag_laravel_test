<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import QRCode from 'qrcode'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import type { CodigoAcceso } from '@/types'

const toast = useToast()

const codigo = ref<CodigoAcceso | null>(null)
const cargando = ref(true)
const regenerando = ref(false)
const error = ref(false)
const canvas = ref<HTMLCanvasElement | null>(null)

async function pintarQr() {
  if (!codigo.value) return
  await nextTick()
  if (!canvas.value) return
  await QRCode.toCanvas(canvas.value, codigo.value.codigo, { width: 260, margin: 1 })
}

async function cargar() {
  cargando.value = true
  error.value = false
  try {
    const { data } = await api.get<CodigoAcceso>('/codigo-acceso')
    codigo.value = data
    await pintarQr()
  } catch {
    error.value = true
    toast.error('No se pudo obtener el código QR. Verifica tu conexión con el servidor.')
  } finally {
    cargando.value = false
  }
}

async function regenerar() {
  regenerando.value = true
  try {
    const { data } = await api.post<CodigoAcceso>('/codigo-acceso/regenerar')
    codigo.value = data
    await pintarQr()
    toast.success('Código QR regenerado. El anterior ya no funcionará.')
  } catch {
    toast.error('No se pudo regenerar el código QR')
  } finally {
    regenerando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <StaffLayout>
    <div class="max-w-xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Código QR de Acceso</h1>
          <p class="text-sm text-white/60 mt-1">Un único código compartido para que los usuarios marquen su entrada</p>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg p-6 sm:p-8 text-center">
        <div v-if="cargando" class="py-16 text-sm text-gray-400">Cargando código…</div>
        <div v-else-if="error" class="py-12">
          <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-4 py-3">
            No se pudo cargar el código QR. Verifica que el backend esté disponible e inténtalo de nuevo.
          </p>
          <button @click="cargar" class="mt-4 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:underline">
            Reintentar
          </button>
        </div>
        <template v-else-if="codigo">
          <div class="inline-block p-4 bg-white border border-gray-200 rounded-lg">
            <canvas ref="canvas" />
          </div>
          <p class="mt-4 text-xs font-mono tracking-wider text-gray-500 break-all">{{ codigo.codigo }}</p>
          <p class="mt-1 text-xs text-gray-400">
            Generado {{ new Date(codigo.updated_at).toLocaleString('es-CL') }}
          </p>

          <div class="mt-6 text-left bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-600">
            Muestra este código en una pantalla o imprímelo en la entrada de la biblioteca. Es el mismo para todos los
            usuarios y sigue funcionando para cualquier cantidad de escaneos hasta que lo regeneres.
          </div>

          <button
            @click="regenerar"
            :disabled="regenerando"
            class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white rounded-lg font-medium text-sm transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {{ regenerando ? 'Regenerando…' : 'Regenerar código' }}
          </button>
          <p class="mt-2 text-xs text-red-500">Al regenerar, el código anterior deja de ser válido de inmediato.</p>
        </template>
      </div>
    </div>
  </StaffLayout>
</template>
