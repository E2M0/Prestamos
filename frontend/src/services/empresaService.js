import api from './api'

// Nombre y lema de la empresa (vienen de config.php; no requiere sesión).
export async function obtenerEmpresa() {
  const { data } = await api.get('/empresa')
  return data.datos
}
