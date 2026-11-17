// tono: esmeralda | rojo | neutro
export default function TarjetaEstadistica({ icono: Icono, titulo, valor, detalle, tono = 'esmeralda' }) {
  return (
    <article className="tarjeta estadistica">
      <div className={`estadistica__icono estadistica__icono--${tono}`}>
        <Icono size={20} />
      </div>
      <div>
        <p className="estadistica__titulo">{titulo}</p>
        <p className="estadistica__valor">{valor}</p>
        {detalle && <p className="estadistica__detalle">{detalle}</p>}
      </div>
    </article>
  )
}
