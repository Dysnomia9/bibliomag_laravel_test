<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import api from '@/services/api'
import { usuariosMock } from '@/data/mock'
import { formatRut, validarRut } from '@/composables/useRut'
import type { Usuario } from '@/types'

const usuarios = ref<Usuario[]>([])
const loading = ref(true)
const usingMock = ref(false)

const filtros = reactive({
  q: '',
  tipo: '',
  activo: '',
})

let debounceTimer: ReturnType<typeof setTimeout> | undefined

function filtrarMock(): Usuario[] {
  const q = filtros.q.trim().toLowerCase()

  return usuariosMock.filter((u) => {
    const matchQ = !q || `${u.nombre} ${u.apellido} ${u.rut} ${u.carrera ?? ''}`.toLowerCase().includes(q)
    const matchTipo = !filtros.tipo || u.tipo === filtros.tipo
    const matchActivo = filtros.activo === '' || String(u.activo) === filtros.activo
    return matchQ && matchTipo && matchActivo
  })
}

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
    usingMock.value = false
  } catch {
    usingMock.value = true
    usuarios.value = filtrarMock()
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

// --- Modal de crear/editar ---
const mostrarModal = ref(false)
const editando = ref<Usuario | null>(null)
const guardando = ref(false)
const errores = ref<Record<string, string[]>>({})

const formVacio = {
  rut: '',
  nombre: '',
  apellido: '',
  email: '',
  tipo: 'estudiante' as Usuario['tipo'],
  carrera: '',
  anio_ingreso: null as number | null,
  sexo: '',
  activo: true,
}

const form = reactive({ ...formVacio })

function abrirCrear() {
  editando.value = null
  errores.value = {}
  Object.assign(form, formVacio)
  mostrarModal.value = true
}

function abrirEditar(usuario: Usuario) {
  editando.value = usuario
  errores.value = {}
  Object.assign(form, {
    rut: usuario.rut,
    nombre: usuario.nombre,
    apellido: usuario.apellido,
    email: usuario.email ?? '',
    tipo: usuario.tipo,
    carrera: usuario.carrera ?? '',
    anio_ingreso: usuario.anio_ingreso,
    sexo: usuario.sexo ?? '',
    activo: usuario.activo,
  })
  mostrarModal.value = true
}

function onRutInput(event: Event) {
  const target = event.target as HTMLInputElement
  form.rut = formatRut(target.value)
}

async function guardar() {
  errores.value = {}

  if (!validarRut(form.rut)) {
    errores.value.rut = ['El RUT ingresado no es válido.']
    return
  }

  guardando.value = true

  const payload = {
    ...form,
    email: form.email || null,
    carrera: form.carrera || null,
    sexo: form.sexo || null,
  }

  try {
    if (editando.value) {
      await api.put(`/usuarios/${editando.value.id}`, payload)
    } else {
      await api.post('/usuarios', payload)
    }
    mostrarModal.value = false
    await cargarUsuarios()
  } catch (e: any) {
    if (e?.response?.status === 422) {
      errores.value = e.response.data.errors ?? {}
    } else {
      errores.value = { general: ['No se pudo guardar. Verifica tu conexión con el servidor.'] }
    }
  } finally {
    guardando.value = false
  }
}

async function toggleActivo(usuario: Usuario) {
  try {
    const { data } = await api.patch<Usuario>(`/usuarios/${usuario.id}/toggle-activo`)
    const idx = usuarios.value.findIndex((u) => u.id === usuario.id)
    if (idx !== -1) usuarios.value[idx] = data
  } catch {
    if (usingMock.value) {
      usuario.activo = !usuario.activo
    }
  }
}

const hayResultados = computed(() => usuarios.value.length > 0)
</script>

<template>
  <StaffLayout>
    <div class="mb-5 sm:mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-serif font-semibold text-biblioteca-900">Usuarios</h1>
        <p class="text-sm text-biblioteca-500 mt-0.5">Gestión de usuarios de la biblioteca</p>
        <p v-if="usingMock" class="mt-2 text-xs inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
          <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
          Mostrando datos de ejemplo (API no disponible)
        </p>
      </div>
      <button
        @click="abrirCrear"
        class="inline-flex items-center justify-center gap-2 bg-biblioteca-700 hover:bg-biblioteca-800 text-white text-sm font-medium px-4 py-2.5 rounded-lg shrink-0"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Usuario
      </button>
    </div>

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
              <th class="px-5 py-3 font-medium text-right">Acciones</th>
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
                <button
                  @click="toggleActivo(u)"
                  class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="u.activo ? 'bg-biblioteca-100 text-biblioteca-700' : 'bg-red-100 text-red-700'"
                >
                  {{ u.activo ? 'Activo' : 'Inactivo' }}
                </button>
              </td>
              <td class="px-5 py-3 text-right">
                <button @click="abrirEditar(u)" class="text-biblioteca-600 hover:text-biblioteca-900 text-sm font-medium">
                  Editar
                </button>
              </td>
            </tr>
            <tr v-if="!hayResultados && !loading">
              <td colspan="6" class="px-5 py-8 text-center text-sm text-biblioteca-400">
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
        <div class="mt-3 flex items-center justify-between">
          <button
            @click="toggleActivo(u)"
            class="text-xs font-medium px-2 py-0.5 rounded-full"
            :class="u.activo ? 'bg-biblioteca-100 text-biblioteca-700' : 'bg-red-100 text-red-700'"
          >
            {{ u.activo ? 'Activo' : 'Inactivo' }}
          </button>
          <button @click="abrirEditar(u)" class="text-biblioteca-600 hover:text-biblioteca-900 text-sm font-medium">
            Editar
          </button>
        </div>
      </div>
      <p v-if="!hayResultados && !loading" class="text-center text-sm text-biblioteca-400 py-8">
        Sin usuarios que coincidan con la búsqueda.
      </p>
    </div>

    <!-- Modal crear/editar -->
    <div
      v-if="mostrarModal"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-biblioteca-950/40 p-0 sm:p-4"
      @click.self="mostrarModal = false"
    >
      <div class="bg-white w-full sm:max-w-lg sm:rounded-xl rounded-t-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-5 py-4 border-b border-biblioteca-100 flex items-center justify-between">
          <h3 class="font-serif font-semibold text-biblioteca-900">
            {{ editando ? 'Editar usuario' : 'Registrar nuevo usuario' }}
          </h3>
          <button @click="mostrarModal = false" class="text-biblioteca-400 hover:text-biblioteca-700 p-1">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="guardar" class="p-5 space-y-4">
          <p v-if="errores.general" class="text-sm text-red-600">{{ errores.general[0] }}</p>

          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2 sm:col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">RUT</label>
              <input
                :value="form.rut"
                @input="onRutInput"
                type="text"
                maxlength="12"
                placeholder="12.345.678-5"
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400"
                :class="errores.rut ? 'border-red-400' : 'border-biblioteca-200'"
              />
              <p v-if="errores.rut" class="text-xs text-red-600 mt-1">{{ errores.rut[0] }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Tipo</label>
              <select v-model="form.tipo" class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400">
                <option value="estudiante">Estudiante</option>
                <option value="docente">Docente</option>
                <option value="funcionario">Funcionario</option>
              </select>
            </div>

            <div class="col-span-2 sm:col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Nombre</label>
              <input v-model="form.nombre" type="text" required class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400" />
              <p v-if="errores.nombre" class="text-xs text-red-600 mt-1">{{ errores.nombre[0] }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Apellido</label>
              <input v-model="form.apellido" type="text" required class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400" />
              <p v-if="errores.apellido" class="text-xs text-red-600 mt-1">{{ errores.apellido[0] }}</p>
            </div>

            <div class="col-span-2">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400" />
              <p v-if="errores.email" class="text-xs text-red-600 mt-1">{{ errores.email[0] }}</p>
            </div>

            <div class="col-span-2 sm:col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Carrera</label>
              <input v-model="form.carrera" type="text" class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400" />
            </div>
            <div class="col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Año ingreso</label>
              <input v-model.number="form.anio_ingreso" type="number" class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400" />
            </div>
            <div class="col-span-1">
              <label class="block text-xs font-medium text-biblioteca-600 mb-1">Sexo</label>
              <select v-model="form.sexo" class="w-full rounded-lg border border-biblioteca-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-biblioteca-400">
                <option value="">-</option>
                <option value="Femenino">Femenino</option>
                <option value="Masculino">Masculino</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" @click="mostrarModal = false" class="text-sm font-medium text-biblioteca-600 hover:text-biblioteca-900 px-4 py-2.5">
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="guardando"
              class="bg-biblioteca-700 hover:bg-biblioteca-800 disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg"
            >
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </StaffLayout>
</template>
