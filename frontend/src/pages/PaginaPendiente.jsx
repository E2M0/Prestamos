import { Construction } from 'lucide-react'
import EstadoVacio from '../components/EstadoVacio'

// Marcador honesto de un módulo que todavía no se desarrolla (no simula funcionalidad).
export default function PaginaPendiente({ modulo, etapa }) {
  return (
    <div className="tarjeta">
      <EstadoVacio
        icono={Construction}
        titulo={`${modulo}: módulo pendiente`}
        texto={`Este módulo se desarrolla en la Etapa ${etapa} del plan.`}
      />
    </div>
  )
}
