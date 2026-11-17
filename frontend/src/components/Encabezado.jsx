import { useLocation } from 'react-router-dom'
import { LogOut, Menu } from 'lucide-react'
import useAutenticacion from '../hooks/useAutenticacion'
import { tituloDePagina } from '../utils/navegacion'
import Boton from './Boton'

export default function Encabezado({ onMenu }) {
  const { pathname } = useLocation()
  const { usuario, cerrarSesion } = useAutenticacion()

  return (
    <header className="encabezado">
      <div className="encabezado__izq">
        <button type="button" className="boton boton--fantasma encabezado__menu" onClick={onMenu} aria-label="Abrir menú">
          <Menu size={20} />
        </button>
        <h1>{tituloDePagina(pathname)}</h1>
      </div>

      <div className="encabezado__usuario">
        <div className="usuario__datos">
          <strong>{usuario.nombre}</strong>
          <span>{usuario.rol}</span>
        </div>
        <div className="avatar" aria-hidden="true">{usuario.nombre.charAt(0).toUpperCase()}</div>
        <Boton variante="secundario" tamano="pequeno" icono={LogOut} onClick={cerrarSesion}>
          Salir
        </Boton>
      </div>
    </header>
  )
}
