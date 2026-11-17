import { useContext } from 'react'
import { ContextoAutenticacion } from '../context/contextoAutenticacion'

export default function useAutenticacion() {
  const contexto = useContext(ContextoAutenticacion)
  if (!contexto) {
    throw new Error('useAutenticacion debe usarse dentro de AutenticacionProvider')
  }
  return contexto
}
