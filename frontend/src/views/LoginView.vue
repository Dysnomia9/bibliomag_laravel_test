<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('admin@umag.cl')
const password = ref('')
const auth = useAuthStore()
const router = useRouter()

async function onSubmit() {
  const ok = await auth.login(email.value, password.value)
  if (ok) router.push({ name: 'dashboard' })
}
</script>

<template>
  <div class="relative min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12">
    <router-link
      :to="{ name: 'login-v2' }"
      class="absolute top-4 right-4 sm:top-6 sm:right-6 text-xs font-medium text-gray-500 hover:text-indigo-600 border border-gray-200 hover:border-indigo-300 rounded-full px-3 py-1.5 bg-white/70 backdrop-blur-sm transition-colors"
    >
      Versión 2
    </router-link>

    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div
          class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl text-white text-xl font-serif font-semibold shadow-md"
          style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
        >
          B
        </div>
        <h1 class="text-2xl font-serif font-semibold text-gray-900">Biblioteca UMAG</h1>
        <p class="mt-1 text-sm text-gray-500">Panel de administración</p>
      </div>

      <form @submit.prevent="onSubmit" class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 sm:p-8 space-y-5">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Correo institucional</label>
          <input
            id="email"
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-gray-300 bg-gray-50/50 px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            placeholder="nombre@umag.cl"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Contraseña</label>
          <input
            id="password"
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-lg border border-gray-300 bg-gray-50/50 px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            placeholder="••••••••"
          />
        </div>

        <p v-if="auth.error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
          {{ auth.error }}
        </p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium py-2.5 transition-colors"
        >
          {{ auth.loading ? 'Ingresando…' : 'Iniciar sesión' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-gray-500">
        Sistema interno · Universidad de Magallanes
      </p>
      <p class="mt-3 text-center text-sm">
        <router-link :to="{ name: 'portal-login' }" class="text-indigo-600 hover:text-indigo-700 font-medium hover:underline">
          ¿Eres estudiante, docente o funcionario? Ingresa aquí
        </router-link>
      </p>
    </div>
  </div>
</template>
