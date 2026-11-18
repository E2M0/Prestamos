import api from './api'

// Listas desplegables de la apertura (tipos de préstamo activos, plazos, amortizaciones, fecha de hoy).
export async function obtenerCatalogosPrestamos() {
  const { data } = await api.get('/catalogos/prestamos')
  return data.datos
}

// Límite, capital pendiente y disponible del cliente, más su garante vigente.
export async function obtenerCreditoCliente(clienteId) {
  const { data } = await api.get(`/clientes/${clienteId}/credito`)
  return data.datos
}

// El servidor calcula la fecha sugerida del primer pago según el tipo de plazo.
export async function obtenerFechaPrimerPago(parametros) {
  const { data } = await api.get('/prestamos/fecha-primer-pago', { params: parametros })
  return data.datos.fechaPrimerPago
}
