import { useEffect, useState } from 'react'
import { obtenerEmpresa } from '../services/empresaService'

const PORDEFECTO = { nombre: 'Gestión de Préstamos', lema: '' }
let guardada = null // se consulta una sola vez por carga de la página

// Datos de la empresa para el login y el menú. Si la API no responde, se usan valores por defecto.
export default function useEmpresa() {
  const [empresa, setEmpresa] = useState(guardada ?? PORDEFECTO)

  useEffect(() => {
    if (guardada) return undefined
    let activo = true
    obtenerEmpresa()
      .then((datos) => {
        guardada = datos
        if (activo) setEmpresa(datos)
      })
      .catch(() => {})
    return () => { activo = false }
  }, [])

  return empresa
}
