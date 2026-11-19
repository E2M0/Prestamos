import { useCallback, useMemo, useState } from 'react'
import { Calculator, HandCoins, RotateCcw, Search } from 'lucide-react'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import Campo from '../components/Campo'
import CampoSelect from '../components/CampoSelect'
import Cargando from '../components/Cargando'
import ConfirmDialog from '../components/ConfirmDialog'
import EstadoVacio from '../components/EstadoVacio'
import Etiqueta from '../components/Etiqueta'
import ModalConsulta from '../components/ModalConsulta'
import SeccionForm from '../components/SeccionForm'
import ModalRecibo from '../components/recibos/ModalRecibo'
import PanelCuenta from '../components/recibos/PanelCuenta'
import VistaPreviaPago from '../components/recibos/VistaPreviaPago'
import {
  calcularPago, guardarPago, listarPrestamos, obtenerCuenta, obtenerRecibo,
} from '../services/reciboService'
import { formatoMoneda } from '../utils/formato'
import {
  aPayloadPago, FORMATOS, MODALIDADES, TIPOS_PAGO, validarPago,
} from '../utils/validacionesRecibo'
import '../styles/recibos.css'

const FORMULARIO_VACIO = { tipoPago: 'normal', monto: '', modalidad: '', formato: 'A4', observacion: '' }

const COLUMNAS_PRESTAMOS = [
  { clave: 'numero', titulo: 'Préstamo' },
  { clave: 'cliente', titulo: 'Cliente' },
  { clave: 'numeroDocumento', titulo: 'Documento' },
  { clave: 'capitalPendiente', titulo: 'Capital pendiente', render: (f) => formatoMoneda(f.capitalPendiente) },
  { clave: 'estado', titulo: 'Estado', render: (f) => <Etiqueta estado={f.estado} /> },
]
const FILTROS_PRESTAMOS = [{
  clave: 'estado',
  etiqueta: 'Estado',
  opciones: [
    { valor: '', etiqueta: 'Todos los estados' },
    { valor: 'Activo', etiqueta: 'Activos' },
    { valor: 'Saldado', etiqueta: 'Saldados' },
    { valor: 'Renovado', etiqueta: 'Renovados' },
  ],
}]

