import Campo from '../Campo'
import { aplicarMascaraTelefono } from '../../utils/documento'

// Datos laborales: empresa, cargo, tiempo, sueldo, teléfono y dirección del trabajo.
export default function CamposLaborales({ prefijoId, valor, onCambio, errores, soloLectura = false }) {
  const e = (campo) => errores[`laboral.${campo}`]
  return (
    <div className="rejilla">
      <Campo
        id={`${prefijoId}-empresa`}
        etiqueta="Empresa"
        value={valor.empresa}
        readOnly={soloLectura}
        error={e('empresa')}
        onChange={(ev) => onCambio({ empresa: ev.target.value })}
      />
      <Campo
        id={`${prefijoId}-cargoOcupacion`}
        etiqueta="Ocupación o cargo"
        value={valor.cargoOcupacion}
        readOnly={soloLectura}
        error={e('cargoOcupacion')}
        onChange={(ev) => onCambio({ cargoOcupacion: ev.target.value })}
      />
      <Campo
        id={`${prefijoId}-tiempoLaborando`}
        etiqueta="Tiempo laborando"
        placeholder="Ej.: 3 años"
        value={valor.tiempoLaborando}
        readOnly={soloLectura}
        error={e('tiempoLaborando')}
        onChange={(ev) => onCambio({ tiempoLaborando: ev.target.value })}
      />
      <Campo
        id={`${prefijoId}-sueldo`}
        etiqueta="Sueldo que devenga (RD$)"
        type="number"
        min="0"
        step="0.01"
        value={valor.sueldo}
        readOnly={soloLectura}
        error={e('sueldo')}
        onChange={(ev) => onCambio({ sueldo: ev.target.value })}
      />
      <Campo
        id={`${prefijoId}-telefonoEmpresa`}
        etiqueta="Teléfono del trabajo"
        placeholder="809-000-0000"
        value={valor.telefonoEmpresa}
        readOnly={soloLectura}
        error={e('telefonoEmpresa')}
        onChange={(ev) => onCambio({ telefonoEmpresa: aplicarMascaraTelefono(ev.target.value) })}
      />
      <Campo
        id={`${prefijoId}-direccionEmpresa`}
        etiqueta="Dirección del trabajo"
        value={valor.direccionEmpresa}
        readOnly={soloLectura}
        error={e('direccionEmpresa')}
        onChange={(ev) => onCambio({ direccionEmpresa: ev.target.value })}
      />
    </div>
  )
}
