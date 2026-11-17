import { useEffect, useRef } from 'react'
import { X } from 'lucide-react'
import '../styles/modal.css'

// Ventana modal accesible: se cierra con Esc o al pulsar fuera, bloquea el scroll de la página
// y devuelve el foco al elemento que la abrió. ancho: sm | md | lg
export default function Modal({ abierto, titulo, onCerrar, children, pie, ancho = 'md' }) {
  const caja = useRef(null)
  const cerrarRef = useRef(onCerrar)

  useEffect(() => {
    cerrarRef.current = onCerrar
  })

  useEffect(() => {
    if (!abierto) return undefined
    const previo = document.activeElement
    const alTeclear = (e) => {
      if (e.key === 'Escape') cerrarRef.current()
    }
    document.addEventListener('keydown', alTeclear)
    document.body.style.overflow = 'hidden'
    if (caja.current && !caja.current.contains(document.activeElement)) caja.current.focus()
    return () => {
      document.removeEventListener('keydown', alTeclear)
      document.body.style.overflow = ''
      previo?.focus?.()
    }
  }, [abierto])

  if (!abierto) return null

  return (
    <div
      className="modal__fondo"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onCerrar()
      }}
    >
      <div className={`modal modal--${ancho}`} role="dialog" aria-modal="true" aria-label={titulo} tabIndex={-1} ref={caja}>
        <header className="modal__cabecera">
          <h2>{titulo}</h2>
          <button type="button" className="boton boton--fantasma boton--pequeno" onClick={onCerrar} aria-label="Cerrar">
            <X size={18} />
          </button>
        </header>
        <div className="modal__cuerpo">{children}</div>
        {pie && <footer className="modal__pie">{pie}</footer>}
      </div>
    </div>
  )
}
