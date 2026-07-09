import { ref } from 'vue'
import api from '@/services/api'

const nombresStaff = ref<string[]>([])
let cargado = false

export function useStaffNombres() {
  async function cargarStaffNombres() {
    if (cargado) return
    try {
      const { data } = await api.get<{ id: number; nombre: string }[]>('/staff')
      nombresStaff.value = data.map((s) => s.nombre)
      cargado = true
    } catch {
      // Silencioso: el campo "registrado por" sigue siendo texto libre sin autocompletado.
    }
  }

  return { nombresStaff, cargarStaffNombres }
}
