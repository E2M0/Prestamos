import { formatoFecha, formatoMoneda } from '../../utils/formato'
import '../../styles/tabla.css'

// Tabla virtual de cuotas: número, fecha, capital, interés, seguro, (gasto de cierre), cuota, balance y cuotas restantes.
// Solo muestra lo que calculó el servidor.
export default function TablaAmortizacion({ cuotas }) {
  const hayGasto = cuotas.some((c) => c.gastoCierre > 0) // la columna aparece solo si el gasto se financió
  return (
    <div className="tabla__contenedor tabla__contenedor--alto">
      <table className="tabla tabla--plana">
        <thead>
          <tr>
            <th>No.</th>
            <th>Fecha de pago</th>
            <th className="tabla__num">Capital</th>
            <th className="tabla__num">Interés</th>
            <th className="tabla__num">Seguro</th>
            {hayGasto && <th className="tabla__num">Gasto de cierre</th>}
            <th className="tabla__num">Cuota</th>
            <th className="tabla__num">Balance</th>
            <th className="tabla__num">Restantes</th>
          </tr>
        </thead>
        <tbody>
          {cuotas.map((c) => (
            <tr key={c.numero}>
              <td>{c.numero}</td>
              <td>{formatoFecha(c.fechaPago)}</td>
              <td className="tabla__num">{formatoMoneda(c.capital)}</td>
              <td className="tabla__num">{formatoMoneda(c.interes)}</td>
              <td className="tabla__num">{formatoMoneda(c.seguro)}</td>
              {hayGasto && <td className="tabla__num">{formatoMoneda(c.gastoCierre)}</td>}
              <td className="tabla__num"><strong>{formatoMoneda(c.valorCuota)}</strong></td>
              <td className="tabla__num">{formatoMoneda(c.balance)}</td>
              <td className="tabla__num">{c.cuotasRestantes}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
