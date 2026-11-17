import api from './api'

// Verifica la API y la conexión a SQL Server.
export async function obtenerSalud() {
  const { data } = await api.get('/salud')
  return data.datos
}
