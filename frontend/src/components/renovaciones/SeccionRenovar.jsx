import CampoSelect from '../CampoSelect'
import EstadoVacio from '../EstadoVacio'
import { formatoMoneda } from '../../utils/formato'
import '../../styles/renovaciones.css'

export const BASES_SALDO = [
  { valor: 'Capital pendiente', etiqueta: 'Capital pendiente' },
  { valor: 'Balance a la fecha', etiqueta: 'Balance a la fecha' },
  { valor: 'Balance general', etiqueta: 'Balance general' },
]
export const MAX_RENOVAR = 3

// Selección de los préstamos a renovar y de la base de saldo de cada uno. Los saldos los calcula el servidor.
export default function SeccionRenovar({ renovables, renovar, onCambiar, error }) {
  const elegidos = new Map(renovar.map((r) => [r.prestamoId, r.base]))
  const llenos = renovar.length >= MAX_RENOVAR

  if (!renovables.length) {
    return <EstadoVacio compacto titulo="Sin préstamos activos" texto="El cliente no tiene préstamos activos para renovar." />
  }

  function alternar(id, marcado) {
    onCambiar(marcado ? [...renovar, { prestamoId: id, base: '' }] : renovar.filter((r) => r.prestamoId !== id))
  }
  function cambiarBase(id, base) {
    onCambiar(renovar.map((r) => (r.prestamoId === id ? { ...r, base } : r)))
  }

  return (
    <>
      <p className="renovar-contador">
        Seleccionados: {renovar.length} de {MAX_RENOVAR} como máximo.
      </p>
      <div className="renovar-lista">
        {renovables.map((p) => {
          const marcado = elegidos.has(p.id)
          const base = elegidos.get(p.id)
          return (
            <div key={p.id} className={`renovar-item${marcado ? ' renovar-item--activo' : ''}`}>
              <input
                type="checkbox"
                aria-label={`Renovar ${p.numero}`}
                checked={marcado}
                disabled={!marcado && llenos}
                onChange={(e) => alternar(p.id, e.target.checked)}
              />
              <div className="renovar-item__datos">
                <strong>{p.numero} · {p.tipoPrestamo}</strong>
                <span>{p.amortizacion} · {p.plazo} cuotas · monto {formatoMoneda(p.monto)} · capital pendiente {formatoMoneda(p.capitalPendiente)}</span>
                {marcado && base && (
                  <div className="renovar-item__saldo">
                    Saldo a cancelar: <strong>{formatoMoneda(p.saldos[base])}</strong>
                  </div>
                )}
              </div>
              {marcado && (
                <CampoSelect
                  id={`base-${p.id}`}
                  etiqueta="Base de saldo"
                  placeholder="Elija la base…"
                  opciones={BASES_SALDO}
                  value={base}
                  onChange={(e) => cambiarBase(p.id, e.target.value)}
                />
              )}
            </div>
          )
        })}
      </div>
      {error && <p className="campo__error--bloque" style={{ marginTop: 8 }}>{error}</p>}
    </>
  )
}
