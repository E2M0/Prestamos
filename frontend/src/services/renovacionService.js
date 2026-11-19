import api from './api'

// Préstamos activos del cliente con el saldo a cancelar según cada base de saldo.
export async function obtenerPrestamosRenovables(clienteId) {
  const { data } = await api.get(`/clientes/${clienteId}/prestamos-renovables`)
  return data.datos
}

// Botón Calcular: cálculo del préstamo nuevo + saldos a cancelar + desembolso final. No guarda nada.
export async function calcularRenovacion(payload) {
  const { data } = await api.post('/renovaciones/calcular', payload)
  return data.datos
}

// Botón Renovar: una sola transacción en el servidor (recibos de cancelación, préstamos anteriores y préstamo nuevo).
export async function guardarRenovacion(payload) {
  const { data } = await api.post('/renovaciones', payload)
  return data.datos
}
