import { formatoFecha, formatoMoneda } from '../../utils/formato'

// Campos informativos del cálculo. Todos los valores vienen calculados del servidor; aquí solo se muestran.
export default function ResumenCalculo({ resumen }) {
  const items = [
    { titulo: 'Desembolso (monto a entregar)', valor: formatoMoneda(resumen.desembolso), destacado: true },
    { titulo: 'Fecha del primer pago', valor: formatoFecha(resumen.fechaPrimerPago), destacado: true },
    { titulo: resumen.soloPrimeraCuota ? 'Valor de la primera cuota' : 'Valor de la cuota', valor: formatoMoneda(resumen.valorPrimeraCuota) },
    { titulo: `Tasa por período`, valor: `${resumen.tasaPeriodo}%` },
    ...(resumen.soloPrimeraCuota
      ? []
      : [
          { titulo: 'Total de intereses', valor: formatoMoneda(resumen.totalIntereses) },
          { titulo: 'Total a pagar (cuotas)', valor: formatoMoneda(resumen.totalCuotas) },
          { titulo: 'Última cuota', valor: formatoFecha(resumen.fechaUltimaCuota) },
        ]),
  ]
  return (
    <div className="resumen-calculo">
      {items.map((it) => (
        <div key={it.titulo} className={`resumen-calculo__item${it.destacado ? ' resumen-calculo__item--destacado' : ''}`}>
          <span>{it.titulo}</span>
          <strong>{it.valor}</strong>
        </div>
      ))}
    </div>
  )
}
