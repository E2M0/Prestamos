// Campo de formulario: etiqueta + input + (icono opcional) + mensaje de error.
// El resto de propiedades (type, value, onChange, autoComplete...) pasan directo al <input>.
export default function Campo({ id, etiqueta, icono: Icono, error, ...entrada }) {
  return (
    <div className={`campo${error ? ' campo--error' : ''}`}>
      <label htmlFor={id}>{etiqueta}</label>
      <div className={`campo__control${Icono ? ' campo__control--icono' : ''}`}>
        {Icono && <Icono size={16} />}
        <input id={id} aria-invalid={Boolean(error)} {...entrada} />
      </div>
      {error && <span className="campo__error">{error}</span>}
    </div>
  )
}
