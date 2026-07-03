<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import StaffLayout from '@/components/layout/StaffLayout.vue'
import BarChart from '@/components/reportes/BarChart.vue'
import BreakdownList from '@/components/reportes/BreakdownList.vue'
import api from '@/services/api'
import type { Periodo, ReporteOpciones, ReporteResumen, ReporteTab } from '@/types'

const usingMock = ref(false)

const tab = ref<ReporteTab>('prestamos')
const periodo = ref<Periodo>('mes')

const filtros = reactive({
  carrera: '',
  anio_ingreso: '',
  sexo: '',
  tipo_usuario: '',
})

const opciones = ref<ReporteOpciones>({ carreras: [], aniosIngreso: [], sexos: [], tiposUsuario: ['estudiante', 'docente', 'funcionario'] })

const resumenVacio: ReporteResumen = {
  total: 0,
  promedioPorPeriodo: 0,
  categoriaMasFrecuente: '—',
  serie: [],
  porCarrera: [],
  porSexo: [],
  porAnioIngreso: [],
  porTipoUsuario: [],
  porHora: [],
}

const resumen = ref<ReporteResumen>({ ...resumenVacio })
const cargando = ref(true)

const TABS: { id: ReporteTab; label: string; color: string }[] = [
  { id: 'prestamos', label: 'Número de Préstamos', color: 'indigo' },
  { id: 'ingresos', label: 'Ingresos a Biblioteca', color: 'emerald' },
  { id: 'logias', label: 'Uso de Logias', color: 'amber' },
]

const PERIODOS: { id: Periodo; label: string }[] = [
  { id: 'dia', label: 'Día' },
  { id: 'semana', label: 'Semana' },
  { id: 'mes', label: 'Mes' },
  { id: 'semestre', label: 'Semestre' },
  { id: 'anio', label: 'Año' },
]

const colorBar: Record<ReporteTab, string> = { prestamos: '#4338CA', ingresos: '#059669', logias: '#D97706' }
const tabTitulo: Record<ReporteTab, string> = { prestamos: 'Total préstamos', ingresos: 'Total ingresos', logias: 'Total usos de logias' }

async function cargarOpciones() {
  try {
    const { data } = await api.get<ReporteOpciones>('/reportes/opciones')
    opciones.value = data
  } catch {
    opciones.value = {
      carreras: ['Ingeniería Civil Informática', 'Ingeniería Comercial', 'Derecho', 'Enfermería'],
      aniosIngreso: [2021, 2022, 2023, 2024, 2025, 2026],
      sexos: ['Femenino', 'Masculino'],
      tiposUsuario: ['estudiante', 'docente', 'funcionario'],
    }
  }
}

async function cargarResumen() {
  cargando.value = true
  try {
    const { data } = await api.get<ReporteResumen>('/reportes/resumen', {
      params: {
        tab: tab.value,
        periodo: periodo.value,
        carrera: filtros.carrera || undefined,
        anio_ingreso: filtros.anio_ingreso || undefined,
        sexo: filtros.sexo || undefined,
        tipo_usuario: filtros.tipo_usuario || undefined,
      },
    })
    resumen.value = data
    usingMock.value = false
  } catch {
    usingMock.value = true
    resumen.value = { ...resumenVacio }
  } finally {
    cargando.value = false
  }
}

onMounted(async () => {
  await cargarOpciones()
  await cargarResumen()
})

watch([tab, periodo, filtros], cargarResumen, { deep: true })
</script>

