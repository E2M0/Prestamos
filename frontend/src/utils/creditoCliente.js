// Reglas del crédito de los clientes. Repiten las de ClienteValidador.php (el servidor siempre vuelve a validar).
export const LIMITE_MINIMO = 5000
export const LIMITE_MAXIMO = 10000000
export const LIMITE_INICIAL = '5000'

export const TRAMOS_CREDITO = {
  C: { nombre: 'Regular', desde: 5000, hasta: 100000 },
  B: { nombre: 'Bueno', desde: 100001, hasta: 1000000 },
  A: { nombre: 'Excelente', desde: 1000001, hasta: 10000000 },
}

export const dinero = (v) => `RD$ ${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const entero = (v) => `RD$ ${Number(v).toLocaleString('en-US')}`

export const etiquetaClasificacion = (c) => {
  const t = TRAMOS_CREDITO[c]
  return t ? `${c} — ${t.nombre} (${entero(t.desde)} - ${entero(t.hasta)})` : c
}

// Clasificación que corresponde a un límite; null si el límite está vacío o fuera de rango.
export function clasificacionPara(limite) {
  if (limite === '' || limite === null || limite === undefined) return null
  const n = Number(limite)
  if (!Number.isFinite(n) || n < LIMITE_MINIMO || n > LIMITE_MAXIMO) return null
  if (n <= TRAMOS_CREDITO.C.hasta) return 'C'
  return n <= TRAMOS_CREDITO.B.hasta ? 'B' : 'A'
}

// Mensajes de error/advertencia del crédito. Devuelve { limiteCredito?, clasificacion? }.
export function erroresCredito(limite, clasificacion) {
  const e = {}
  const n = String(limite ?? '').trim() === '' ? null : Number(limite)
  const correcta = n === null ? null : clasificacionPara(n)
  if (correcta === null) {
    e.limiteCredito = `El límite de crédito debe estar entre ${dinero(LIMITE_MINIMO)} y ${dinero(LIMITE_MAXIMO)}.`
  }
  if (!TRAMOS_CREDITO[clasificacion]) {
    e.clasificacion = 'Seleccione la clasificación del cliente (A, B o C).'
  } else if (correcta !== null && clasificacion !== correcta) {
    const t = TRAMOS_CREDITO[clasificacion]
    e.clasificacion = `La clasificación ${clasificacion} (${t.nombre}) admite límites de ${dinero(t.desde)} a ${dinero(t.hasta)}. Con ${dinero(n)} corresponde la clasificación ${correcta}.`
  }
  return e
}
