import '../styles/tabla.css'

// Tabla moderna con selección de fila.
//  - Un clic (o Espacio) selecciona la fila; doble clic (o Enter) la elige directamente.
//  - columnas: [{ clave, titulo, render?(fila) }]
export default function Tabla({ columnas, filas, claveFila = 'id', seleccionada, onSeleccionar, onDobleClic }) {
  return (
    <div className="tabla__contenedor">
      <table className="tabla">
        <thead>
          <tr>
            {columnas.map((c) => (
              <th key={c.clave}>{c.titulo}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => (
            <tr
              key={fila[claveFila]}
              className={seleccionada === fila[claveFila] ? 'tabla__fila--activa' : ''}
              tabIndex={0}
              onClick={() => onSeleccionar?.(fila)}
              onDoubleClick={() => onDobleClic?.(fila)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') onDobleClic?.(fila)
                if (e.key === ' ') {
                  e.preventDefault()
                  onSeleccionar?.(fila)
                }
              }}
            >
              {columnas.map((c) => (
                <td key={c.clave}>{c.render ? c.render(fila) : fila[c.clave]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
