import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from '../components/Sidebar'
import Encabezado from '../components/Encabezado'

// Estructura de todas las pantallas internas: sidebar + encabezado + contenido de la ruta.
export default function LayoutPrincipal() {
  const [menuAbierto, setMenuAbierto] = useState(false)

  return (
    <div className="app">
      <Sidebar abierto={menuAbierto} onCerrar={() => setMenuAbierto(false)} />
      <div className="contenedor">
        <Encabezado onMenu={() => setMenuAbierto(true)} />
        <main className="pagina">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
