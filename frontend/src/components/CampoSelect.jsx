import '../styles/formularios.css'

// Lista desplegable con el mismo aspecto que Campo. opciones: [{ valor, etiqueta }]
export default function CampoSelect({ id, etiqueta, error, opciones, placeholder = 'Seleccione…', ...resto }) {
  return (
    <div className={`campo${error ? ' campo--error' : ''}`}>
      <label htmlFor={id}>{etiqueta}</label>
      <div className="campo__control">
        <select id={id} aria-invalid={Boolean(error)} {...resto}>
          <option value="">{placeholder}</option>
          {opciones.map((o) => (
            <option key={o.valor} value={o.valor}>
              {o.etiqueta}
            </option>
          ))}
        </select>
      </div>
      {error && <span className="campo__error">{error}</span>}
    </div>
  )
}
