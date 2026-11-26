// Validaciones del formulario de clientes. Repiten las del servidor (ClienteValidador.php):
// el usuario ve los errores al instante, pero el servidor siempre vuelve a validar.
// Las claves de error usan notación de puntos, igual que el servidor.
import { erroresCredito } from './creditoCliente'
import { PATRON_TELEFONO, soloAlfanumerico, validarDocumento } from './documento'

const PATRON_NOMBRE = /^[\p{L}][\p{L} .'-]{1,99}$/u
const PATRON_CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const MAX_DECIMAL = 999999999999.99

const limpio = (v) => String(v ?? '').trim().replace(/\s+/g, ' ')
const numero = (v) => (String(v).trim() === '' ? null : Number(v))
const tipoDe = (p, cat) => cat.tiposDocumento.find((t) => t.id === Number(p.tipoDocumentoId))

function validarFecha(valor, clave, e) {
  const fecha = new Date(`${valor}T00:00:00`)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(valor) || Number.isNaN(fecha.getTime())) {
    e[clave] = 'Ingrese una fecha de nacimiento válida.'
    return
  }
  const hoy = new Date()
  hoy.setHours(0, 0, 0, 0)
  if (fecha > hoy) {
    e[clave] = 'La fecha de nacimiento no puede ser futura.'
    return
  }
  let edad = hoy.getFullYear() - fecha.getFullYear()
  const cumple = new Date(hoy.getFullYear(), fecha.getMonth(), fecha.getDate())
  if (hoy < cumple) edad -= 1
  if (edad < 18) e[clave] = 'La persona debe ser mayor de 18 años.'
  else if (edad > 110) e[clave] = 'Revise la fecha de nacimiento.'
}

function validarTelefonos(lista, pre, e) {
  const k = `${pre}telefonos`
  if (!lista.length) {
    e[k] = 'Registre al menos un teléfono.'
    return
  }
  const vistos = new Set()
  lista.forEach((t, i) => {
    if (!PATRON_TELEFONO.test(t.numero)) e[`${k}.${i}.numero`] = 'Use el formato 809-000-0000.'
    else if (vistos.has(t.numero)) e[`${k}.${i}.numero`] = 'Teléfono repetido.'
    vistos.add(t.numero)
  })
  if (lista.filter((t) => t.principal).length !== 1) e[k] = 'Marque exactamente un teléfono como principal.'
}

function validarLaboral(l, pre, e) {
  const k = `${pre}laboral.`
  const largo = (campo, min, max, mensaje) => {
    const n = limpio(l[campo]).length
    if (n < min || n > max) e[k + campo] = mensaje
  }
  largo('empresa', 2, 120, 'Ingrese la empresa.')
  largo('cargoOcupacion', 2, 80, 'Ingrese la ocupación o cargo.')
  largo('tiempoLaborando', 1, 40, 'Indique el tiempo laborando.')
  largo('direccionEmpresa', 5, 250, 'Ingrese la dirección del trabajo.')
  const sueldo = numero(l.sueldo)
  if (sueldo === null || !(sueldo > 0) || sueldo > MAX_DECIMAL) e[`${k}sueldo`] = 'Ingrese un sueldo mayor que cero.'
  if (!PATRON_TELEFONO.test(l.telefonoEmpresa)) e[`${k}telefonoEmpresa`] = 'Use el formato 809-000-0000.'
}

// Persona completa (cliente o garante). `pre` es el prefijo de las claves ('' o 'garante.').
export function validarPersona(p, cat, pre = '') {
  const e = {}
  if (!tipoDe(p, cat)) e[`${pre}tipoDocumentoId`] = 'Seleccione el tipo de documento.'
  const msgDoc = validarDocumento(p.numeroDocumento, tipoDe(p, cat))
  if (msgDoc && tipoDe(p, cat)) e[`${pre}numeroDocumento`] = msgDoc
  else if (msgDoc && !p.numeroDocumento.trim()) e[`${pre}numeroDocumento`] = 'Ingrese el número de documento.'

  if (!PATRON_NOMBRE.test(limpio(p.nombres))) e[`${pre}nombres`] = 'Ingrese los nombres (solo letras, mínimo 2).'
  if (!PATRON_NOMBRE.test(limpio(p.apellidos))) e[`${pre}apellidos`] = 'Ingrese los apellidos (solo letras, mínimo 2).'
  if (!cat.nacionalidades.some((n) => n.id === Number(p.nacionalidadId))) {
    e[`${pre}nacionalidadId`] = 'Seleccione la nacionalidad.'
  }
  validarFecha(p.fechaNacimiento, `${pre}fechaNacimiento`, e)
  if (!cat.estadosCiviles.includes(p.estadoCivil)) e[`${pre}estadoCivil`] = 'Seleccione el estado civil.'

  const correo = p.correo.trim()
  if (correo && (correo.length > 150 || !PATRON_CORREO.test(correo))) {
    e[`${pre}correo`] = 'Ingrese un correo electrónico válido.'
  }
  const dir = limpio(p.direccion).length
  if (dir < 5 || dir > 250) e[`${pre}direccion`] = 'Ingrese la dirección (mínimo 5 caracteres).'
  if (limpio(p.referenciaDireccion).length > 250) e[`${pre}referenciaDireccion`] = 'Máximo 250 caracteres.'

  validarTelefonos(p.telefonos, pre, e)
  validarLaboral(p.laboral, pre, e)
  return e
}

export function validarCliente(form, cat) {
  const e = validarPersona(form, cat)

  Object.assign(e, erroresCredito(form.limiteCredito, form.clasificacion))

  // Referencias: opcionales; una fila vacía se ignora y, si se llena algo, nombre y teléfono son obligatorios
  form.referencias.forEach((r, i) => {
    if (!limpio(r.nombre) && !r.telefono.trim()) return
    if (limpio(r.nombre).length < 2) e[`referencias.${i}.nombre`] = 'Ingrese el nombre.'
    if (!PATRON_TELEFONO.test(r.telefono)) e[`referencias.${i}.telefono`] = 'Use el formato 809-000-0000.'
  })

  // Familiares: opcionales; si se llena algo, nombre y parentesco son obligatorios
  for (const clave of ['cercano', 'noConvive']) {
    const f = form.familiares[clave]
    if (!Object.values(f).some((v) => limpio(v))) continue
    if (limpio(f.nombre).length < 2) e[`familiares.${clave}.nombre`] = 'Ingrese el nombre.'
    if (limpio(f.parentesco).length < 2) e[`familiares.${clave}.parentesco`] = 'Ingrese el parentesco.'
    if (f.telefono.trim() && !PATRON_TELEFONO.test(f.telefono)) {
      e[`familiares.${clave}.telefono`] = 'Use el formato 809-000-0000.'
    }
  }

  // Garante: obligatorio
  const g = form.garante
  if (!g.buscado) {
    e['garante.numeroDocumento'] = 'Busque el documento del garante para continuar.'
  } else if (g.existente && !g.actualizar) {
    const msg = validarDocumento(g.numeroDocumento, tipoDe(g, cat))
    if (msg) e['garante.numeroDocumento'] = msg
  } else {
    Object.assign(e, validarPersona(g, cat, 'garante.'))
  }
  if (g.numeroDocumento && soloAlfanumerico(g.numeroDocumento) === soloAlfanumerico(form.numeroDocumento)) {
    e['garante.numeroDocumento'] = 'El garante no puede tener el mismo documento que el cliente.'
  }
  return e
}
