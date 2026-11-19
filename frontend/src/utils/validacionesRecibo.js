// Validaciones de pantalla del pago. El servidor valida de nuevo (y es quien manda).
export const TIPOS_PAGO = [
  { valor: 'normal', etiqueta: 'Pago normal (cuotas)' },
  { valor: 'solo_mora', etiqueta: 'Solo mora' },
  { valor: 'abono_capital', etiqueta: 'Abono a capital' },
]
export const MODALIDADES = [
  { valor: 'Reducir cuota', etiqueta: 'Reducir cuota (mismo plazo)' },
  { valor: 'Reducir plazo', etiqueta: 'Reducir plazo (menos cuotas)' },
]
export const FORMATOS = [
  { valor: 'A4', etiqueta: 'A4 (hoja)' },
  { valor: 'POS80', etiqueta: 'POS 80 mm (ticket)' },
]
export const NOMBRE_TIPO = { normal: 'Pago normal', solo_mora: 'Pago de mora', abono_capital: 'Abono a capital' }

// Acepta 62590.72, 62,590.72 y 62.590,72; devuelve un texto con punto decimal o '' si no es un número válido.
export function normalizarMonto(valor) {
  let t = String(valor ?? '').trim().replace(/\s|RD\$/gi, '')
  const coma = t.lastIndexOf(',')
  const punto = t.lastIndexOf('.')
  if (coma > -1 && punto > -1) {
    // el último separador es el decimal; el otro es de miles
    t = coma > punto ? t.replace(/\./g, '').replace(',', '.') : t.replace(/,/g, '')
  } else if (coma > -1) {
    t = /,\d{1,2}$/.test(t) && t.indexOf(',') === coma ? t.replace(',', '.') : t.replace(/,/g, '')
  }
  return /^\d+(\.\d{1,2})?$/.test(t) ? t : ''
}

export function validarPago(form, prestamo) {
  const errores = {}
  if (!prestamo) errores.prestamo = 'Seleccione un préstamo.'
  else if (prestamo.estado !== 'Activo') errores.prestamo = `El préstamo está ${prestamo.estado} y no admite pagos.`
  if (!form.tipoPago) errores.tipoPago = 'Seleccione el tipo de pago.'

  const texto = normalizarMonto(form.monto)
  if (texto === '' || Number(texto) <= 0) {
    errores.monto = 'Ingrese un monto mayor que cero (máximo 2 decimales).'
  }
  if (form.tipoPago === 'abono_capital' && !form.modalidad) errores.modalidad = 'Elija reducir cuota o reducir plazo.'
  if ((form.observacion ?? '').length > 250) errores.observacion = 'Máximo 250 caracteres.'
  return errores
}

export function aPayloadPago(form, prestamoId) {
  return {
    prestamo: prestamoId,
    tipoPago: form.tipoPago,
    monto: Number(normalizarMonto(form.monto)),
    modalidad: form.tipoPago === 'abono_capital' ? form.modalidad : null,
    formato: form.formato,
    observacion: (form.observacion ?? '').trim(),
  }
}
