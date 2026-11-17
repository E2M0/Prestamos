import Campo from '../Campo'
import CampoSelect from '../CampoSelect'
import { aplicarMascaraDocumento } from '../../utils/documento'

// Datos generales de una persona (cliente o garante). Los errores llegan con claves relativas.
//  - prefijoId: evita ids repetidos cuando hay dos personas en la misma pantalla ('cliente' | 'garante').
//  - ocultarDocumento: el garante tiene su propia fila de búsqueda por documento.
export default function CamposPersona({
  prefijoId, valor, onCambio, errores, catalogos, soloLectura = false, ocultarDocumento = false, onDocumentoSalir,
}) {
  const tipo = catalogos.tiposDocumento.find((t) => t.id === Number(valor.tipoDocumentoId))
  const id = (campo) => `${prefijoId}-${campo}`

  return (
    <div className="rejilla">
      {!ocultarDocumento && (
        <>
          <CampoSelect
            id={id('tipoDocumentoId')}
            etiqueta="Tipo de documento"
            value={valor.tipoDocumentoId}
            disabled={soloLectura}
            error={errores.tipoDocumentoId}
            opciones={catalogos.tiposDocumento.map((t) => ({ valor: t.id, etiqueta: t.nombre }))}
            onChange={(e) =>
              onCambio({ tipoDocumentoId: e.target.value ? Number(e.target.value) : '', numeroDocumento: '' })
            }
          />
          <Campo
            id={id('numeroDocumento')}
            etiqueta="Número de documento"
            value={valor.numeroDocumento}
            readOnly={soloLectura}
            placeholder={tipo?.mascara ?? 'Seleccione primero el tipo'}
            maxLength={tipo?.longitud ?? 20}
            error={errores.numeroDocumento}
            onChange={(e) => onCambio({ numeroDocumento: aplicarMascaraDocumento(e.target.value, tipo?.nombre) })}
            onBlur={onDocumentoSalir}
          />
        </>
      )}
      <Campo
        id={id('nombres')}
        etiqueta="Nombres"
        value={valor.nombres}
        readOnly={soloLectura}
        error={errores.nombres}
        onChange={(e) => onCambio({ nombres: e.target.value })}
      />
      <Campo
        id={id('apellidos')}
        etiqueta="Apellidos"
        value={valor.apellidos}
        readOnly={soloLectura}
        error={errores.apellidos}
        onChange={(e) => onCambio({ apellidos: e.target.value })}
      />
      <CampoSelect
        id={id('nacionalidadId')}
        etiqueta="Nacionalidad"
        value={valor.nacionalidadId}
        disabled={soloLectura}
        error={errores.nacionalidadId}
        opciones={catalogos.nacionalidades.map((n) => ({ valor: n.id, etiqueta: n.nombre }))}
        onChange={(e) => onCambio({ nacionalidadId: e.target.value ? Number(e.target.value) : '' })}
      />
      <Campo
        id={id('fechaNacimiento')}
        etiqueta="Fecha de nacimiento"
        type="date"
        value={valor.fechaNacimiento}
        readOnly={soloLectura}
        error={errores.fechaNacimiento}
        onChange={(e) => onCambio({ fechaNacimiento: e.target.value })}
      />
      <CampoSelect
        id={id('estadoCivil')}
        etiqueta="Estado civil"
        value={valor.estadoCivil}
        disabled={soloLectura}
        error={errores.estadoCivil}
        opciones={catalogos.estadosCiviles.map((c) => ({ valor: c, etiqueta: c }))}
        onChange={(e) => onCambio({ estadoCivil: e.target.value })}
      />
      <Campo
        id={id('correo')}
        etiqueta="Correo electrónico (opcional)"
        type="email"
        value={valor.correo}
        readOnly={soloLectura}
        error={errores.correo}
        onChange={(e) => onCambio({ correo: e.target.value })}
      />
    </div>
  )
}

// Dirección y referencias de la dirección.
export function CamposDireccion({ prefijoId, valor, onCambio, errores, soloLectura = false }) {
  return (
    <div className="rejilla">
      <Campo
        id={`${prefijoId}-direccion`}
        etiqueta="Dirección"
        value={valor.direccion}
        readOnly={soloLectura}
        error={errores.direccion}
        onChange={(e) => onCambio({ direccion: e.target.value })}
      />
      <Campo
        id={`${prefijoId}-referenciaDireccion`}
        etiqueta="Referencias de la dirección (opcional)"
        placeholder="Punto de referencia, indicaciones para llegar"
        value={valor.referenciaDireccion}
        readOnly={soloLectura}
        error={errores.referenciaDireccion}
        onChange={(e) => onCambio({ referenciaDireccion: e.target.value })}
      />
    </div>
  )
}
