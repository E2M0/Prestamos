import '../styles/formularios.css'

// Barra de pestañas para dividir un formulario largo. No muestra el contenido: cada panel
// lo decide el formulario con <PanelPestana>. Una pestaña con errores se marca con un punto rojo.
export default function Pestanas({ pestanas, activa, onCambio }) {
  return (
    <div className="pestanas" role="tablist">
      {pestanas.map((p) => (
        <button
          key={p.id}
          type="button"
          role="tab"
          id={`pestana-${p.id}`}
          aria-selected={activa === p.id}
          aria-controls={`panel-${p.id}`}
          className={`pestanas__boton${activa === p.id ? ' pestanas__boton--activa' : ''}`}
          onClick={() => onCambio(p.id)}
        >
          {p.titulo}
          {p.conError && <span className="pestanas__error" title="Hay campos por corregir" aria-label="Hay campos por corregir" />}
        </button>
      ))}
    </div>
  )
}

// Los paneles inactivos se ocultan pero siguen montados, para no perder lo que el usuario escribió.
export function PanelPestana({ id, activa, children }) {
  return (
    <div role="tabpanel" id={`panel-${id}`} aria-labelledby={`pestana-${id}`} hidden={activa !== id}>
      {children}
    </div>
  )
}
