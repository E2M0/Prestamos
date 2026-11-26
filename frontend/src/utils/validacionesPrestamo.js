// Validaciones del formulario de apertura. Mismas claves de error que devuelve el servidor (PrestamoValidador.php).
// Importante: aquí solo se valida; ningún cálculo financiero se hace en React.
import { formatoMoneda } from './formato'

const MAX_DECIMAL = 999999999999.99
const MAX_CUOTAS = 600
const MAX_TASA = 100

export const aperturaVacia = (hoy) => ({
  tipoPrestamoId: '',
  monto: '',
  plazo: '',
  tasa: '',
  tipoPlazoId: '',
  tipoAmortizacionId: '',
  incluyeSabado: true,
  incluyeDomingo: true,
  tipoMora: 'Porcentaje',
  valorMora: '',
  seguroTipo: 'Fijo',
  seguroMonto: '',
  gastoCierre: '',
  gastoFinanciado: false,
  fechaApertura: hoy,
  fechaPrimerPago: '',
})

const numero = (v) => (String(v).trim() === '' ? null : Number(v))

export function esFechaReal(texto) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(texto)) return false
  const fecha = new Date(`${texto}T00:00:00Z`) // en UTC para que el resultado no dependa de la zona horaria
  return !Number.isNaN(fecha.getTime()) && fecha.toISOString().slice(0, 10) === texto
}

// credito: respuesta de /clientes/{id}/credito (null si aún no se eligió cliente).
// opciones.omitirLimite: en la renovación el límite lo valida el servidor (el capital cancelado queda libre).
export function validarApertura(f, cat, credito, { omitirLimite = false } = {}) {
  const e = {}

  if (!credito) e.cliente = 'Seleccione el cliente.'
  else if (credito.estado !== 'Activo') e.cliente = 'El cliente está inactivo.'

  if (!cat.tiposPrestamo.some((t) => t.id === Number(f.tipoPrestamoId))) e.tipoPrestamoId = 'Seleccione el tipo de préstamo.'

  const reglas = cat.reglas ?? {}   // reglas de negocio que envía el servidor (tabla reglas_negocio)
  const pct = (v) => String(Number(v))
  const monto = numero(f.monto)
  if (monto === null || !(monto > 0) || monto > MAX_DECIMAL) e.monto = 'Ingrese un monto mayor que cero.'
  else if (reglas.monto_minimo !== undefined && monto < reglas.monto_minimo) {
    e.monto = `El monto mínimo de un préstamo es ${formatoMoneda(reglas.monto_minimo)}.`
  }
  else if (!omitirLimite && credito && monto > credito.disponible) {
    e.monto = `El monto supera el límite disponible (${formatoMoneda(credito.disponible)}).`
  }

  const plazo = numero(f.plazo)
  if (plazo === null || !Number.isInteger(plazo) || plazo < 1 || plazo > MAX_CUOTAS) {
    e.plazo = `Ingrese un número de cuotas entre 1 y ${MAX_CUOTAS}.`
  }

  const tasa = numero(f.tasa)
  if (tasa === null || tasa < 0 || tasa > MAX_TASA) e.tasa = `Ingrese una tasa mensual entre 0 y ${MAX_TASA}.`
  else if ((reglas.tasa_minima !== undefined && tasa < reglas.tasa_minima) || (reglas.tasa_maxima !== undefined && tasa > reglas.tasa_maxima)) {
    e.tasa = `La tasa mensual debe estar entre ${pct(reglas.tasa_minima ?? 0)} % y ${pct(reglas.tasa_maxima ?? MAX_TASA)} %.`
  }

  const tipoPlazo = cat.tiposPlazo.find((p) => p.id === Number(f.tipoPlazoId))
  if (!tipoPlazo) e.tipoPlazoId = 'Seleccione el tipo de plazo.'
  else if (!e.plazo && (plazo < tipoPlazo.plazoMinimo || plazo > tipoPlazo.plazoMaximo)) {
    e.plazo = `El plazo ${tipoPlazo.nombre} permite de ${tipoPlazo.plazoMinimo} a ${tipoPlazo.plazoMaximo} cuotas.`
  }
  if (!cat.tiposAmortizacion.some((a) => a.id === Number(f.tipoAmortizacionId))) {
    e.tipoAmortizacionId = 'Seleccione el tipo de amortización.'
  }

  const mora = numero(f.valorMora)
  if (mora === null || mora < 0 || mora > MAX_DECIMAL) e.valorMora = 'Ingrese el valor de la mora (0 o mayor).'
  else if (f.tipoMora === 'Porcentaje' && mora > 100) e.valorMora = 'El porcentaje no puede pasar de 100.'
  else if (f.tipoMora === 'Porcentaje' && reglas.mora_porcentaje_maxima !== undefined && mora > reglas.mora_porcentaje_maxima) {
    e.valorMora = `La mora no puede pasar de ${pct(reglas.mora_porcentaje_maxima)} % de la cuota.`
  } else if (f.tipoMora === 'Monto' && monto > 0 && reglas.mora_fija_maxima_pct !== undefined && mora > (monto * reglas.mora_fija_maxima_pct) / 100) {
    e.valorMora = `La mora fija no puede pasar de ${formatoMoneda((monto * reglas.mora_fija_maxima_pct) / 100)} (${pct(reglas.mora_fija_maxima_pct)} % del monto).`
  }

  const seguro = numero(f.seguroMonto)
  if (seguro === null || seguro < 0 || seguro > MAX_DECIMAL) e.seguroMonto = 'Ingrese el monto del seguro (0 si no aplica).'
  else if (monto > 0 && Number.isInteger(plazo) && plazo > 0 && reglas.seguro_total_maximo_pct !== undefined) {
    const seguroTotal = f.seguroTipo === 'Fijo' ? seguro * plazo : seguro   // 'Fijo' es por cuota; 'Repartido' ya es el total
    const tope = (monto * reglas.seguro_total_maximo_pct) / 100
    if (seguroTotal > tope) {
      e.seguroMonto = `El seguro total (${formatoMoneda(seguroTotal)}) no puede pasar de ${pct(reglas.seguro_total_maximo_pct)} % del monto (${formatoMoneda(tope)}).`
    }
  }

  const gasto = numero(f.gastoCierre)
  if (gasto === null || gasto < 0 || gasto > MAX_DECIMAL) e.gastoCierre = 'Ingrese el gasto de cierre (0 si no aplica).'
  else if (!f.gastoFinanciado && monto !== null && gasto >= monto) {
    e.gastoCierre = 'El gasto de cierre debe ser menor que el monto para que quede desembolso.'
  } else if (monto > 0 && reglas.gasto_cierre_maximo_pct !== undefined && gasto > (monto * reglas.gasto_cierre_maximo_pct) / 100) {
    e.gastoCierre = `El gasto de cierre no puede pasar de ${pct(reglas.gasto_cierre_maximo_pct)} % del monto (${formatoMoneda((monto * reglas.gasto_cierre_maximo_pct) / 100)}).`
  }

  if (!esFechaReal(f.fechaApertura)) e.fechaApertura = 'Ingrese una fecha de apertura válida.'
  if (!esFechaReal(f.fechaPrimerPago)) e.fechaPrimerPago = 'Ingrese la fecha del primer pago.'
  else if (esFechaReal(f.fechaApertura) && f.fechaPrimerPago < f.fechaApertura) {
    e.fechaPrimerPago = 'El primer pago no puede ser anterior a la apertura.'
  }

  return e
}

