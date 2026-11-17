// Etiqueta de color para estados. Verde = bien, rojo = problema, gris = neutro.
const TONOS = {
  Activo: 'exito',
  Pagada: 'exito',
  Vencida: 'peligro',
  Anulado: 'peligro',
}

export default function Etiqueta({ estado, tono, children }) {
  const clase = tono || TONOS[estado] || 'neutro'
  return <span className={`etiqueta etiqueta--${clase}`}>{children ?? estado}</span>
}
