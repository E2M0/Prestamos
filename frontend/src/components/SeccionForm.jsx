import '../styles/formularios.css'

// Tarjeta con título para dividir los formularios largos en secciones.
export default function SeccionForm({ titulo, descripcion, children }) {
  return (
    <section className="seccion">
      <header className="seccion__cabecera">
        <h2>{titulo}</h2>
        {descripcion && <p>{descripcion}</p>}
      </header>
      <div className="seccion__cuerpo">{children}</div>
    </section>
  )
}
