<script setup lang="ts">
import { ref } from 'vue'
import SidebarNav from './SidebarNav.vue'
import TopBar from './TopBar.vue'

const sidebarOpen = ref(false)
</script>

<template>
  <div class="min-h-screen flex bg-biblioteca-50">
    <SidebarNav :open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="flex-1 flex flex-col min-w-0">
      <TopBar @toggle-sidebar="sidebarOpen = true" />
      <main class="flex-1 overflow-y-auto px-4 py-5 sm:px-6 sm:py-6">
        <slot />
      </main>
    </div>
  </div>
</template>
