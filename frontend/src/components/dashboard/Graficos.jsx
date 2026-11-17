import {
  Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts'
import EstadoVacio from '../EstadoVacio'
import { formatoCompacto, formatoMoneda } from '../../utils/formato'

// Colores en JS (las gráficas dibujan SVG): verdes para lo "sano", grises para lo neutro.
const COLORES_ESTADO = { Activo: '#059669', Saldado: '#9ca3af', Renovado: '#6ee7b7' }
const COLORES_TIPO = ['#059669', '#6ee7b7', '#9ca3af', '#d1d5db', '#047857']
const EJE = { tickLine: false, axisLine: false, fontSize: 12, stroke: '#6b7280' }

export function GraficoEstados({ datos }) {
  if (!datos.length) return <EstadoVacio compacto titulo="Aún no hay préstamos registrados" />
  return (
    <div className="grafico">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={datos} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
          <CartesianGrid stroke="#e5e7eb" vertical={false} />
          <XAxis dataKey="estado" {...EJE} />
          <YAxis allowDecimals={false} {...EJE} />
          <Tooltip cursor={{ fill: '#f3f4f6' }} formatter={(valor) => [valor, 'Préstamos']} />
          <Bar dataKey="cantidad" radius={[6, 6, 0, 0]} maxBarSize={56}>
            {datos.map((d) => (
              <Cell key={d.estado} fill={COLORES_ESTADO[d.estado] || '#9ca3af'} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}

export function GraficoIngresos({ datos }) {
  if (!datos.some((d) => d.total > 0)) {
    return <EstadoVacio compacto titulo="Sin pagos en los últimos 6 meses" />
  }
  return (
    <div className="grafico">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={datos} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <CartesianGrid stroke="#e5e7eb" vertical={false} />
          <XAxis dataKey="etiqueta" {...EJE} />
          <YAxis width={48} tickFormatter={formatoCompacto} {...EJE} />
          <Tooltip
            cursor={{ fill: '#f3f4f6' }}
            labelFormatter={(_, carga) => carga?.[0]?.payload?.periodoTexto ?? ''}
            formatter={(valor) => [formatoMoneda(valor), 'Ingresos']}
          />
          <Bar dataKey="total" fill="#059669" radius={[6, 6, 0, 0]} maxBarSize={44} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}

export function GraficoTipos({ datos }) {
  if (!datos.length) return <EstadoVacio compacto titulo="Aún no hay préstamos registrados" />
  return (
    <>
      <div className="grafico grafico--corto">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie data={datos} dataKey="cantidad" nameKey="nombre" innerRadius={52} outerRadius={80} paddingAngle={2}>
              {datos.map((d, i) => (
                <Cell key={d.nombre} fill={COLORES_TIPO[i % COLORES_TIPO.length]} />
              ))}
            </Pie>
            <Tooltip formatter={(valor, nombre) => [valor, nombre]} />
          </PieChart>
        </ResponsiveContainer>
      </div>
      <ul className="leyenda">
        {datos.map((d, i) => (
          <li key={d.nombre}>
            <span className="leyenda__punto" style={{ background: COLORES_TIPO[i % COLORES_TIPO.length] }} />
            {d.nombre} ({d.cantidad})
          </li>
        ))}
      </ul>
    </>
  )
}
