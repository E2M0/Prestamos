// Estado del formulario de clientes: valores iniciales y conversión desde/hacia la API.

export const laboralVacio = () => ({
  empresa: '', cargoOcupacion: '', tiempoLaborando: '', sueldo: '', telefonoEmpresa: '', direccionEmpresa: '',
})

export const contactoVacio = () => ({ nombre: '', parentesco: '', telefono: '', direccion: '' })

export const personaVacia = () => ({
  id: null,
  tipoDocumentoId: '',
  numeroDocumento: '',
  nombres: '',
  apellidos: '',
  nacionalidadId: '',
  fechaNacimiento: '',
  estadoCivil: '',
  correo: '',
  direccion: '',
  referenciaDireccion: '',
  telefonos: [{ tipo: 'Celular', numero: '', principal: true }],
  laboral: laboralVacio(),
})

// existente: la persona ya estaba registrada · actualizar: se editan sus datos · buscado: ya se consultó el documento
export const garanteVacio = () => ({ ...personaVacia(), existente: false, actualizar: false, buscado: false })

export const clienteVacio = () => ({
  ...personaVacia(),
  limiteCredito: '',
  clasificacion: '',
  estado: 'Activo',
  familiares: { cercano: contactoVacio(), noConvive: contactoVacio() },
  referencias: [0, 1, 2].map(() => ({ nombre: '', telefono: '' })),
  garante: garanteVacio(),
})

// Persona que viene de la API (los null pasan a texto vacío para los inputs controlados).
export function personaDeDetalle(p) {
  return {
    id: p.id ?? null,
    tipoDocumentoId: p.tipoDocumentoId ?? '',
    numeroDocumento: p.numeroDocumento ?? '',
    nombres: p.nombres ?? '',
    apellidos: p.apellidos ?? '',
    nacionalidadId: p.nacionalidadId ?? '',
    fechaNacimiento: p.fechaNacimiento ?? '',
    estadoCivil: p.estadoCivil ?? '',
    correo: p.correo ?? '',
    direccion: p.direccion ?? '',
    referenciaDireccion: p.referenciaDireccion ?? '',
    telefonos: p.telefonos?.length
      ? p.telefonos.map((t) => ({ tipo: t.tipo, numero: t.numero, principal: Boolean(t.principal) }))
      : [{ tipo: 'Celular', numero: '', principal: true }],
    laboral: p.laboral ? { ...laboralVacio(), ...p.laboral, sueldo: p.laboral.sueldo ?? '' } : laboralVacio(),
  }
}

export function clienteDeDetalle(d) {
  return {
    ...personaDeDetalle(d),
    limiteCredito: d.limiteCredito ?? '',
    clasificacion: d.clasificacion ?? '',
    estado: d.estado ?? 'Activo',
    familiares: {
      cercano: { ...contactoVacio(), ...d.familiares?.cercano },
      noConvive: { ...contactoVacio(), ...d.familiares?.noConvive },
    },
    referencias: [0, 1, 2].map((i) => ({
      nombre: d.referencias?.[i]?.nombre ?? '',
      telefono: d.referencias?.[i]?.telefono ?? '',
    })),
    garante: d.garante
      ? { ...personaDeDetalle(d.garante), existente: true, actualizar: false, buscado: true }
      : garanteVacio(),
  }
}

const personaAPayload = (p) => ({
  tipoDocumentoId: Number(p.tipoDocumentoId),
  numeroDocumento: p.numeroDocumento.trim(),
  nombres: p.nombres.trim(),
  apellidos: p.apellidos.trim(),
  nacionalidadId: Number(p.nacionalidadId),
  fechaNacimiento: p.fechaNacimiento,
  estadoCivil: p.estadoCivil,
  correo: p.correo.trim(),
  direccion: p.direccion.trim(),
  referenciaDireccion: p.referenciaDireccion.trim(),
  telefonos: p.telefonos.map((t) => ({ tipo: t.tipo, numero: t.numero, principal: Boolean(t.principal) })),
  laboral: { ...p.laboral, sueldo: Number(p.laboral.sueldo) },
})

export function aPayload(form) {
  return {
    ...personaAPayload(form),
    limiteCredito: Number(form.limiteCredito),
    clasificacion: form.clasificacion,
    familiares: form.familiares,
    referencias: form.referencias,
    garante: { ...personaAPayload(form.garante), actualizar: Boolean(form.garante.actualizar) },
  }
}

// Filtra los errores que empiezan por un prefijo ('garante.') y se lo quita: los componentes
// reutilizables de persona siempre trabajan con claves relativas ('nombres', 'telefonos.0.numero').
export function subErrores(errores, prefijo) {
  const resultado = {}
  for (const [clave, mensaje] of Object.entries(errores)) {
    if (clave.startsWith(prefijo)) resultado[clave.slice(prefijo.length)] = mensaje
  }
  return resultado
}
