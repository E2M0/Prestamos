import api from './api'

// filtros: { buscar, estado, rol }
export async function listarClientes(filtros = {}) {
  const { data } = await api.get('/clientes', { params: filtros })
  return data.datos
}

export async function obtenerCliente(id) {
  const { data } = await api.get(`/clientes/${id}`)
  return data.datos
}

// Crea (id null) o modifica un cliente con su garante, en una sola operación del servidor.
export async function guardarCliente(id, payload) {
  const { data } = id ? await api.put(`/clientes/${id}`, payload) : await api.post('/clientes', payload)
  return data.datos
}

export async function cambiarEstadoCliente(id, estado) {
  const { data } = await api.patch(`/clientes/${id}/estado`, { estado })
  return data.datos
}

// Busca una persona por documento (garante y búsqueda inversa). Devuelve { existe, esCliente, esGarante, estado, persona }.
export async function buscarPersona(documento) {
  const { data } = await api.get('/personas/buscar', { params: { documento } })
  return data.datos
}
