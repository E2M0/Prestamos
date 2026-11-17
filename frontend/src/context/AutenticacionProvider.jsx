import { useCallback, useEffect, useMemo, useState } from 'react'
import { ContextoAutenticacion } from './contextoAutenticacion'
import * as authService from '../services/authService'

// Mantiene al usuario conectado. La verdad vive en el servidor (cookie de sesión):
// al abrir la app se consulta /auth/yo para saber si la sesión sigue activa.
export default function AutenticacionProvider({ children }) {
  const [usuario, setUsuario] = useState(null)
  const [cargando, setCargando] = useState(true)

  useEffect(() => {
    authService
      .obtenerSesion()
      .then(setUsuario)
      .catch(() => setUsuario(null))
      .finally(() => setCargando(false))
  }, [])

  // Si cualquier petición recibe 401, se limpia el usuario y RutaProtegida redirige al login.
  useEffect(() => {
    const alExpirar = () => setUsuario(null)
    window.addEventListener('sesion-expirada', alExpirar)
    return () => window.removeEventListener('sesion-expirada', alExpirar)
  }, [])

  const iniciarSesion = useCallback(async (nombreUsuario, clave) => {
    const datos = await authService.iniciarSesion(nombreUsuario, clave)
    setUsuario(datos)
  }, [])

  const cerrarSesion = useCallback(async () => {
    try {
      await authService.cerrarSesion()
    } finally {
      setUsuario(null)
    }
  }, [])

  const valor = useMemo(
    () => ({ usuario, cargando, iniciarSesion, cerrarSesion }),
    [usuario, cargando, iniciarSesion, cerrarSesion],
  )

  return <ContextoAutenticacion.Provider value={valor}>{children}</ContextoAutenticacion.Provider>
}
