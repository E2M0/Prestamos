import { Loader2 } from 'lucide-react'

export default function Cargando({ texto = 'Cargando…', pantalla = false }) {
  return (
    <div className={`cargando${pantalla ? ' cargando--pantalla' : ''}`} role="status">
      <Loader2 className="girar" size={20} />
      {texto}
    </div>
  )
}
