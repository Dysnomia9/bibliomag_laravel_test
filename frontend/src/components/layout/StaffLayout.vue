<script setup lang="ts">
import TopBar from './TopBar.vue'
</script>

<template>
  <div class="flex flex-col min-h-screen bg-gradient-to-br from-indigo-50/60 via-slate-50 to-purple-50/40">
    <TopBar />
    <main class="flex-1 p-6">
      <slot />
    </main>
  </div>
</template>
