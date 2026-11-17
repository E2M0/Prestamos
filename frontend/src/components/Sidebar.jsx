import { NavLink } from 'react-router-dom'
import { Landmark } from 'lucide-react'
import { NAVEGACION } from '../utils/navegacion'

export default function Sidebar({ abierto, onCerrar }) {
  return (
    <>
      <aside className={`sidebar${abierto ? ' sidebar--abierto' : ''}`}>
        <div className="sidebar__marca">
          <Landmark size={24} />
          <div>
            <strong>Préstamos</strong>
            <span>Sistema administrativo</span>
          </div>
        </div>

        <nav className="sidebar__nav" aria-label="Navegación principal">
          {NAVEGACION.map(({ ruta, titulo, icono: Icono }) => (
            <NavLink
              key={ruta}
              to={ruta}
              end={ruta === '/'}
              onClick={onCerrar}
              className={({ isActive }) => `sidebar__enlace${isActive ? ' sidebar__enlace--activo' : ''}`}
            >
              <Icono size={18} />
              {titulo}
            </NavLink>
          ))}
        </nav>

        <p className="sidebar__pie">Sistema de Gestión de Préstamos</p>
      </aside>
      {abierto && <div className="sidebar__fondo" onClick={onCerrar} aria-hidden="true" />}
    </>
  )
}
