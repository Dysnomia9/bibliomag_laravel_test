<script setup lang="ts">
import logoUmag from '@/assets/logo-umag.png'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const anio = new Date().getFullYear()

function mockClick() {
  toast.info('Vista de demostración (V2) — este botón no está conectado a un sistema real.')
}
</script>

<template>
  <div class="relative min-h-screen flex items-center justify-center bg-biblioteca-50 px-4 py-12">
    <span
      class="absolute top-4 left-4 sm:top-6 sm:left-6 text-[11px] font-medium uppercase tracking-wide text-acento-600 bg-acento-500/10 border border-acento-500/20 rounded-full px-3 py-1"
    >
      Mockup · No funcional
    </span>

    <div class="w-full max-w-sm">
      <div class="bg-white border border-biblioteca-100 rounded-2xl shadow-lg p-8 text-center">
        <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-full bg-biblioteca-50 text-biblioteca-700">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
          </svg>
        </div>
        <p class="text-xs font-medium uppercase tracking-wider text-biblioteca-500 mb-5">Acceso al sistema</p>

        <img :src="logoUmag" alt="Universidad de Magallanes" class="mx-auto h-16 w-16 object-contain mb-3" />
        <h1 class="text-xl font-serif font-semibold text-biblioteca-900">Biblioteca UMAG</h1>
        <p class="mt-1 text-sm text-biblioteca-500 mb-7">Sistema Digital Institucional</p>

        <div class="space-y-3">
          <button
            type="button"
            @click="mockClick"
            class="w-full flex items-center justify-center gap-3 rounded-xl border border-biblioteca-200 bg-white py-3 text-sm font-medium text-biblioteca-800 shadow-sm hover:shadow-md hover:border-biblioteca-300 transition-all"
          >
            <svg width="18" height="18" viewBox="0 0 48 48">
              <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12s5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24s8.955,20,20,20s20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z" />
              <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z" />
              <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z" />
              <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z" />
            </svg>
            Continuar con correo <span class="text-biblioteca-500">@umag.cl</span>
          </button>

          <button
            type="button"
            @click="mockClick"
            class="w-full rounded-xl py-3 text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all"
            style="background: linear-gradient(135deg, #31482f 0%, #4f6f4d 55%, #b5852c 120%);"
          >
            Cuenta SID / SIA
          </button>
        </div>

        <button type="button" @click="mockClick" class="mt-5 text-sm text-acento-600 hover:text-acento-500 font-medium hover:underline">
          Ingresar con cuenta externa
        </button>

        <p class="mt-8 text-[11px] text-biblioteca-400 leading-relaxed">
          © {{ anio }} Todos los derechos reservados.<br />
          Universidad de Magallanes
        </p>
      </div>

      <p class="mt-6 text-center text-sm">
        <router-link :to="{ name: 'login' }" class="text-biblioteca-500 hover:text-biblioteca-700 font-medium hover:underline">
          ← Volver al inicio de sesión
        </router-link>
      </p>
    </div>
  </div>
</template>
