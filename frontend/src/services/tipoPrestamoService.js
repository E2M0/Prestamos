import api from './api'

// filtros: { buscar, estado }
export async function listarTiposPrestamo(filtros = {}) {
  const { data } = await api.get('/tipos-prestamo', { params: filtros })
  return data.datos
}

export async function obtenerTipoPrestamo(id) {
  const { data } = await api.get(`/tipos-prestamo/${id}`)
  return data.datos
}

// Crea (id null) o modifica un tipo de préstamo.
export async function guardarTipoPrestamo(id, payload) {
  const { data } = id ? await api.put(`/tipos-prestamo/${id}`, payload) : await api.post('/tipos-prestamo', payload)
  return data.datos
}

export async function cambiarEstadoTipoPrestamo(id, estado) {
  const { data } = await api.patch(`/tipos-prestamo/${id}/estado`, { estado })
  return data.datos
}
