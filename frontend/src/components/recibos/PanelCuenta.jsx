import { UserRound } from 'lucide-react'
import { formatoFecha, formatoFechaHora, formatoMoneda } from '../../utils/formato'
import { NOMBRE_TIPO } from '../../utils/validacionesRecibo'
import Etiqueta from '../Etiqueta'
import SeccionForm from '../SeccionForm'
import Tabla from '../Tabla'
import EstadoVacio from '../EstadoVacio'

const COLUMNAS_CUOTAS = [
  { clave: 'numero', titulo: '#' },
  { clave: 'fechaPago', titulo: 'Vence', render: (c) => formatoFecha(c.fechaPago) },
  { clave: 'valor', titulo: 'Valor cuota', render: (c) => formatoMoneda(c.valor) },
  { clave: 'mora', titulo: 'Mora', render: (c) => formatoMoneda(c.mora) },
  { clave: 'pendiente', titulo: 'Pendiente', render: (c) => formatoMoneda(c.pendiente) },
  { clave: 'estado', titulo: 'Estado', render: (c) => <Etiqueta estado={c.estado} /> },
]

// Resumen del préstamo, cuotas y recibos anteriores.
export default function PanelCuenta({ cuenta, onAbrirRecibo }) {
  const { prestamo: p, resumen: r, cuotas, historial } = cuenta

  const columnasHistorial = [
    { clave: 'numero', titulo: 'Recibo' },
    { clave: 'fecha', titulo: 'Fecha', render: (h) => formatoFechaHora(h.fecha) },
    { clave: 'tipoPago', titulo: 'Tipo', render: (h) => NOMBRE_TIPO[h.tipoPago] },
    { clave: 'monto', titulo: 'Monto', render: (h) => formatoMoneda(h.monto) },
    { clave: 'balancePosterior', titulo: 'Balance posterior', render: (h) => formatoMoneda(h.balancePosterior) },
    { clave: 'estado', titulo: 'Estado', render: (h) => <Etiqueta estado={h.estado} /> },
  ]

  return (
    <>
      <SeccionForm titulo="1. Préstamo" descripcion="Datos actuales; la mora se calcula al día de hoy.">
        <div className="resumen-cliente">
          <div className="resumen-cliente__nombre">
            <UserRound size={18} />
            <strong>{p.cliente}</strong>
            <Etiqueta estado={p.estado} />
            <span>{p.numero} · {p.tipoPrestamo}</span>
          </div>
          <dl className="resumen-cliente__datos">
            <div><dt>Documento</dt><dd>{p.numeroDocumento}</dd></div>
            <div><dt>Amortización / plazo</dt><dd>{p.amortizacion} · {p.plazo} cuotas ({p.plazoNombre})</dd></div>
            <div><dt>Monto original</dt><dd>{formatoMoneda(p.monto)}</dd></div>
            <div><dt>Capital pendiente</dt><dd className="resumen-cliente__disponible">{formatoMoneda(p.capitalPendiente)}</dd></div>
            <div><dt>Mora pendiente</dt><dd className={r.moraPendiente > 0 ? 'texto-rojo' : ''}>{formatoMoneda(r.moraPendiente)}</dd></div>
            <div><dt>Cuotas vencidas</dt><dd className={r.cuotasVencidas > 0 ? 'texto-rojo' : ''}>{r.cuotasVencidas} ({formatoMoneda(r.totalVencido)})</dd></div>
          </dl>
        </div>
      </SeccionForm>

      <SeccionForm titulo="2. Cuotas" descripcion={p.codigo === 'FIJO' ? 'Saldo fijo: cada cuota nace al saldarse la anterior.' : undefined}>
        {cuotas.length ? (
          <div className="tabla__contenedor--corta">
            <Tabla columnas={COLUMNAS_CUOTAS} filas={cuotas} claveFila="numero" />
          </div>
        ) : (
          <EstadoVacio compacto titulo="Sin cuotas" />
        )}
      </SeccionForm>

      <SeccionForm titulo="Recibos anteriores" descripcion="Doble clic sobre un recibo para verlo o reimprimirlo.">
        {historial.length ? (
          <Tabla columnas={columnasHistorial} filas={historial} onDobleClic={(h) => onAbrirRecibo(h.id)} />
        ) : (
          <EstadoVacio compacto titulo="Aún no hay recibos" texto="Los pagos que registre aparecerán aquí." />
        )}
      </SeccionForm>
    </>
  )
}
