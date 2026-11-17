import { useCallback, useEffect, useState } from 'react'

// Carga datos al montar y expone { datos, cargando, error, recargar }.
// `funcion` debe ser estable (una función importada de un service, no una flecha creada en el render).
export default function useApi(funcion) {
  const [estado, setEstado] = useState({ datos: null, cargando: true, error: '' })

  const cargar = useCallback(async () => {
    setEstado((anterior) => ({ ...anterior, cargando: true, error: '' }))
    try {
      const datos = await funcion()
      setEstado({ datos, cargando: false, error: '' })
    } catch (e) {
      setEstado({ datos: null, cargando: false, error: e.message })
    }
  }, [funcion])

  useEffect(() => {
    cargar()
  }, [cargar])

  return { ...estado, recargar: cargar }
}
