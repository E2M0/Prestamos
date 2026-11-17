import api from './api'

// Devuelve el usuario conectado o null.
export async function obtenerSesion() {
  const { data } = await api.get('/auth/yo')
  return data.datos
}

export async function iniciarSesion(usuario, clave) {
  const { data } = await api.post('/auth/login', { usuario, clave })
  return data.datos
}

export async function cerrarSesion() {
  await api.post('/auth/logout')
}
