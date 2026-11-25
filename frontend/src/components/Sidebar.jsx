import { NavLink } from 'react-router-dom'
import { NAVEGACION, puedeVer } from '../utils/navegacion'
import useAutenticacion from '../hooks/useAutenticacion'
import useEmpresa from '../hooks/useEmpresa'
import LogoEmpresa from './LogoEmpresa'

export default function Sidebar({ abierto, onCerrar }) {
  const { usuario } = useAutenticacion()
  const empresa = useEmpresa()
  return (
    <>
      <aside className={`sidebar${abierto ? ' sidebar--abierto' : ''}`}>
        <div className="sidebar__marca">
          <LogoEmpresa tamano={40} />
          <div>
            <strong>{empresa.nombre}</strong>
            {empresa.lema && <span>{empresa.lema}</span>}
          </div>
        </div>

        <nav className="sidebar__nav" aria-label="Navegación principal">
          {NAVEGACION.filter((n) => puedeVer(n, usuario)).map(({ ruta, titulo, icono: Icono }) => (
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

     
      </aside>
      {abierto && <div className="sidebar__fondo" onClick={onCerrar} aria-hidden="true" />}
    </>
  )
}
