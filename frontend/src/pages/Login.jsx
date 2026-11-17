import { useState } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { Landmark, Lock, UserRound } from 'lucide-react'
import useAutenticacion from '../hooks/useAutenticacion'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import Campo from '../components/Campo'
import '../styles/login.css'

export default function Login() {
  const { usuario, iniciarSesion } = useAutenticacion()
  const ubicacion = useLocation()
  const [nombreUsuario, setNombreUsuario] = useState('')
  const [clave, setClave] = useState('')
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')
  const [enviando, setEnviando] = useState(false)

  // Si ya hay sesión se va al destino original (o al dashboard)
  if (usuario) return <Navigate to={ubicacion.state?.desde?.pathname || '/'} replace />

  async function enviar(evento) {
    evento.preventDefault()
    const nuevos = {}
    if (!nombreUsuario.trim()) nuevos.usuario = 'Ingrese su usuario.'
    if (!clave) nuevos.clave = 'Ingrese su contraseña.'
    setErrores(nuevos)
    setMensaje('')
    if (Object.keys(nuevos).length) return

    setEnviando(true)
    try {
      await iniciarSesion(nombreUsuario.trim(), clave)
    } catch (e) {
      setMensaje(e.message)
      setErrores(e.errores || {})
    } finally {
      setEnviando(false)
    }
  }

  return (
    <main className="login">
      <section className="login__tarjeta">
        <div className="login__marca">
          <div className="login__logo"><Landmark size={26} /></div>
          <h1>Gestión de Préstamos</h1>
          <p>Inicie sesión para continuar</p>
        </div>

        <form className="login__form" onSubmit={enviar} noValidate>
          {mensaje && <Alerta tipo="error">{mensaje}</Alerta>}
          <Campo
            id="usuario"
            etiqueta="Usuario"
            icono={UserRound}
            autoComplete="username"
            autoFocus
            value={nombreUsuario}
            onChange={(e) => setNombreUsuario(e.target.value)}
            error={errores.usuario}
          />
          <Campo
            id="clave"
            etiqueta="Contraseña"
            icono={Lock}
            type="password"
            autoComplete="current-password"
            value={clave}
            onChange={(e) => setClave(e.target.value)}
            error={errores.clave}
          />
          <Boton tipo="submit" cargando={enviando}>
            Entrar
          </Boton>
        </form>
      </section>
    </main>
  )
}
