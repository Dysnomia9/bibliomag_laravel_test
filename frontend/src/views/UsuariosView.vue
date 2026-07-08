<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import api from '@/services/api'
import type { Usuario } from '@/types'

const usuarios = ref<Usuario[]>([])
const loading = ref(true)
const apiError = ref(false)

const filtros = reactive({
  q: '',
  tipo: '',
  activo: '',
})

let debounceTimer: ReturnType<typeof setTimeout> | undefined

async function cargarUsuarios() {
  loading.value = true
  try {
    const { data } = await api.get<Usuario[]>('/usuarios', {
      params: {
        q: filtros.q || undefined,
        tipo: filtros.tipo || undefined,
        activo: filtros.activo === '' ? undefined : filtros.activo,
      },
    })
    usuarios.value = data
    apiError.value = false
  } catch {
    apiError.value = true
    usuarios.value = []
  } finally {
    loading.value = false
  }
}

watch(filtros, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(cargarUsuarios, 300)
}, { deep: true })

onMounted(cargarUsuarios)

const tipoLabel: Record<string, string> = {
  estudiante: 'Estudiante',
  docente: 'Docente',
  funcionario: 'Funcionario',
}

const tipoBadge: Record<string, string> = {
  estudiante: 'bg-biblioteca-100 text-biblioteca-700',
  docente: 'bg-acento-500/10 text-acento-600',
  funcionario: 'bg-biblioteca-800/10 text-biblioteca-800',
}

const hayResultados = computed(() => usuarios.value.length > 0)
</script>

<template>
  <StaffLayout>
    <div class="mb-5 sm:mb-6">
      <h1 class="text-xl sm:text-2xl font-serif font-semibold text-biblioteca-900">Usuarios</h1>
      <p class="text-sm text-biblioteca-500 mt-0.5">
        Información de usuarios registrados en el sistema institucional (solo lectura)
      </p>
    </div>

    <ApiErrorBanner v-if="apiError" />

    <!-- Filtros -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
      <input
        v-model="filtros.q"
        type="text"
        placeholder="Buscar por nombre, RUT o carrera..."
        class="sm:col-span-1 w-full rounded-lg border border-biblioteca-200 px-3.5 py-2.5 text-sm text-biblioteca-900 placeholder:text-biblioteca-400 focus:outline-none focus:ring-2 focus:ring-biblioteca-400"
      />
      <select
        v-model="filtros.tipo"
        class="w-full rounded-lg border border-biblioteca-200 px-3.5 py-2.5 text-sm text-biblioteca-900 focus:outline-none focus:ring-2 focus:ring-biblioteca-400"
      >
        <option value="">Todos los tipos</option>
        <option value="estudiante">Estudiante</option>
        <option value="docente">Docente</option>
        <option value="funcionario">Funcionario</option>
      </select>
      <select
        v-model="filtros.activo"
        class="w-full rounded-lg border border-biblioteca-200 px-3.5 py-2.5 text-sm text-biblioteca-900 focus:outline-none focus:ring-2 focus:ring-biblioteca-400"
      >
        <option value="">Todos los estados</option>
        <option value="true">Activos</option>
        <option value="false">Inactivos</option>
      </select>
    </div>

    <!-- Tabla desktop -->
    <div class="hidden lg:block bg-white border border-biblioteca-200 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-biblioteca-100 text-left text-xs text-biblioteca-500 uppercase tracking-wide">
              <th class="px-5 py-3 font-medium">RUT</th>
              <th class="px-5 py-3 font-medium">Nombre</th>
              <th class="px-5 py-3 font-medium">Carrera</th>
              <th class="px-5 py-3 font-medium">Tipo</th>
              <th class="px-5 py-3 font-medium">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-biblioteca-100">
            <tr v-for="u in usuarios" :key="u.id">
              <td class="px-5 py-3 font-mono text-biblioteca-700">{{ u.rut }}</td>
              <td class="px-5 py-3">
                <p class="font-medium text-biblioteca-900">{{ u.nombre }} {{ u.apellido }}</p>
                <p class="text-xs text-biblioteca-500">{{ u.email ?? '-' }}</p>
              </td>
              <td class="px-5 py-3 text-biblioteca-700">{{ u.carrera ?? '-' }}</td>
              <td class="px-5 py-3">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="tipoBadge[u.tipo]">
                  {{ tipoLabel[u.tipo] }}
                </span>
              </td>
              <td class="px-5 py-3">
                <span
                  class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="u.activo ? 'bg-biblioteca-100 text-biblioteca-700' : 'bg-red-100 text-red-700'"
                >
                  {{ u.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
            </tr>
            <tr v-if="!hayResultados && !loading">
              <td colspan="5" class="px-5 py-8 text-center text-sm text-biblioteca-400">
                Sin usuarios que coincidan con la búsqueda.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Cards mobile/tablet -->
    <div class="lg:hidden space-y-3">
      <div
        v-for="u in usuarios"
        :key="u.id"
        class="bg-white border border-biblioteca-200 rounded-xl p-4"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-medium text-biblioteca-900 truncate">{{ u.nombre }} {{ u.apellido }}</p>
            <p class="text-xs font-mono text-biblioteca-500">{{ u.rut }}</p>
          </div>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0" :class="tipoBadge[u.tipo]">
            {{ tipoLabel[u.tipo] }}
          </span>
        </div>
        <p class="text-xs text-biblioteca-500 mt-2">{{ u.carrera ?? 'Sin carrera' }} · {{ u.email ?? 'Sin email' }}</p>
        <div class="mt-3">
          <span
            class="text-xs font-medium px-2 py-0.5 rounded-full"
            :class="u.activo ? 'bg-biblioteca-100 text-biblioteca-700' : 'bg-red-100 text-red-700'"
          >
            {{ u.activo ? 'Activo' : 'Inactivo' }}
          </span>
        </div>
      </div>
      <p v-if="!hayResultados && !loading" class="text-center text-sm text-biblioteca-400 py-8">
        Sin usuarios que coincidan con la búsqueda.
      </p>
    </div>
  </StaffLayout>
</template>
