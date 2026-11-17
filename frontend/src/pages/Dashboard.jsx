import { Banknote, Clock, HandCoins, Percent, RotateCw, Users, Wallet } from 'lucide-react'
import useApi from '../hooks/useApi'
import { obtenerResumen } from '../services/dashboardService'
import { formatoMoneda } from '../utils/formato'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import Cargando from '../components/Cargando'
import TarjetaEstadistica from '../components/TarjetaEstadistica'
import ActividadReciente from '../components/dashboard/ActividadReciente'
import { GraficoEstados, GraficoIngresos, GraficoTipos } from '../components/dashboard/Graficos'
import '../styles/dashboard.css'

// Todas las cifras vienen de /api/dashboard (consultas reales a SQL Server).
export default function Dashboard() {
  const { datos, cargando, error, recargar } = useApi(obtenerResumen)

  if (cargando && !datos) return <Cargando texto="Cargando indicadores…" />

  if (error) {
    return (
      <Alerta
        tipo="error"
        accion={
          <Boton variante="secundario" tamano="pequeno" icono={RotateCw} onClick={recargar}>
            Reintentar
          </Boton>
        }
      >
        {error}
      </Alerta>
    )
  }

  const i = datos.indicadores

  return (
    <div className="dashboard">
      <section className="dashboard__estadisticas" aria-label="Indicadores">
        <TarjetaEstadistica icono={Users} titulo="Clientes activos" valor={i.totalClientes} />
        <TarjetaEstadistica icono={HandCoins} titulo="Préstamos activos" valor={i.prestamosActivos} />
        <TarjetaEstadistica
          icono={Wallet}
          titulo="Capital colocado"
          valor={formatoMoneda(i.capitalColocado)}
          detalle={`Pendiente de cobro: ${formatoMoneda(i.capitalPendiente)}`}
        />
        <TarjetaEstadistica
          icono={Banknote}
          titulo="Pagos recibidos"
          valor={formatoMoneda(i.pagosMes)}
          detalle={`Mes de ${i.mesActual}`}
        />
        <TarjetaEstadistica
          icono={Clock}
          titulo="Cuotas vencidas"
          valor={i.cuotasVencidas}
          tono={i.cuotasVencidas > 0 ? 'rojo' : 'neutro'}
        />
        <TarjetaEstadistica
          icono={Percent}
          titulo="Mora pendiente"
          valor={formatoMoneda(i.moraPendiente)}
          tono={i.moraPendiente > 0 ? 'rojo' : 'neutro'}
        />
      </section>

      <section className="dashboard__graficos" aria-label="Gráficos">
        <article className="tarjeta">
          <h2 className="tarjeta__titulo">Préstamos por estado</h2>
          <GraficoEstados datos={datos.prestamosPorEstado} />
        </article>
        <article className="tarjeta">
          <h2 className="tarjeta__titulo">Ingresos por mes</h2>
          <GraficoIngresos datos={datos.ingresosPorMes} />
        </article>
        <article className="tarjeta">
          <h2 className="tarjeta__titulo">Tipos de préstamo</h2>
          <GraficoTipos datos={datos.tiposPrestamo} />
        </article>
      </section>

      <article className="tarjeta">
        <h2 className="tarjeta__titulo">Actividad reciente</h2>
        <ActividadReciente items={datos.actividadReciente} />
      </article>
    </div>
  )
}
