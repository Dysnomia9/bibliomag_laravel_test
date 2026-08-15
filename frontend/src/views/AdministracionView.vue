<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import type { ConfiguracionInstitucional, EstadoLibroPersonalizado, Ubicacion } from '@/types'

const toast = useToast()
const apiError = ref(false)
const cargando = ref(true)

const configuracion = reactive({ jefe_unidad_nombre: '', jefe_unidad_cargo: '' })
const configuracionOriginal = reactive({ jefe_unidad_nombre: '', jefe_unidad_cargo: '' })
const guardandoConfig = ref(false)
const confirmandoConfig = ref(false)

const estados = ref<EstadoLibroPersonalizado[]>([])
const nuevoEstado = reactive({ nombre: '', descripcion: '' })
const creandoEstado = ref(false)
const estadoADesactivar = ref<EstadoLibroPersonalizado | null>(null)
const desactivandoEstado = ref(false)

const ubicaciones = ref<Ubicacion[]>([])
const nuevaUbicacion = reactive({ nombre: '' })
const creandoUbicacion = ref(false)

async function cargar() {
  cargando.value = true
  try {
    const [configRes, estadosRes, ubicacionesRes] = await Promise.all([
      api.get<ConfiguracionInstitucional>('/configuracion'),
      api.get<EstadoLibroPersonalizado[]>('/estados-libro-personalizados'),
      api.get<Ubicacion[]>('/ubicaciones'),
    ])
    Object.assign(configuracion, configRes.data)
    Object.assign(configuracionOriginal, configRes.data)
    estados.value = estadosRes.data
    ubicaciones.value = ubicacionesRes.data
    apiError.value = false
  } catch {
    apiError.value = true
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

function pedirConfirmacionConfig() {
  if (!configuracion.jefe_unidad_nombre.trim() || !configuracion.jefe_unidad_cargo.trim()) {
    toast.error('Completa nombre y cargo')
    return
  }
  confirmandoConfig.value = true
}

async function guardarConfiguracion() {
  guardandoConfig.value = true
  try {
    await api.put('/configuracion', configuracion)
    Object.assign(configuracionOriginal, configuracion)
    toast.success('Configuración institucional actualizada')
    confirmandoConfig.value = false
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo guardar')
  } finally {
    guardandoConfig.value = false
  }
}

async function crearEstado() {
  if (!nuevoEstado.nombre.trim()) {
    toast.error('Ingresa un nombre para el estado personalizado')
    return
  }
  creandoEstado.value = true
  try {
    const { data } = await api.post<EstadoLibroPersonalizado>('/estados-libro-personalizados', {
      nombre: nuevoEstado.nombre.trim(),
      descripcion: nuevoEstado.descripcion.trim() || null,
    })
    estados.value.push(data)
    nuevoEstado.nombre = ''
    nuevoEstado.descripcion = ''
    toast.success('Estado personalizado creado')
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo crear el estado')
  } finally {
    creandoEstado.value = false
  }
}

async function crearUbicacion() {
  if (!nuevaUbicacion.nombre.trim()) {
    toast.error('Ingresa un nombre para la ubicación')
    return
  }
  creandoUbicacion.value = true
  try {
    const { data } = await api.post<Ubicacion>('/ubicaciones', { nombre: nuevaUbicacion.nombre.trim() })
    ubicaciones.value.push(data)
    ubicaciones.value.sort((a, b) => a.nombre.localeCompare(b.nombre))
    nuevaUbicacion.nombre = ''
    toast.success('Ubicación creada')
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo crear la ubicación')
  } finally {
    creandoUbicacion.value = false
  }
}

async function aplicarCambioActivo(estado: EstadoLibroPersonalizado, activo: boolean) {
  try {
    const { data } = await api.patch<EstadoLibroPersonalizado>(`/estados-libro-personalizados/${estado.id}/activo`, { activo })
    const idx = estados.value.findIndex((e) => e.id === estado.id)
    if (idx !== -1) estados.value[idx] = data
  } catch {
    toast.error('No se pudo cambiar el estado')
  }
}

// Desactivar pide confirmación (deja de ofrecerse para libros nuevos); reactivar es
// la dirección "segura" y no la necesita.
function toggleActivo(estado: EstadoLibroPersonalizado) {
  if (estado.activo) {
    estadoADesactivar.value = estado
    return
  }
  aplicarCambioActivo(estado, true)
}

async function confirmarDesactivar() {
  if (!estadoADesactivar.value) return
  desactivandoEstado.value = true
  try {
    await aplicarCambioActivo(estadoADesactivar.value, false)
    estadoADesactivar.value = null
  } finally {
    desactivandoEstado.value = false
  }
}
</script>

<template>
  <StaffLayout>
    <div class="max-w-4xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Administración</h1>
          <p class="text-sm text-white/60 mt-1">Configuración institucional y catálogos administrables</p>
        </div>
      </div>

      <ApiErrorBanner v-if="apiError" />

      <div v-if="!cargando" class="space-y-6">
        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-semibold text-gray-900 mb-1">Configuración institucional</h3>
          <p class="text-xs text-gray-500 mb-4">
            Nombre y cargo de quien firma la Constancia de No Multa (Usuarios → Constancia).
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
              <input v-model="configuracion.jefe_unidad_nombre" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Cargo</label>
              <input v-model="configuracion.jefe_unidad_cargo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
            </div>
          </div>
          <button
            @click="pedirConfirmacionConfig"
            :disabled="guardandoConfig"
            class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-60"
          >
            {{ guardandoConfig ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-semibold text-gray-900 mb-1">Estados personalizados de libro</h3>
          <p class="text-xs text-gray-500 mb-4">
            Aparecen como opción "Personalizado" en Estado de Libro y Cambio Masivo. Un estado ya usado no se puede
            borrar, solo desactivar (deja de ofrecerse en el selector).
          </p>

          <div class="flex flex-col sm:flex-row gap-2 mb-5">
            <input v-model="nuevoEstado.nombre" placeholder="Nombre (ej: Restauración)" class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
            <input v-model="nuevoEstado.descripcion" placeholder="Descripción (opcional)" class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
            <button
              @click="crearEstado"
              :disabled="creandoEstado"
              class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm disabled:opacity-60 shrink-0"
            >
              + Crear
            </button>
          </div>

          <div class="rounded-lg border border-gray-200 divide-y divide-gray-100">
            <div v-for="e in estados" :key="e.id" class="flex items-center justify-between px-4 py-3">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ e.nombre }}</p>
                <p v-if="e.descripcion" class="text-xs text-gray-500">{{ e.descripcion }}</p>
              </div>
              <button
                @click="toggleActivo(e)"
                class="text-xs px-3 py-1 rounded-full font-medium transition-colors"
                :class="e.activo ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
              >
                {{ e.activo ? 'Activo' : 'Inactivo' }}
              </button>
            </div>
            <p v-if="!estados.length" class="px-4 py-6 text-center text-sm text-gray-400">Sin estados personalizados todavía.</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-semibold text-gray-900 mb-1">Ubicaciones</h3>
          <p class="text-xs text-gray-500 mb-4">
            Ubicación física de los ejemplares, seleccionable en Catalogación y Cambio Masivo de Estado.
          </p>

          <div class="flex flex-col sm:flex-row gap-2 mb-5">
            <input v-model="nuevaUbicacion.nombre" placeholder="Nombre (ej: Biblioteca Central)" class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" @keydown.enter="crearUbicacion" />
            <button
              @click="crearUbicacion"
              :disabled="creandoUbicacion"
              class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm disabled:opacity-60 shrink-0"
            >
              + Crear
            </button>
          </div>

          <div class="rounded-lg border border-gray-200 divide-y divide-gray-100">
            <div v-for="u in ubicaciones" :key="u.id" class="px-4 py-3">
              <p class="text-sm font-medium text-gray-900">{{ u.nombre }}</p>
            </div>
            <p v-if="!ubicaciones.length" class="px-4 py-6 text-center text-sm text-gray-400">Sin ubicaciones todavía.</p>
          </div>
        </div>
      </div>

      <div
        v-if="confirmandoConfig"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="confirmandoConfig = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm">
          <h3 class="text-lg font-bold text-gray-900 mb-1">¿Confirmar cambio de datos institucionales?</h3>
          <p class="text-sm text-gray-500 mb-3">
            Este nombre y cargo aparecen firmando la Constancia de No Multa que se le entrega a los usuarios.
          </p>
          <div class="text-sm bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-6 space-y-1">
            <p v-if="configuracionOriginal.jefe_unidad_nombre !== configuracion.jefe_unidad_nombre">
              Nombre: <span class="text-gray-400 line-through">{{ configuracionOriginal.jefe_unidad_nombre }}</span>
              → <strong>{{ configuracion.jefe_unidad_nombre }}</strong>
            </p>
            <p v-if="configuracionOriginal.jefe_unidad_cargo !== configuracion.jefe_unidad_cargo">
              Cargo: <span class="text-gray-400 line-through">{{ configuracionOriginal.jefe_unidad_cargo }}</span>
              → <strong>{{ configuracion.jefe_unidad_cargo }}</strong>
            </p>
            <p v-if="configuracionOriginal.jefe_unidad_nombre === configuracion.jefe_unidad_nombre && configuracionOriginal.jefe_unidad_cargo === configuracion.jefe_unidad_cargo" class="text-gray-500">
              Sin cambios respecto a lo guardado actualmente.
            </p>
          </div>
          <div class="flex gap-3">
            <button
              @click="confirmandoConfig = false"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="guardarConfiguracion"
              :disabled="guardandoConfig"
              class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium text-sm disabled:opacity-60"
            >
              {{ guardandoConfig ? 'Guardando…' : 'Sí, guardar' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="estadoADesactivar"
        class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="estadoADesactivar = null"
      >
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm">
          <h3 class="text-lg font-bold text-gray-900 mb-1">¿Desactivar este estado?</h3>
          <p class="text-sm text-gray-500 mb-6">
            <strong>{{ estadoADesactivar.nombre }}</strong> dejará de aparecer como opción en Estado de Libro y Cambio
            Masivo. Los ejemplares que ya lo tienen asignado no se ven afectados, y se puede reactivar cuando quieras.
          </p>
          <div class="flex gap-3">
            <button
              @click="estadoADesactivar = null"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="confirmarDesactivar"
              :disabled="desactivandoEstado"
              class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium text-sm disabled:opacity-60"
            >
              {{ desactivandoEstado ? 'Desactivando…' : 'Sí, desactivar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