<template>
  <StaffLayout>
    <div class="max-w-6xl mx-auto">
      <div class="mb-6">
        <h1 class="text-2xl font-serif font-bold tracking-tight text-gray-900 flex items-center gap-2">
          <svg class="w-6 h-6 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          Reportes
        </h1>
        <p class="text-sm text-gray-500 mt-1">Estadísticas y métricas de la biblioteca</p>
        <p v-if="usingMock" class="mt-2 text-xs">
          <span class="inline-flex items-center gap-1.5 bg-acento-500/10 text-acento-600 px-2.5 py-1 rounded-full">
            <span class="h-1.5 w-1.5 rounded-full bg-acento-500"></span>
            No se pudo cargar el reporte (API no disponible)
          </span>
        </p>
      </div>

      <div class="flex gap-2 mb-6 border-b border-gray-200">
        <button
          v-for="t in TABS"
          :key="t.id"
          @click="tab = t.id"
          class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
          :class="tab === t.id ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
        >
          {{ t.label }}
        </button>
      </div>

      <div class="flex items-center gap-2 mb-4 flex-wrap">
        <span class="text-xs font-medium text-gray-500 mr-1">Periodo:</span>
        <button
          v-for="p in PERIODOS"
          :key="p.id"
          @click="periodo = p.id"
          class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
          :class="periodo === p.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        >
          {{ p.label }}
        </button>
      </div>

      <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <div class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-700">
          Filtros
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Carrera</label>
            <select v-model="filtros.carrera" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="">Todas</option>
              <option v-for="c in opciones.carreras" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Año de ingreso</label>
            <select v-model="filtros.anio_ingreso" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="">Todos</option>
              <option v-for="a in opciones.aniosIngreso" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Sexo</label>
            <select v-model="filtros.sexo" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="">Todos</option>
              <option v-for="s in opciones.sexos" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de usuario</label>
            <select v-model="filtros.tipo_usuario" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="">Todos</option>
              <option v-for="tu in opciones.tiposUsuario" :key="tu" :value="tu">{{ tu.charAt(0).toUpperCase() + tu.slice(1) }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-md p-5">
          <p class="text-xs text-gray-500">{{ tabTitulo[tab] }}</p>
          <p class="text-2xl font-mono font-bold text-gray-900">{{ resumen.total }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-5">
          <p class="text-xs text-gray-500">Promedio ({{ PERIODOS.find((p) => p.id === periodo)?.label.toLowerCase() }})</p>
          <p class="text-2xl font-mono font-bold text-gray-900">{{ resumen.promedioPorPeriodo }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-5">
          <p class="text-xs text-gray-500">Categoría más frecuente</p>
          <p class="text-sm font-mono font-bold text-gray-900 truncate">{{ resumen.categoriaMasFrecuente }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <h3 class="font-medium text-gray-900 mb-1">
          {{ TABS.find((t) => t.id === tab)?.label }} — {{ PERIODOS.find((p) => p.id === periodo)?.label }}
        </h3>
        <p class="text-xs text-gray-400 mb-4">Mostrando los últimos {{ resumen.serie.length }} periodos según filtros aplicados</p>
        <div class="h-72">
          <BarChart :data="resumen.serie" :color="colorBar[tab]" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-medium text-gray-900 mb-4">Por carrera</h3>
          <BreakdownList :data="resumen.porCarrera" :total="resumen.total" :color="tab === 'prestamos' ? 'indigo' : tab === 'ingresos' ? 'emerald' : 'amber'" />
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-medium text-gray-900 mb-4">Por sexo</h3>
          <BreakdownList :data="resumen.porSexo" :total="resumen.total" color="blue" />
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-medium text-gray-900 mb-4">Por año de ingreso</h3>
          <BreakdownList :data="resumen.porAnioIngreso" :total="resumen.total" color="purple" />
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
          <h3 class="font-medium text-gray-900 mb-4">Por tipo de usuario</h3>
          <BreakdownList :data="resumen.porTipoUsuario" :total="resumen.total" :color="tab === 'ingresos' ? 'emerald' : 'amber'" />
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 md:col-span-2">
          <h3 class="font-medium text-gray-900 mb-1">Distribución por hora del día</h3>
          <p class="text-xs text-gray-400 mb-4">Cantidad de registros por hora (00:00 – 23:00)</p>
          <div class="h-64">
            <BarChart :data="resumen.porHora" :color="colorBar[tab]" />
          </div>
        </div>
      </div>
    </div>
  </StaffLayout>
</template>
