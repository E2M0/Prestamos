// Formatos de presentación (los cálculos financieros siempre los hace el backend).
const moneda = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' })
const compacto = new Intl.NumberFormat('es-DO', { notation: 'compact', maximumFractionDigits: 1 })

export const formatoMoneda = (valor) => moneda.format(Number(valor) || 0)
export const formatoCompacto = (valor) => compacto.format(Number(valor) || 0)

export function formatoFechaHora(iso) {
  const fecha = new Date(iso)
  if (Number.isNaN(fecha.getTime())) return ''
  return fecha.toLocaleString('es-DO', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
