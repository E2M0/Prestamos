import Prestamos from './Prestamos'

// La renovación es el módulo de apertura con la sección «Préstamos a renovar» (enunciado, sección 7).
export default function Renovaciones() {
  return <Prestamos modo="renovacion" />
}
