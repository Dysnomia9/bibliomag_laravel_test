import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'
import type { Staff } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const staff = ref<Staff | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))
  const error = ref<string | null>(null)
  const loading = ref(false)

  async function login(email: string, password: string) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.post('/auth/login', { email, password })
      token.value = data.token
      staff.value = data.staff
      localStorage.setItem('token', data.token)
      return true
    } catch (e: any) {
      error.value = e?.response?.data?.message ?? 'No se pudo iniciar sesión. Verifica tus credenciales.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      token.value = null
      staff.value = null
      localStorage.removeItem('token')
    }
  }

  return { staff, token, error, loading, login, logout }
})
