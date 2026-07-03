import axios from 'axios'

const defaultApiUrl = `${window.location.protocol}//${window.location.hostname}:8000/api`

const apiUsuario = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? defaultApiUrl,
  withCredentials: true,
})

apiUsuario.interceptors.request.use((config) => {
  const token = localStorage.getItem('usuario_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default apiUsuario
