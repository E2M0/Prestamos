import { AlertCircle, CheckCircle2 } from 'lucide-react'

// tipo: error | exito      accion: botón opcional (por ejemplo "Reintentar")
export default function Alerta({ tipo = 'error', children, accion }) {
  const Icono = tipo === 'exito' ? CheckCircle2 : AlertCircle
  return (
    <div className={`alerta alerta--${tipo}`} role={tipo === 'error' ? 'alert' : 'status'}>
      <Icono size={18} />
      <div className="alerta__cuerpo">{children}</div>
      {accion}
    </div>
  )
}
