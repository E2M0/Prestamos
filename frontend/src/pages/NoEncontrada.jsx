import { Link } from 'react-router-dom'
import { SearchX } from 'lucide-react'
import EstadoVacio from '../components/EstadoVacio'

export default function NoEncontrada() {
  return (
    <div className="tarjeta">
      <EstadoVacio
        icono={SearchX}
        titulo="Página no encontrada"
        texto="La dirección no existe o fue movida."
        accion={<Link to="/" className="boton boton--secundario">Volver al dashboard</Link>}
      />
    </div>
  )
}
