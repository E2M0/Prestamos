import api from './api'

// Préstamos para el modal de consulta (buscar por número, cliente o documento).
export async function listarPrestamos(filtros = {}) {
  const { data } = await api.get('/prestamos', { params: filtros })
  return data.datos
}

// Situación del préstamo: cuotas con lo pendiente, mora, valores sugeridos e historial de recibos.
export async function obtenerCuenta(prestamoId) {
  const { data } = await api.get(`/prestamos/${prestamoId}/cuenta`)
  return data.datos
}

// Vista previa: el servidor calcula cómo se aplicaría el pago. No guarda nada.
export async function calcularPago(payload) {
  const { data } = await api.post('/recibos/calcular', payload)
  return data.datos
}

// Cobrar: el servidor vuelve a calcular y guarda recibo, cuotas y préstamo en una transacción.
export async function guardarPago(payload) {
  const { data } = await api.post('/recibos', payload)
  return data.datos
}

export async function obtenerRecibo(id) {
  const { data } = await api.get(`/recibos/${id}`)
  return data.datos
}

export async function cambiarFormatoRecibo(id, formato) {
  const { data } = await api.patch(`/recibos/${id}/formato`, { formato })
  return data.datos
}

export async function enviarReciboPorCorreo(id, correo) {
  const { data } = await api.post(`/recibos/${id}/correo`, { correo })
  return data.datos
}
