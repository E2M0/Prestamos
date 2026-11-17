import EstadoVacio from '../EstadoVacio'
import Etiqueta from '../Etiqueta'
import { formatoFechaHora, formatoMoneda } from '../../utils/formato'

export default function ActividadReciente({ items }) {
  if (!items.length) return <EstadoVacio compacto titulo="Todavía no hay actividad" />
  return (
    <ul className="actividad">
      {items.map((a) => (
        <li key={`${a.tipo}-${a.referencia}`} className="actividad__item">
          <Etiqueta tono={a.tipo === 'recibo' ? 'exito' : 'neutro'}>
            {a.tipo === 'recibo' ? 'Recibo' : 'Apertura'}
          </Etiqueta>
          <div className="actividad__texto">
            <div className="actividad__titulo">{a.referencia}</div>
            <div className="actividad__sub">
              {a.cliente} · {formatoFechaHora(a.fecha)}
            </div>
          </div>
          <div className="actividad__monto">{formatoMoneda(a.monto)}</div>
        </li>
      ))}
    </ul>
  )
}
