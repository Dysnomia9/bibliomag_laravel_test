import axios from 'axios'

const apiUsuario = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
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
