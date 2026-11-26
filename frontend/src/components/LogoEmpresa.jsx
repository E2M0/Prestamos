import { useState } from 'react'
import { Landmark } from 'lucide-react'

// Logo de la empresa. Prueba cada archivo de `archivos` (en public/) en orden; si ninguno existe
// se muestra un ícono de respaldo. Ej.: ['logo-login.png', 'logo.png'] usa la versión grande si la hay.
export default function LogoEmpresa({ tamano = 40, archivos = ['logo.png'] }) {
  const [intento, setIntento] = useState(0)
  if (intento >= archivos.length) return <Landmark size={Math.round(tamano * 0.6)} />
  return (
    <img
      className="logo-empresa"
      src={`${import.meta.env.BASE_URL}${archivos[intento]}`}
      alt=""
      style={{ width: tamano, height: tamano }}
      onError={() => setIntento((i) => i + 1)}
    />
  )
}
