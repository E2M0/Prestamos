import { Banknote, HandCoins, LayoutDashboard, RefreshCw, Tags, Users } from 'lucide-react'

// Única fuente de verdad del menú: la usan el sidebar, el encabezado y las rutas.
// `etapa` indica en qué etapa del plan se construye el módulo (si existe, aún está pendiente).
export const NAVEGACION = [
  { ruta: '/', titulo: 'Dashboard', icono: LayoutDashboard },
  { ruta: '/clientes', titulo: 'Clientes y garantes', icono: Users },
  { ruta: '/tipos-prestamo', titulo: 'Tipos de préstamo', icono: Tags },
  { ruta: '/prestamos', titulo: 'Apertura de préstamos', icono: HandCoins },
  { ruta: '/recibos', titulo: 'Recibos de ingreso', icono: Banknote, etapa: 8 },
  { ruta: '/renovaciones', titulo: 'Renovaciones', icono: RefreshCw, etapa: 9 },
]

export function tituloDePagina(pathname) {
  const ruta = pathname.length > 1 ? pathname.replace(/\/$/, '') : pathname
  return NAVEGACION.find((n) => n.ruta === ruta)?.titulo ?? 'Página no encontrada'
}
