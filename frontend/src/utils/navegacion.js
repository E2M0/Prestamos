import { Banknote, HandCoins, LayoutDashboard, RefreshCw, Tags, Users } from 'lucide-react'

// Única fuente de verdad del menú: la usan el sidebar, el encabezado y las rutas.
// `roles` (opcional) limita el módulo a esos roles; sin `roles` lo ve cualquier usuario con sesión.
// La API también lo exige (403): ocultar el menú es solo comodidad, no seguridad.
export const NAVEGACION = [
  { ruta: '/', titulo: 'Dashboard', icono: LayoutDashboard },
  { ruta: '/clientes', titulo: 'Clientes y garantes', icono: Users },
  { ruta: '/tipos-prestamo', titulo: 'Tipos de préstamo', icono: Tags, roles: ['Administrador'] },
  { ruta: '/prestamos', titulo: 'Apertura de préstamos', icono: HandCoins },
  { ruta: '/recibos', titulo: 'Recibos de ingreso', icono: Banknote },
  { ruta: '/renovaciones', titulo: 'Renovaciones', icono: RefreshCw, roles: ['Administrador'] },
]

export function puedeVer(modulo, usuario) {
  return !modulo.roles || modulo.roles.includes(usuario?.rol)
}

export function tituloDePagina(pathname) {
  const ruta = pathname.length > 1 ? pathname.replace(/\/$/, '') : pathname
  return NAVEGACION.find((n) => n.ruta === ruta)?.titulo ?? 'Página no encontrada'
}