// Datos que se envían al servidor (Calcular y Guardar). Solo se convierten tipos; no se calcula nada.
export const aPayloadApertura = (f, credito) => ({
  clienteId: credito?.id ?? null,
  tipoPrestamoId: Number(f.tipoPrestamoId),
  monto: Number(f.monto),
  plazo: Number(f.plazo),
  tasa: Number(f.tasa),
  tipoPlazoId: Number(f.tipoPlazoId),
  tipoAmortizacionId: Number(f.tipoAmortizacionId),
  incluyeSabado: Boolean(f.incluyeSabado),
  incluyeDomingo: Boolean(f.incluyeDomingo),
  tipoMora: f.tipoMora,
  valorMora: Number(f.valorMora),
  seguroTipo: f.seguroTipo,
  seguroMonto: Number(f.seguroMonto),
  gastoCierre: Number(f.gastoCierre),
  gastoFinanciado: Boolean(f.gastoFinanciado),
  fechaApertura: f.fechaApertura,
  fechaPrimerPago: f.fechaPrimerPago,
})

// Renovación: de 1 a 3 préstamos, cada uno con su base de saldo.
export function validarRenovar(renovar) {
  if (renovar.length < 1) return 'Seleccione al menos un préstamo para renovar.'
  if (renovar.length > 3) return 'Máximo 3 préstamos por renovación.'
  if (renovar.some((r) => !r.base)) return 'Elija la base de saldo de cada préstamo seleccionado.'
  return ''
}
