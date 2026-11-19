import { formatoMoneda } from '../../utils/formato'
import '../../styles/renovaciones.css'

// Resultado de la renovación calculado por el servidor: saldos cancelados, descuento y desembolso final.
export default function ResumenRenovacion({ renovacion }) {
  return (
    <div>
      <div className="tabla__contenedor">
        <table className="tabla">
          <thead>
            <tr><th>Préstamo</th><th>Base de saldo</th><th>Saldo a cancelar</th><th>Descuento (condonado)</th></tr>
          </thead>
          <tbody>
            {renovacion.prestamos.map((p) => (
              <tr key={p.prestamoId}>
                <td>{p.numero}</td>
                <td>{p.base}</td>
                <td className="tabla__numero">{formatoMoneda(p.montoCancelado)}</td>
                <td className="tabla__numero">{formatoMoneda(p.descuento)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="resumen-calculo" style={{ marginTop: 12 }}>
        <div className="resumen-calculo__item"><span>Total saldos cancelados</span><strong>{formatoMoneda(renovacion.totalSaldoCancelado)}</strong></div>
        <div className="resumen-calculo__item"><span>Total descuento</span><strong>{formatoMoneda(renovacion.totalDescuento)}</strong></div>
        <div className="resumen-calculo__item"><span>Desembolso del préstamo nuevo</span><strong>{formatoMoneda(renovacion.desembolsoNuevo)}</strong></div>
        <div className="resumen-calculo__item resumen-calculo__item--destacado"><span>Desembolso final (a entregar)</span><strong>{formatoMoneda(renovacion.desembolsoFinal)}</strong></div>
      </div>
    </div>
  )
}
