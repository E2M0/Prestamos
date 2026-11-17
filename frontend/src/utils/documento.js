// Máscaras y validación de documentos y teléfonos.
// El patrón (expresión regular) de cada tipo de documento viene de la base de datos (tipos_documento.patron),
// así React y PHP validan exactamente con la misma regla.

export const PATRON_TELEFONO = /^\d{3}-\d{3}-\d{4}$/

export function aplicarMascaraDocumento(valor, nombreTipo) {
  if (nombreTipo === 'Cédula') {
    const d = valor.replace(/\D/g, '').slice(0, 11)
    return [d.slice(0, 3), d.slice(3, 10), d.slice(10, 11)].filter(Boolean).join('-')
  }
  if (nombreTipo === 'RNC') {
    const d = valor.replace(/\D/g, '').slice(0, 9)
    return [d.slice(0, 1), d.slice(1, 3), d.slice(3, 8), d.slice(8, 9)].filter(Boolean).join('-')
  }
  return valor.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12) // Pasaporte
}

export function aplicarMascaraTelefono(valor) {
  const d = valor.replace(/\D/g, '').slice(0, 10)
  return [d.slice(0, 3), d.slice(3, 6), d.slice(6, 10)].filter(Boolean).join('-')
}

export const normalizarDocumento = (valor) => (valor || '').trim().toUpperCase()

// Compara documentos ignorando guiones (para "el garante no puede ser el mismo cliente").
export const soloAlfanumerico = (valor) => normalizarDocumento(valor).replace(/[^A-Z0-9]/g, '')

// Devuelve '' si es válido, o el mensaje de error.
export function validarDocumento(valor, tipo) {
  if (!tipo) return 'Seleccione el tipo de documento.'
  const numero = normalizarDocumento(valor)
  if (!numero) return 'Ingrese el número de documento.'
  return new RegExp(tipo.patron, 'u').test(numero) ? '' : `Formato inválido. Use ${tipo.mascara}.`
}
