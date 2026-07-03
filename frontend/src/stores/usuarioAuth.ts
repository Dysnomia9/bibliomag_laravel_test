import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiUsuario from '@/services/apiUsuario'
import type { Usuario } from '@/types'

export const useUsuarioAuthStore = defineStore('usuarioAuth', () => {
  const usuario = ref<Usuario | null>(null)
  const token = ref<string | null>(localStorage.getItem('usuario_token'))
  const error = ref<string | null>(null)
  const loading = ref(false)

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
      localStorage.removeItem('usuario_token')
    }
  }

  return { usuario, token, error, loading, login, logout }
})
