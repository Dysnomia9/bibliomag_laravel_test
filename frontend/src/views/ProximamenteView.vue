<script setup lang="ts">
import StaffLayout from '@/components/layout/StaffLayout.vue'

defineProps<{ titulo: string }>()
</script>

<template>
  <StaffLayout>
    <div class="flex flex-col items-center justify-center text-center py-20 sm:py-28">
      <div class="h-14 w-14 rounded-full bg-biblioteca-100 flex items-center justify-center mb-4">
        <svg class="h-7 w-7 text-biblioteca-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h2 class="text-lg font-serif font-semibold text-biblioteca-900">{{ titulo }}</h2>
      <p class="text-sm text-biblioteca-500 mt-1 max-w-xs">
        Este módulo se está migrando a Vue 3 + Laravel. Próximamente disponible.
      </p>
    </div>
  </StaffLayout>
</template>
