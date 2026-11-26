import Campo from '../Campo'
import { aplicarMascaraTelefono } from '../../utils/documento'

// Un familiar (cercano o que no convive): nombre, parentesco, teléfono y dirección. Todo opcional en conjunto.
export function BloqueFamiliar({ clave, titulo, valor, onCambio, errores }) {
  const e = (campo) => errores[`familiares.${clave}.${campo}`]
  return (
    <div>
      <p className="subtitulo" style={{ marginBottom: 12 }}>{titulo}</p>
      <div className="rejilla">
        <Campo id={`fam-${clave}-nombre`} etiqueta="Nombre" value={valor.nombre} error={e('nombre')}
          onChange={(ev) => onCambio({ nombre: ev.target.value })} />
        <Campo id={`fam-${clave}-parentesco`} etiqueta="Parentesco" value={valor.parentesco} error={e('parentesco')}
          onChange={(ev) => onCambio({ parentesco: ev.target.value })} />
        <Campo id={`fam-${clave}-telefono`} etiqueta="Teléfono" placeholder="809-000-0000" value={valor.telefono}
          error={e('telefono')} onChange={(ev) => onCambio({ telefono: aplicarMascaraTelefono(ev.target.value) })} />
        <Campo id={`fam-${clave}-direccion`} etiqueta="Dirección" value={valor.direccion} error={e('direccion')}
          onChange={(ev) => onCambio({ direccion: ev.target.value })} />
      </div>
    </div>
  )
}

// Hasta tres referencias personales (opcionales): nombre y teléfono; si llena una, debe completarla.
export function ListaReferencias({ referencias, onCambio, errores }) {
  const cambiar = (i, parcial) => onCambio(referencias.map((r, k) => (k === i ? { ...r, ...parcial } : r)))
  return (
    <>
      {referencias.map((r, i) => (
        <div className="rejilla" key={i}>
          <Campo id={`ref-nombre-${i}`} etiqueta={`Referencia ${i + 1}: nombre`} value={r.nombre}
            error={errores[`referencias.${i}.nombre`]} onChange={(ev) => cambiar(i, { nombre: ev.target.value })} />
          <Campo id={`ref-telefono-${i}`} etiqueta="Teléfono" placeholder="809-000-0000" value={r.telefono}
            error={errores[`referencias.${i}.telefono`]}
            onChange={(ev) => cambiar(i, { telefono: aplicarMascaraTelefono(ev.target.value) })} />
        </div>
      ))}
      {errores.referencias && <p className="campo__error--bloque">{errores.referencias}</p>}
    </>
  )
}
