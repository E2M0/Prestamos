import { Navigate, Outlet } from 'react-router-dom'
import useAutenticacion from '../hooks/useAutenticacion'

// Deja pasar solo a los roles indicados; a los demás los lleva al dashboard.
// Se usa dentro de RutaProtegida, así que ya hay una sesión iniciada.
export default function RutaPorRol({ roles }) {
  const { usuario } = useAutenticacion()
  if (!roles.includes(usuario?.rol)) return <Navigate to="/" replace />
  return <Outlet />
}
