import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiUsuario from '@/services/apiUsuario'
import type { Usuario } from '@/types'

export const useUsuarioAuthStore = defineStore('usuarioAuth', () => {
  const usuario = ref<Usuario | null>(null)
  const token = ref<string | null>(localStorage.getItem('usuario_token'))
  const error = ref<string | null>(null)
  const loading = ref(false)
  const validated = ref(false)

  async function login(rut: string, password: string) {
    loading.value = true
    error.value = null
    try {
      const { data } = await apiUsuario.post('/auth/usuario/login', { rut, password })
      token.value = data.token
      usuario.value = data.usuario
      localStorage.setItem('usuario_token', data.token)
      return true
    } catch (e: any) {
      error.value = e?.response?.data?.message ?? 'No se pudo iniciar sesión. Verifica tu RUT y contraseña.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await apiUsuario.post('/auth/usuario/logout')
    } finally {
      token.value = null
      usuario.value = null
      validated.value = false
      localStorage.removeItem('usuario_token')
    }
  }

  /** Ver auth.ts (staff) — misma lógica para la sesión del portal. */
  async function validar(): Promise<boolean> {
    if (!token.value) return false
    if (validated.value) return true

    try {
      const { data } = await apiUsuario.get('/auth/usuario/me')
      usuario.value = data
      validated.value = true
      return true
    } catch (e: any) {
      if (e?.response?.status === 401) {
        return false
      }
      return true
    }
  }

  return { usuario, token, error, loading, login, logout, validar }
})
