import axios from 'axios'

// Si no se define VITE_API_URL, arma la URL del backend usando el mismo host
// desde el que se accedió al frontend (localhost, IP de red local, etc.) para
// que funcione igual desde el navegador del PC que desde un celular en la
// misma WiFi.
const defaultApiUrl = `${window.location.protocol}//${window.location.hostname}:8000/api`

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? defaultApiUrl,
  withCredentials: true,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
