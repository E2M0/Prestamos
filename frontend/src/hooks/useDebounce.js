import { useEffect, useState } from 'react'

// Devuelve `valor` retrasado `ms` milisegundos (evita una petición por cada tecla en los buscadores).
export default function useDebounce(valor, ms = 300) {
  const [retrasado, setRetrasado] = useState(valor)

  useEffect(() => {
    const temporizador = setTimeout(() => setRetrasado(valor), ms)
    return () => clearTimeout(temporizador)
  }, [valor, ms])

  return retrasado
}
