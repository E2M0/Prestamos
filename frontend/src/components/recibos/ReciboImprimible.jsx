import { formatoFecha, formatoFechaHora, formatoMoneda } from '../../utils/formato'
import { NOMBRE_TIPO } from '../../utils/validacionesRecibo'

// Recibo listo para imprimir. formato: 'A4' | 'POS80'. Solo muestra datos que ya calculó el servidor.
export default function ReciboImprimible({ recibo, formato }) {
  const componentes = [
    ['Mora', recibo.totalMora],
    ['Interés', recibo.totalInteres],
    ['Seguro', recibo.totalSeguro],
    ['Gasto de cierre', recibo.totalGasto],
    ['Capital', recibo.totalCapital],
  ].filter(([, v]) => v > 0)

  return (
    <article className={`recibo-papel recibo-papel--${formato === 'POS80' ? 'pos' : 'a4'}`}>
      <header className="recibo-papel__cabecera">
        <h1>{recibo.empresa}</h1>
        <p>Recibo de ingreso</p>
        <strong>{recibo.numero}</strong>
        {recibo.estado === 'Anulado' && <span className="recibo-papel__anulado">ANULADO</span>}
      </header>

      <dl className="recibo-papel__datos">
        <div><dt>Fecha</dt><dd>{formatoFechaHora(recibo.fecha)}</dd></div>
        <div><dt>Cliente</dt><dd>{recibo.cliente}</dd></div>
        <div><dt>Documento</dt><dd>{recibo.numeroDocumento}</dd></div>
        <div><dt>Préstamo</dt><dd>{recibo.prestamoNumero} · {recibo.tipoPrestamo}</dd></div>
        <div>
          <dt>Tipo de pago</dt>
          <dd>{NOMBRE_TIPO[recibo.tipoPago]}{recibo.modalidad ? ` (${recibo.modalidad.toLowerCase()})` : ''}</dd>
        </div>
      </dl>

      <table className="recibo-papel__tabla">
        <thead>
          <tr><th>Concepto</th><th>Importe</th></tr>
        </thead>
        <tbody>
          {recibo.detalle.map((d, i) => {
            const total = d.mora + d.interes + d.seguro + d.gasto + d.capital
            return (
              <tr key={i}>
                <td>
                  {d.cuotaNumero === null ? 'Abono a capital' : `Cuota ${d.cuotaNumero}`}
                  {d.fechaPago && <small> (vence {formatoFecha(d.fechaPago)})</small>}
                </td>
                <td>{formatoMoneda(total)}</td>
              </tr>
            )
          })}
        </tbody>
      </table>

      <dl className="recibo-papel__totales">
        {componentes.map(([etiqueta, valor]) => (
          <div key={etiqueta}><dt>{etiqueta}</dt><dd>{formatoMoneda(valor)}</dd></div>
        ))}
        <div className="recibo-papel__total"><dt>Total recibido</dt><dd>{formatoMoneda(recibo.monto)}</dd></div>
        <div><dt>Balance (capital) posterior</dt><dd>{formatoMoneda(recibo.balancePosterior)}</dd></div>
      </dl>

      {recibo.observacion && <p className="recibo-papel__obs">Observación: {recibo.observacion}</p>}

      <footer className="recibo-papel__pie">
        <p>Atendido por: {recibo.cajero}</p>
        <p>Gracias por su pago.</p>
      </footer>
    </article>
  )
}
