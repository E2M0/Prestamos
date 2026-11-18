import { Navigate, Route, Routes } from 'react-router-dom'
import RutaProtegida from './components/RutaProtegida'
import LayoutPrincipal from './layouts/LayoutPrincipal'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Clientes from './pages/Clientes'
import Prestamos from './pages/Prestamos'
import TiposPrestamo from './pages/TiposPrestamo'
import PaginaPendiente from './pages/PaginaPendiente'
import NoEncontrada from './pages/NoEncontrada'
import { NAVEGACION } from './utils/navegacion'

// Las rutas de los módulos aún no desarrollados muestran una pantalla honesta de
// "pendiente" (con su etapa). Se reemplazan una a una conforme se construye cada módulo.
export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      <Route element={<RutaProtegida />}>
        <Route element={<LayoutPrincipal />}>
          <Route index element={<Dashboard />} />
          <Route path="/clientes" element={<Clientes />} />
          <Route path="/tipos-prestamo" element={<TiposPrestamo />} />
          <Route path="/prestamos" element={<Prestamos />} />
          {NAVEGACION.filter((n) => n.etapa).map((n) => (
            <Route key={n.ruta} path={n.ruta} element={<PaginaPendiente modulo={n.titulo} etapa={n.etapa} />} />
          ))}
          <Route path="*" element={<NoEncontrada />} />
        </Route>
      </Route>

      <Route path="/index.html" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
