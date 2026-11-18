import Campo from '../Campo'

// Nombre y descripción del tipo de préstamo (se usa en la pantalla de mantenimiento y en el modal de la apertura).
export default function CamposTipoPrestamo({ prefijoId, valor, onCambio, errores }) {
  return (
    <div className="rejilla">
      <Campo
        id={`${prefijoId}-nombre`}
        etiqueta="Nombre"
        maxLength={60}
        value={valor.nombre}
        error={errores.nombre}
        onChange={(e) => onCambio({ nombre: e.target.value })}
      />
      <Campo
        id={`${prefijoId}-descripcion`}
        etiqueta="Descripción (opcional)"
        maxLength={250}
        value={valor.descripcion}
        error={errores.descripcion}
        onChange={(e) => onCambio({ descripcion: e.target.value })}
      />
    </div>
  )
}
