import api from './api'

// Catálogos del formulario de clientes (tipos de documento con su patrón, nacionalidades, etc.).
export async function obtenerCatalogosClientes() {
  const { data } = await api.get('/catalogos/clientes')
  return data.datos
}
