import { Plus, Trash2 } from 'lucide-react'
import Boton from '../Boton'
import Campo from '../Campo'
import CampoSelect from '../CampoSelect'
import { aplicarMascaraTelefono } from '../../utils/documento'

const MAXIMO = 5

// Lista editable de teléfonos: tipo, número y uno marcado como principal.
export default function EditorTelefonos({ prefijoId, telefonos, onCambio, errores, tipos, soloLectura = false }) {
  const cambiar = (i, parcial) => onCambio(telefonos.map((t, k) => (k === i ? { ...t, ...parcial } : t)))
  const marcarPrincipal = (i) => onCambio(telefonos.map((t, k) => ({ ...t, principal: k === i })))
  const agregar = () => onCambio([...telefonos, { tipo: 'Celular', numero: '', principal: false }])
  const quitar = (i) => {
    const resto = telefonos.filter((_, k) => k !== i)
    if (resto.length && !resto.some((t) => t.principal)) resto[0] = { ...resto[0], principal: true }
    onCambio(resto)
  }

  return (
    <>
      {telefonos.map((t, i) => (
        <div className="telefono-fila" key={i}>
          <CampoSelect
            id={`${prefijoId}-telefono-tipo-${i}`}
            etiqueta="Tipo"
            value={t.tipo}
            disabled={soloLectura}
            error={errores[`telefonos.${i}.tipo`]}
            opciones={tipos.map((x) => ({ valor: x, etiqueta: x }))}
            placeholder="Tipo"
            onChange={(e) => cambiar(i, { tipo: e.target.value })}
          />
          <Campo
            id={`${prefijoId}-telefono-numero-${i}`}
            etiqueta="Número"
            placeholder="809-000-0000"
            value={t.numero}
            readOnly={soloLectura}
            error={errores[`telefonos.${i}.numero`]}
            onChange={(e) => cambiar(i, { numero: aplicarMascaraTelefono(e.target.value) })}
          />
          <label className="telefono-fila__principal">
            <input
              type="radio"
              name={`${prefijoId}-telefono-principal`}
              checked={Boolean(t.principal)}
              disabled={soloLectura}
              onChange={() => marcarPrincipal(i)}
            />
            Principal
          </label>
          {!soloLectura && (
            <Boton
              variante="fantasma"
              icono={Trash2}
              aria-label="Quitar teléfono"
              disabled={telefonos.length === 1}
              onClick={() => quitar(i)}
            />
          )}
        </div>
      ))}

      {errores.telefonos && <p className="campo__error--bloque">{errores.telefonos}</p>}

      {!soloLectura && telefonos.length < MAXIMO && (
        <div>
          <Boton variante="secundario" tamano="pequeno" icono={Plus} onClick={agregar}>
            Agregar teléfono
          </Boton>
        </div>
      )}
    </>
  )
}
