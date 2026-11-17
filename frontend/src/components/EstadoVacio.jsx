import { Inbox } from 'lucide-react'

// Pantalla (o bloque) sin datos. `accion` es un botón opcional.
export default function EstadoVacio({ icono: Icono = Inbox, titulo, texto, accion, compacto = false }) {
  return (
    <div className={`vacio${compacto ? ' vacio--compacto' : ''}`}>
      <Icono size={compacto ? 28 : 40} />
      <p className="vacio__titulo">{titulo}</p>
      {texto && <p className="vacio__texto">{texto}</p>}
      {accion}
    </div>
  )
}