export default function Recibos() {
  const [cuenta, setCuenta] = useState(null)
  const [cargandoCuenta, setCargandoCuenta] = useState(false)
  const [consultaAbierta, setConsultaAbierta] = useState(false)
  const [form, setForm] = useState(FORMULARIO_VACIO)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState(null)
  const [previa, setPrevia] = useState(null) // { firma, datos }
  const [calculando, setCalculando] = useState(false)
  const [confirmar, setConfirmar] = useState(false)
  const [cobrando, setCobrando] = useState(false)
  const [recibo, setRecibo] = useState(null)

  const prestamo = cuenta?.prestamo ?? null
  const firma = useMemo(() => JSON.stringify(form), [form])
  const previaVigente = previa && previa.firma === firma ? previa.datos : null

  const cargarCuenta = useCallback(async (id) => {
    setCargandoCuenta(true)
    try {
      setCuenta(await obtenerCuenta(id))
      return true
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
      return false
    } finally {
      setCargandoCuenta(false)
    }
  }, [])

  async function elegirPrestamo(fila) {
    setConsultaAbierta(false)
    setForm(FORMULARIO_VACIO)
    setErrores({})
    setPrevia(null)
    setMensaje(null)
    await cargarCuenta(fila.id)
  }

  function limpiar() {
    setCuenta(null)
    setForm(FORMULARIO_VACIO)
    setErrores({})
    setPrevia(null)
    setMensaje(null)
  }

  function cambiar(campo, valor) {
    setForm((f) => ({ ...f, [campo]: valor }))
    setErrores((e) => ({ ...e, [campo]: undefined }))
  }

  // Los valores sugeridos los calcula el servidor; el botón solo los copia al formulario.
  function sugerir(tipoPago, valor) {
    setForm((f) => ({ ...f, tipoPago, monto: Number(valor).toFixed(2) }))
    setErrores({})
  }

  function mostrarErrorApi(e) {
    if (e.errores && Object.keys(e.errores).length) setErrores(e.errores)
    setMensaje({ tipo: 'error', texto: e.message })
  }

  async function calcular() {
    setMensaje(null)
    const v = validarPago(form, prestamo)
    setErrores(v)
    if (Object.keys(v).length) return
    setCalculando(true)
    try {
      const datos = await calcularPago(aPayloadPago(form, prestamo.id))
      setPrevia({ firma, datos })
    } catch (e) {
      setPrevia(null)
      mostrarErrorApi(e)
    } finally {
      setCalculando(false)
    }
  }

  async function cobrar() {
    setCobrando(true)
    try {
      const guardado = await guardarPago(aPayloadPago(form, prestamo.id))
      setConfirmar(false)
      setRecibo(guardado)
      setForm(FORMULARIO_VACIO)
      setPrevia(null)
      setErrores({})
      setMensaje({ tipo: 'exito', texto: `Pago registrado. Recibo ${guardado.numero}.` })
      await cargarCuenta(prestamo.id)
    } catch (e) {
      setConfirmar(false)
      mostrarErrorApi(e)
      await cargarCuenta(prestamo.id) // por si el estado cambió mientras tanto
    } finally {
      setCobrando(false)
    }
  }

  async function abrirRecibo(id) {
    try {
      setRecibo(await obtenerRecibo(id))
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    }
  }

  const r = cuenta?.resumen
  const activo = prestamo?.estado === 'Activo'

  return (
    <div>
      <div className="barra-acciones">
        <div className="barra-acciones__grupo">
          <Boton variante="secundario" icono={Search} onClick={() => setConsultaAbierta(true)}>
            {prestamo ? 'Cambiar préstamo' : 'Seleccionar préstamo'}
          </Boton>
          <Boton variante="fantasma" icono={RotateCcw} onClick={limpiar}>Limpiar</Boton>
        </div>
        <div className="barra-acciones__grupo">
          <Boton variante="secundario" icono={Calculator} cargando={calculando} disabled={!activo} onClick={calcular}>
            Calcular pago
          </Boton>
          <Boton icono={HandCoins} disabled={!previaVigente} onClick={() => setConfirmar(true)}>Cobrar</Boton>
        </div>
      </div>

      {mensaje && <div style={{ marginBottom: 16 }}><Alerta tipo={mensaje.tipo}>{mensaje.texto}</Alerta></div>}
      {errores.prestamo && <div style={{ marginBottom: 16 }}><Alerta tipo="error">{errores.prestamo}</Alerta></div>}

      {cargandoCuenta && !cuenta && <Cargando />}

      {!cuenta && !cargandoCuenta && (
        <EstadoVacio
          icono={HandCoins}
          titulo="Seleccione un préstamo"
          texto="Busque por número de préstamo, nombre o documento del cliente para registrar un pago."
        />
      )}

      {cuenta && (
        <>
          <PanelCuenta cuenta={cuenta} onAbrirRecibo={abrirRecibo} />

          <SeccionForm
            titulo="3. Pago"
            descripcion={activo ? 'Elija el tipo de pago, calcule y revise cómo se aplica antes de cobrar.' : `El préstamo está ${prestamo.estado}: no admite pagos.`}
          >
            <div className="rejilla">
              <CampoSelect
                id="tipoPago" etiqueta="Tipo de pago" opciones={TIPOS_PAGO} placeholder="Tipo de pago…"
                value={form.tipoPago} onChange={(e) => cambiar('tipoPago', e.target.value)}
                error={errores.tipoPago} disabled={!activo}
              />
              <Campo
                id="monto" etiqueta="Monto recibido (RD$)" inputMode="decimal" placeholder="0.00"
                value={form.monto} onChange={(e) => cambiar('monto', e.target.value)}
                error={errores.monto} disabled={!activo}
              />
              {form.tipoPago === 'abono_capital' && (
                <CampoSelect
                  id="modalidad" etiqueta="Aplicar el abono" opciones={MODALIDADES} placeholder="Seleccione…"
                  value={form.modalidad} onChange={(e) => cambiar('modalidad', e.target.value)}
                  error={errores.modalidad} disabled={!activo}
                />
              )}
              <CampoSelect
                id="formato" etiqueta="Formato del recibo" opciones={FORMATOS} placeholder="Formato…"
                value={form.formato} onChange={(e) => e.target.value && cambiar('formato', e.target.value)}
                disabled={!activo}
              />
              <div className="rejilla__completo">
                <Campo
                  id="observacion" etiqueta="Observación (opcional)" maxLength={250}
                  value={form.observacion} onChange={(e) => cambiar('observacion', e.target.value)}
                  error={errores.observacion} disabled={!activo}
                />
              </div>
            </div>

            {activo && (
              <div className="cobro__rapidos">
                <span>Valores sugeridos por el sistema:</span>
                <Boton tamano="pequeno" variante="secundario" disabled={!r.proximaCuota} onClick={() => sugerir('normal', r.proximaCuota)}>
                  Próxima cuota {formatoMoneda(r.proximaCuota)}
                </Boton>
                <Boton tamano="pequeno" variante="secundario" disabled={!r.totalVencido} onClick={() => sugerir('normal', r.totalVencido)}>
                  Total vencido {formatoMoneda(r.totalVencido)}
                </Boton>
                <Boton tamano="pequeno" variante="secundario" disabled={!r.moraPendiente} onClick={() => sugerir('solo_mora', r.moraPendiente)}>
                  Mora {formatoMoneda(r.moraPendiente)}
                </Boton>
                <Boton
                  tamano="pequeno" variante="secundario" disabled={!r.abonoMaximo}
                  title={r.moraPendiente > 0 ? 'Pague primero la mora pendiente' : 'Capital de las cuotas aún no vencidas'}
                  onClick={() => setForm((f) => ({ ...f, tipoPago: 'abono_capital', monto: Number(r.abonoMaximo).toFixed(2) }))}
                >
                  Abono máximo {formatoMoneda(r.abonoMaximo)}
                </Boton>
              </div>
            )}

            {previaVigente && <VistaPreviaPago previa={previaVigente} plazoActual={prestamo.plazo} />}
            {previa && !previaVigente && (
              <Alerta tipo="info">Cambió el pago: vuelva a pulsar «Calcular pago» para ver cómo se aplica.</Alerta>
            )}
          </SeccionForm>
        </>
      )}

      <ModalConsulta
        abierto={consultaAbierta}
        titulo="Seleccionar préstamo"
        columnas={COLUMNAS_PRESTAMOS}
        filtros={FILTROS_PRESTAMOS}
        cargar={listarPrestamos}
        placeholder="Buscar por número, cliente o documento…"
        onSeleccionar={elegirPrestamo}
        onCerrar={() => setConsultaAbierta(false)}
      />

      <ConfirmDialog
        abierto={confirmar}
        titulo="Confirmar cobro"
        mensaje={previaVigente ? `Se registrará un recibo por ${formatoMoneda(previaVigente.monto)} en el préstamo ${prestamo.numero}. Esta acción no se puede deshacer.` : ''}
        textoConfirmar="Cobrar y generar recibo"
        cargando={cobrando}
        onConfirmar={cobrar}
        onCancelar={() => setConfirmar(false)}
      />

      <ModalRecibo abierto={Boolean(recibo)} recibo={recibo} onCerrar={() => setRecibo(null)} />
    </div>
  )
}
