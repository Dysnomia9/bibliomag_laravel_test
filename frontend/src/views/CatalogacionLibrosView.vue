<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import ApiErrorBanner from '@/components/ApiErrorBanner.vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import type { Libro, TipoMaterialLibro } from '@/types'

const toast = useToast()

const libros = ref<Libro[]>([])
const cargando = ref(true)
const apiError = ref(false)
const busqueda = ref('')

const editandoId = ref<number | null>(null)
const guardando = ref(false)

const formVacio = {
  codigo_barras: '',
  titulo: '',
  autor: '',
  categoria: '',
  clasificacion: '',
  coleccion: '',
  editorial: '',
  anio_publicacion: '' as number | '',
  ubicacion: '',
  tipo_material: 'libro' as TipoMaterialLibro,
  volumen: '',
  precio: '' as number | '',
  nota_publica: '',
  nota_interna: '',
}
const form = reactive({ ...formVacio })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get<Libro[]>('/libros', {
      params: { q: busqueda.value.trim() || undefined },
    })
    libros.value = data
    apiError.value = false
  } catch {
    apiError.value = true
    libros.value = []
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

let buscarTimer: ReturnType<typeof setTimeout> | undefined
function onBuscarInput() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(cargar, 250)
}

function resetForm() {
  Object.assign(form, formVacio)
  editandoId.value = null
}

function editar(libro: Libro) {
  editandoId.value = libro.id
  Object.assign(form, {
    codigo_barras: libro.codigo_barras,
    titulo: libro.titulo,
    autor: libro.autor ?? '',
    categoria: libro.categoria ?? '',
    clasificacion: libro.clasificacion ?? '',
    coleccion: libro.coleccion ?? '',
    editorial: libro.editorial ?? '',
    anio_publicacion: libro.anio_publicacion ?? '',
    ubicacion: libro.ubicacion ?? '',
    tipo_material: libro.tipo_material ?? 'libro',
    volumen: libro.volumen ?? '',
    precio: libro.precio ?? '',
    nota_publica: libro.nota_publica ?? '',
    nota_interna: libro.nota_interna ?? '',
  })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function guardar() {
  if (!form.codigo_barras.trim() || !form.titulo.trim()) {
    toast.error('Ingrese al menos el código de barras y el título')
    return
  }
  guardando.value = true
  try {
    const payload = {
      ...form,
      anio_publicacion: form.anio_publicacion === '' ? null : Number(form.anio_publicacion),
      precio: form.precio === '' ? null : Number(form.precio),
    }
    if (editandoId.value) {
      await api.patch(`/libros/${editandoId.value}`, payload)
      toast.success('Libro actualizado')
    } else {
      await api.post('/libros', payload)
      toast.success('Libro catalogado correctamente')
    }
    resetForm()
    await cargar()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo guardar el libro')
  } finally {
    guardando.value = false
  }
}

const estadoBadges: Record<string, { label: string; cls: string }> = {
  inventario: { label: 'Inventario', cls: 'bg-gray-100 text-gray-700' },
  procesos_tecnicos: { label: 'En procesos técnicos', cls: 'bg-amber-100 text-amber-700' },
  por_colocar: { label: 'Por colocar', cls: 'bg-sky-100 text-sky-700' },
  en_estante: { label: 'En estante', cls: 'bg-emerald-100 text-emerald-700' },
  estanteria_auxiliar: { label: 'Estantería auxiliar', cls: 'bg-indigo-100 text-indigo-700' },
  de_baja: { label: 'De baja', cls: 'bg-red-100 text-red-700' },
}
</script>

<template>
  <StaffLayout>
    <div class="max-w-5xl mx-auto">
      <div
        class="rounded-xl shadow-md mb-6 overflow-hidden"
        style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%);"
      >
        <div class="px-6 py-5">
          <h1 class="text-2xl font-serif font-bold tracking-tight text-white">Catalogación de Libros</h1>
          <p class="text-sm text-white/60 mt-1">Alta de material bibliográfico con clasificación Dewey y código de barras</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900">
            {{ editandoId ? 'Editando libro' : 'Nuevo libro' }}
          </h3>
          <button v-if="editandoId" @click="resetForm" class="text-sm text-gray-500 hover:text-gray-700 font-medium">
            Cancelar edición
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Código de barras *</label>
            <input v-model="form.codigo_barras" placeholder="978..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-mono text-sm" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Título *</label>
            <input v-model="form.titulo" placeholder="Título del libro" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Autor</label>
            <input v-model="form.autor" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Categoría / Área</label>
            <input v-model="form.categoria" placeholder="Ej: Ciencia, Historia..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de material</label>
            <select v-model="form.tipo_material" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
              <option value="libro">Libro</option>
              <option value="revista">Revista</option>
              <option value="tesis">Tesis</option>
              <option value="dvd">DVD</option>
              <option value="otro">Otro</option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Clasificación (Dewey + Cutter + año)</label>
            <input v-model="form.clasificacion" placeholder="Ej: 813.54 G216n 2020" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Colección</label>
            <input v-model="form.coleccion" list="colecciones" placeholder="General, Referencia..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Editorial</label>
            <input v-model="form.editorial" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Año de publicación</label>
            <input v-model="form.anio_publicacion" type="number" placeholder="2024" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Volumen</label>
            <input v-model="form.volumen" placeholder="Tomo I" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Ubicación física</label>
            <input v-model="form.ubicacion" placeholder="1er Piso - Estante 4" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Precio (CLP)</label>
            <input v-model="form.precio" type="number" min="0" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
          </div>

          <div class="sm:col-span-2 lg:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Nota pública (visible en el catálogo del portal)</label>
            <textarea v-model="form.nota_publica" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"></textarea>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Nota interna (solo staff)</label>
            <textarea v-model="form.nota_interna" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"></textarea>
          </div>
        </div>

        <div class="flex justify-end mt-4">
          <button
            @click="guardar"
            :disabled="guardando || !form.codigo_barras.trim() || !form.titulo.trim()"
            class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ guardando ? 'Guardando…' : editandoId ? 'Guardar cambios' : 'Catalogar libro' }}
          </button>
        </div>
      </div>

      <ApiErrorBanner v-if="apiError" />

      <div class="bg-white rounded-xl shadow-md p-4 mb-4">
        <div class="relative max-w-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
          </svg>
          <input
            v-model="busqueda"
            @input="onBuscarInput"
            type="text"
            placeholder="Buscar por título, autor o código de barras..."
            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
          />
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-100 border-b-2 border-gray-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Código</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Título</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Clasificación</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Estado</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="l in libros" :key="l.id" class="hover:bg-indigo-50/40 transition-colors">
                <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ l.codigo_barras }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ l.titulo }}</td>
                <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ l.clasificacion ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span class="text-xs px-2.5 py-1 rounded-full font-medium" :class="estadoBadges[l.estado_proceso]?.cls">
                    {{ estadoBadges[l.estado_proceso]?.label }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button @click="editar(l)" class="text-sm text-indigo-700 hover:text-indigo-800 font-medium">Editar</button>
                </td>
              </tr>
              <tr v-if="!cargando && !libros.length">
                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">Sin libros que coincidan con la búsqueda.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <datalist id="colecciones">
        <option value="General" />
        <option value="Referencia" />
        <option value="Hemeroteca" />
      </datalist>
    </div>
  </StaffLayout>
</template>
