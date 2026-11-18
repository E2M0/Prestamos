// Mismas reglas que TipoPrestamoService.php (el servidor siempre vuelve a validar).
const limpio = (v) => String(v ?? '').trim().replace(/\s+/g, ' ')

export const tipoPrestamoVacio = () => ({ id: null, nombre: '', descripcion: '', estado: 'Activo', prestamos: 0 })

export function validarTipoPrestamo(tipo) {
  const e = {}
  const largo = limpio(tipo.nombre).length
  if (largo < 2 || largo > 60) e.nombre = 'Ingrese el nombre (entre 2 y 60 caracteres).'
  if (limpio(tipo.descripcion).length > 250) e.descripcion = 'Máximo 250 caracteres.'
  return e
}

export const aPayloadTipoPrestamo = (tipo) => ({ nombre: limpio(tipo.nombre), descripcion: limpio(tipo.descripcion) })
