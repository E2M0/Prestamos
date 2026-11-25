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

  const esPos = formato === 'POS80'
  const saldo = recibo.balancePosterior

  return (
    <article className={`recibo-papel recibo-papel--${esPos ? 'pos' : 'a4'}`}>
      <header className="recibo-papel__cabecera">
        <div className="recibo-papel__marca">
          <img
            className="recibo-papel__logo"
            src={`${import.meta.env.BASE_URL}logo.png`}
            alt=""
            onError={(e) => { e.currentTarget.style.display = 'none' }}
          />
          <div>
            <h1>{recibo.empresa}</h1>
            {recibo.empresaLema && <p className="recibo-papel__lema">{recibo.empresaLema}</p>}
          </div>
        </div>
        <div className="recibo-papel__meta">
          <h2>Recibo de pago</h2>
          <strong className="recibo-papel__numero">N.º {recibo.numero}</strong>
          <p className="recibo-papel__fecha"><b>Fecha:</b> {formatoFechaHora(recibo.fecha)}</p>
          {recibo.estado === 'Anulado' && <span className="recibo-papel__anulado">ANULADO</span>}
        </div>
      </header>

      <div className="recibo-papel__bloques">
        <section>
          <h3>Información del cliente</h3>
          <dl className="recibo-papel__datos">
            <div><dt>Nombre</dt><dd>{recibo.cliente}</dd></div>
            <div><dt>Documento</dt><dd>{recibo.numeroDocumento}</dd></div>
          </dl>
        </section>
        <section>
          <h3>Detalle del préstamo</h3>
          <dl className="recibo-papel__datos">
            <div><dt>Préstamo</dt><dd>{recibo.prestamoNumero} · {recibo.tipoPrestamo}</dd></div>
            <div>
              <dt>Tipo de pago</dt>
              <dd>{NOMBRE_TIPO[recibo.tipoPago]}{recibo.modalidad ? ` (${recibo.modalidad.toLowerCase()})` : ''}</dd>
            </div>
            <div><dt>Balance restante</dt><dd>{formatoMoneda(saldo)}</dd></div>
          </dl>
        </section>
      </div>

      <table className="recibo-papel__tabla">
        <thead>
          <tr><th>Descripción</th><th>Total</th></tr>
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

      <div className="recibo-papel__resumen">
        <div className="recibo-papel__obs">
          <strong>Observaciones:</strong>
          <span>{recibo.observacion || '—'}</span>
        </div>
        <dl className="recibo-papel__totales">
          {componentes.map(([etiqueta, valor]) => (
            <div key={etiqueta}><dt>{etiqueta}</dt><dd>{formatoMoneda(valor)}</dd></div>
          ))}
          <div className="recibo-papel__total"><dt>Total pagado</dt><dd>{formatoMoneda(recibo.monto)}</dd></div>
        </dl>
      </div>

      <footer className="recibo-papel__pie">
        <p className="recibo-papel__gracias">¡Gracias por su puntualidad y confianza!</p>
        <p>Atendido por: {recibo.cajero}</p>
        <p>Comprobante de pago — {recibo.empresa}</p>
      </footer>
    </article>
  )
}
