import { AlertCircle, CheckCircle2, Info } from 'lucide-react'
import '../styles/formularios.css'

// tipo: error | exito | info      accion: botón opcional (por ejemplo "Reintentar")
const ICONOS = { error: AlertCircle, exito: CheckCircle2, info: Info }

export default function Alerta({ tipo = 'error', children, accion }) {
  const Icono = ICONOS[tipo] ?? AlertCircle
  return (
    <div className={`alerta alerta--${tipo}`} role={tipo === 'error' ? 'alert' : 'status'}>
      <Icono size={18} />
      <div className="alerta__cuerpo">{children}</div>
      {accion}
    </div>
  )
}
