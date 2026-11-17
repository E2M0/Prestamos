import { Loader2 } from 'lucide-react'

// variante: primario | secundario | fantasma | peligro      tamano: (normal) | pequeno
export default function Boton({
  icono: Icono,
  children,
  variante = 'primario',
  tamano,
  cargando = false,
  tipo = 'button',
  disabled,
  ...resto
}) {
  const clase = `boton boton--${variante}${tamano ? ` boton--${tamano}` : ''}`
  return (
    <button type={tipo} className={clase} disabled={cargando || disabled} {...resto}>
      {cargando ? <Loader2 className="girar" size={16} /> : Icono && <Icono size={16} />}
      {children}
    </button>
  )
}
