<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useUsuarioAuthStore } from '@/stores/usuarioAuth'

const auth = useUsuarioAuthStore()
const router = useRouter()

async function onLogout() {
  await auth.logout()
  router.push({ name: 'portal-login' })
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-biblioteca-50">
    <header
      class="h-16 shrink-0 flex items-center px-4 sm:px-8 gap-3"
      style="background: linear-gradient(135deg, #31482f 0%, #4f6f4d 55%, #b5852c 120%);"
    >
      <router-link :to="{ name: 'portal-home' }" class="flex items-center gap-2.5 shrink-0">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 text-white font-serif font-semibold">
          B
        </div>
        <div class="leading-tight hidden sm:block">
          <p class="text-[13px] font-semibold text-white">Biblioteca UMAG</p>
          <p class="text-[10px] uppercase tracking-wider text-white/70">Portal del usuario</p>
        </div>
      </router-link>

      <div class="flex-1"></div>

      <div class="flex items-center gap-3 shrink-0">
        <div class="hidden sm:block text-right leading-tight">
          <p class="text-[13px] font-medium text-white">{{ auth.usuario?.nombre }} {{ auth.usuario?.apellido }}</p>
          <p class="text-[11px] text-white/70 capitalize">{{ auth.usuario?.tipo }}</p>
        </div>
        <div class="h-9 w-9 rounded-full bg-white/15 flex items-center justify-center text-white font-medium text-sm">
          {{ (auth.usuario?.nombre ?? 'U').charAt(0) }}
        </div>
        <button
          @click="onLogout"
          class="text-white/80 hover:text-white p-2 rounded-md hover:bg-white/10"
          aria-label="Cerrar sesión"
          title="Cerrar sesión"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </button>
      </div>
    </header>
    <main class="flex-1 p-6">
      <slot />
    </main>
  </div>
</template>
