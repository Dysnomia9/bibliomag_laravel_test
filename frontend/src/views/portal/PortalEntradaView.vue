<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import apiUsuario from '@/services/apiUsuario'
import { useUsuarioAuthStore } from '@/stores/usuarioAuth'
import { formatRut, validarRut } from '@/composables/useRut'

const auth = useUsuarioAuthStore()
const router = useRouter()

type Modo = 'menu' | 'rut' | 'success' | 'error'
const modo = ref<Modo>('menu')
const rut = ref('')
const errorMsg = ref('')
const enviando = ref(false)

const teclas = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'K', '0', '⌫']

function handleTecla(t: string) {
  if (t === '⌫') {
    const cleaned = rut.value.replace(/[^0-9kK]/g, '')
    rut.value = formatRut(cleaned.slice(0, -1))
  } else {
    const cleaned = rut.value.replace(/[^0-9kK]/g, '') + t
    if (cleaned.length <= 9) rut.value = formatRut(cleaned)
  }
}

async function registrar(via: 'manual' | 'qr') {
  enviando.value = true
  try {
    await apiUsuario.post('/mi/entrada', via === 'manual' ? { rut: rut.value, via } : { via })
    modo.value = 'success'
    setTimeout(() => {
      modo.value = 'menu'
      rut.value = ''
    }, 3500)
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message ?? 'No se pudo registrar la entrada'
    modo.value = 'error'
    setTimeout(() => setModo('rut'), 3000)
  } finally {
    enviando.value = false
  }
}

function confirmarRut() {
  if (!validarRut(rut.value)) {
    errorMsg.value = 'RUT inválido. Verifica el formato.'
    modo.value = 'error'
    setTimeout(() => setModo('rut'), 3000)
    return
  }
  if (rut.value !== auth.usuario?.rut) {
    errorMsg.value = 'El RUT ingresado no coincide con tu cuenta.'
    modo.value = 'error'
    setTimeout(() => setModo('rut'), 3000)
    return
  }
  registrar('manual')
}

function setModo(m: Modo) {
  modo.value = m
}
</script>

<template>
  <PortalLayout>
    <div class="max-w-sm mx-auto flex flex-col items-center">
      <button
        @click="router.push({ name: 'portal-home' })"
        class="self-start mb-6 flex items-center gap-2 text-sm text-biblioteca-600 hover:text-biblioteca-800"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Volver al inicio
      </button>

      <div v-if="modo === 'menu'" class="w-full space-y-4">
        <h1 class="text-xl font-serif font-bold text-biblioteca-900 text-center mb-2">Marcar entrada</h1>
        <button
          @click="registrar('qr')"
          :disabled="enviando"
          class="w-full flex items-center justify-center gap-3 py-6 bg-biblioteca-700 text-white text-xl font-bold rounded-2xl shadow-md hover:bg-biblioteca-800 transition-all active:scale-95 disabled:opacity-60"
        >
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h3m3 0h-3m0 0v3m0-3v-3" />
          </svg>
          Escanear QR
        </button>
        <button
          @click="modo = 'rut'"
          class="w-full flex items-center justify-center gap-3 py-6 bg-white text-biblioteca-900 text-xl font-bold rounded-2xl shadow-md border-2 border-biblioteca-200 hover:border-biblioteca-700 transition-all active:scale-95"
        >
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 2a1 1 0 00-1 1v1a1 1 0 001 1h6a1 1 0 001-1V3a1 1 0 00-1-1H9zM4 7a2 2 0 012-2h.5a.5.5 0 01.5.5V6a2 2 0 002 2h6a2 2 0 002-2v-.5a.5.5 0 01.5-.5H18a2 2 0 012 2v13a2 2 0 01-2 2H6a2 2 0 01-2-2V7z" />
          </svg>
          Ingresar RUT
        </button>
      </div>

      <div v-else-if="modo === 'rut'" class="w-full">
        <div class="bg-white rounded-2xl shadow-md p-6 mb-4">
          <label class="block text-sm font-medium text-biblioteca-500 mb-2">Confirma tu RUT</label>
          <div class="text-3xl font-mono font-bold text-center py-4 bg-biblioteca-50 rounded-xl text-biblioteca-900 min-h-[60px]">
            {{ rut || 'XX.XXX.XXX-X' }}
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2 mb-4">
          <button
            v-for="t in teclas"
            :key="t"
            @click="handleTecla(t)"
            class="py-5 text-2xl font-bold rounded-xl transition-all active:scale-95"
            :class="t === '⌫' ? 'bg-red-100 text-red-600 hover:bg-red-200' : 'bg-white shadow-md text-biblioteca-900 hover:bg-biblioteca-50'"
          >
            {{ t }}
          </button>
        </div>

        <div class="flex gap-3">
          <button
            @click="modo = 'menu'; rut = ''"
            class="flex-1 py-4 bg-biblioteca-100 text-biblioteca-800 text-lg font-bold rounded-xl hover:bg-biblioteca-200 transition-all"
          >
            Volver
          </button>
          <button
            @click="confirmarRut"
            :disabled="!rut || enviando"
            class="flex-1 py-4 bg-biblioteca-700 text-white text-lg font-bold rounded-xl hover:bg-biblioteca-800 transition-all disabled:opacity-50"
          >
            Confirmar
          </button>
        </div>
      </div>

      <div v-else-if="modo === 'success'" class="text-center py-10">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-emerald-100 text-emerald-600 mb-4">
          <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h2 class="text-2xl font-serif font-bold text-biblioteca-900 mb-2">¡Bienvenido/a!</h2>
        <p class="text-xl text-biblioteca-600">{{ auth.usuario?.nombre }} {{ auth.usuario?.apellido }}</p>
        <p class="text-sm text-biblioteca-400 mt-3">Entrada registrada correctamente</p>
      </div>

      <div v-else-if="modo === 'error'" class="text-center py-10">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-100 text-red-600 mb-4">
          <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
          </svg>
        </div>
        <h2 class="text-2xl font-serif font-bold text-red-700 mb-2">Error</h2>
        <p class="text-lg text-biblioteca-600">{{ errorMsg }}</p>
      </div>
    </div>
  </PortalLayout>
</template>
