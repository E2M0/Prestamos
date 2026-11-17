import axios from 'axios'

// Cliente HTTP único de la aplicación. Todos los services lo reutilizan.
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

// Normaliza los errores: siempre se lanza un Error con mensaje legible
// y, si el servidor envió validaciones, quedan en error.errores.
api.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    const cuerpo = error.response?.data
    const mensaje =
      cuerpo?.mensaje ||
      (error.response ? 'El servidor devolvió un error.' : 'No se pudo conectar con el servidor.')
    const normalizado = new Error(mensaje)
    normalizado.estado = error.response?.status
    normalizado.errores = cuerpo?.errores || {}

    // Sesión vencida en un endpoint protegido: se avisa a la app para volver al login.
    const url = error.config?.url || ''
    if (normalizado.estado === 401 && !url.startsWith('/auth/')) {
      window.dispatchEvent(new Event('sesion-expirada'))
    }
    return Promise.reject(normalizado)
  },
)

export default api
