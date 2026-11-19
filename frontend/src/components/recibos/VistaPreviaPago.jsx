import { formatoFecha, formatoMoneda } from '../../utils/formato'
import Etiqueta from '../Etiqueta'

// Resultado de "Calcular pago": todo viene calculado del servidor; aquí solo se muestra.
export default function VistaPreviaPago({ previa, plazoActual }) {
  const notas = []
  if (previa.saldaPrestamo) notas.push('Con este pago el préstamo queda saldado.')
  if (previa.cuotasNuevas.length) {
    notas.push(`Se generará la cuota ${previa.cuotasNuevas.map((c) => `${c.numero} (${formatoFecha(c.fechaPago)}, ${formatoMoneda(c.valor)})`).join(', ')}.`)
  }
  if (previa.cuotasEliminadas.length) {
    notas.push(`Desaparecen las cuotas futuras: ${previa.cuotasEliminadas.join(', ')}.`)
  }
  if (previa.prestamo.plazo !== plazoActual) notas.push(`El plazo pasa de ${plazoActual} a ${previa.prestamo.plazo} cuotas.`)
  if (previa.tipoPago === 'abono_capital') notas.push('Las cuotas futuras se recalculan con el nuevo capital.')

  return (
    <div className="vista-previa">
      <h3>Así se aplicaría este pago</h3>
      <div className="tabla__contenedor">
        <table className="tabla">
          <thead>
            <tr>
              <th>Cuota</th><th>Mora</th><th>Interés</th><th>Seguro</th><th>Gasto cierre</th><th>Capital</th><th>Queda</th>
            </tr>
          </thead>
          <tbody>
            {previa.detalle.map((d, i) => (
              <tr key={i}>
                <td>{d.cuotaNumero === null ? 'Abono a capital' : `${d.cuotaNumero} · ${formatoFecha(d.fechaPago)}`}</td>
                <td className="tabla__numero">{formatoMoneda(d.mora)}</td>
                <td className="tabla__numero">{formatoMoneda(d.interes)}</td>
                <td className="tabla__numero">{formatoMoneda(d.seguro)}</td>
                <td className="tabla__numero">{formatoMoneda(d.gasto)}</td>
                <td className="tabla__numero">{formatoMoneda(d.capital)}</td>
                <td>{d.estadoResultante ? <Etiqueta estado={d.estadoResultante} /> : '—'}</td>
              </tr>
            ))}
            <tr className="cobro__total">
              <td>Total</td>
              <td className="tabla__numero">{formatoMoneda(previa.totales.mora)}</td>
              <td className="tabla__numero">{formatoMoneda(previa.totales.interes)}</td>
              <td className="tabla__numero">{formatoMoneda(previa.totales.seguro)}</td>
              <td className="tabla__numero">{formatoMoneda(previa.totales.gasto)}</td>
              <td className="tabla__numero">{formatoMoneda(previa.totales.capital)}</td>
              <td className="tabla__numero">{formatoMoneda(previa.monto)}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p style={{ margin: '10px 0 0' }}>
        Balance (capital pendiente) posterior: <strong>{formatoMoneda(previa.balancePosterior)}</strong>
      </p>
      {notas.length > 0 && (
        <ul className="vista-previa__notas">
          {notas.map((n) => <li key={n}>{n}</li>)}
        </ul>
      )}
    </div>
  )
}
