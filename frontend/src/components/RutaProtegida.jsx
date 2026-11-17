import { Navigate, Outlet, useLocation } from 'react-router-dom'
import useAutenticacion from '../hooks/useAutenticacion'
import Cargando from './Cargando'

// Protege las rutas internas: sin sesión se redirige al login y se recuerda a dónde quería ir.
export default function RutaProtegida() {
  const { usuario, cargando } = useAutenticacion()
  const ubicacion = useLocation()

  if (cargando) return <Cargando pantalla texto="Verificando sesión…" />
  if (!usuario) return <Navigate to="/login" replace state={{ desde: ubicacion }} />
  return <Outlet />
}
