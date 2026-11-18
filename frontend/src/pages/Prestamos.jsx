import { useEffect, useState } from 'react'
import { Calculator, Plus, RotateCcw, Save, Search, UserRound } from 'lucide-react'
import useApi from '../hooks/useApi'
import { listarClientes } from '../services/clienteService'
import { obtenerCatalogosPrestamos, obtenerCreditoCliente, obtenerFechaPrimerPago } from '../services/prestamoService'
import { formatoMoneda } from '../utils/formato'
import { aperturaVacia, esFechaReal, validarApertura } from '../utils/validacionesPrestamo'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import Campo from '../components/Campo'
import CampoSelect from '../components/CampoSelect'
import Cargando from '../components/Cargando'
import EstadoVacio from '../components/EstadoVacio'
import Etiqueta from '../components/Etiqueta'
import ModalConsulta from '../components/ModalConsulta'
import SeccionForm from '../components/SeccionForm'
import ModalNuevoTipoPrestamo from '../components/prestamos/ModalNuevoTipoPrestamo'
import '../styles/formularios.css'

// Fuera del componente para que ModalConsulta reciba referencias estables. Solo clientes activos.
const cargarClientesActivos = (filtros) => listarClientes({ ...filtros, rol: 'cliente', estado: 'Activo' })
const COLUMNAS_CLIENTES = [
  { clave: 'numeroDocumento', titulo: 'Documento' },
  { clave: 'nombreCompleto', titulo: 'Nombre' },
  { clave: 'limiteCredito', titulo: 'Límite', render: (f) => formatoMoneda(f.limiteCredito) },
  { clave: 'clasificacion', titulo: 'Clasif.', render: (f) => f.clasificacion || '—' },
]

const enfocarPrimerError = () =>
  setTimeout(() => {
    const campo = document.querySelector('[aria-invalid="true"]')
    campo?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    campo?.focus({ preventScroll: true })
  }, 60)

export default function Prestamos() {
  const { datos: catalogos, cargando, error, recargar } = useApi(obtenerCatalogosPrestamos)

  if (cargando && !catalogos) return <Cargando texto="Cargando formulario…" />
  if (error) {
    return (
      <Alerta tipo="error" accion={<Boton variante="secundario" tamano="pequeno" onClick={recargar}>Reintentar</Boton>}>
        {error}
      </Alerta>
    )
  }
  return <FormularioApertura catalogos={catalogos} alCrearTipo={recargar} />
}

function FormularioApertura({ catalogos, alCrearTipo }) {
  const [credito, setCredito] = useState(null)
  const [form, setForm] = useState(() => aperturaVacia(catalogos.hoy))
  const [primerPagoManual, setPrimerPagoManual] = useState(false)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState(null)
  const [consultaAbierta, setConsultaAbierta] = useState(false)
  const [tipoNuevoAbierto, setTipoNuevoAbierto] = useState(false)
  const [cargandoCredito, setCargandoCredito] = useState(false)

  const plazo = catalogos.tiposPlazo.find((p) => p.id === Number(form.tipoPlazoId))
  const cambiar = (parcial) => setForm((f) => ({ ...f, ...parcial }))

  // La fecha del primer pago se sugiere según el tipo de plazo (la calcula el servidor);
  // si el usuario la cambia a mano, deja de actualizarse sola.
  useEffect(() => {
    if (primerPagoManual || !plazo || !esFechaReal(form.fechaApertura)) return undefined
    let vigente = true
    obtenerFechaPrimerPago({
      fechaApertura: form.fechaApertura,
      tipoPlazoId: plazo.id,
      incluyeSabado: form.incluyeSabado,
      incluyeDomingo: form.incluyeDomingo,
    })
      .then((fecha) => vigente && setForm((f) => ({ ...f, fechaPrimerPago: fecha })))
      .catch(() => {}) // es solo una guía: si falla, el usuario puede digitar la fecha
    return () => {
      vigente = false
    }
  }, [primerPagoManual, plazo, form.fechaApertura, form.incluyeSabado, form.incluyeDomingo])

  function nuevo() {
    setCredito(null)
    setForm(aperturaVacia(catalogos.hoy))
    setPrimerPagoManual(false)
    setErrores({})
    setMensaje(null)
  }

  async function seleccionarCliente(fila) {
    setConsultaAbierta(false)
    setMensaje(null)
    setCargandoCredito(true)
    try {
      setCredito(await obtenerCreditoCliente(fila.id))
      setErrores((e) => ({ ...e, cliente: undefined }))
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    } finally {
      setCargandoCredito(false)
    }
  }

  function cambiarPlazo(valor) {
    const id = valor ? Number(valor) : ''
    const nuevoPlazo = catalogos.tiposPlazo.find((p) => p.id === id)
    // Solo el plazo diario permite excluir fines de semana; en los demás se restablece.
    cambiar({
      tipoPlazoId: id,
      ...(nuevoPlazo?.permiteExcluirFinesSemana ? {} : { incluyeSabado: true, incluyeDomingo: true }),
    })
  }

  function tipoCreado(tipo) {
    setTipoNuevoAbierto(false)
    alCrearTipo() // recarga el catálogo para que el tipo nuevo aparezca en la lista
    cambiar({ tipoPrestamoId: tipo.id })
    setMensaje({ tipo: 'exito', texto: `Tipo de préstamo "${tipo.nombre}" creado y seleccionado.` })
  }

  function calcular() {
    setMensaje(null)
    const nuevos = validarApertura(form, catalogos, credito)
    setErrores(nuevos)
    if (Object.keys(nuevos).length) {
      setMensaje({ tipo: 'error', texto: 'Revise los campos marcados en rojo.' })
      enfocarPrimerError()
      return
    }
    setMensaje({
      tipo: 'info',
      texto:
        'Los datos son válidos. La tabla de amortización, el desembolso y el guardado del préstamo se construyen en la Etapa 7.',
    })
  }

  const tiposPrestamo = catalogos.tiposPrestamo.map((t) => ({ valor: t.id, etiqueta: t.nombre }))

  return (
    <div>
      <div className="barra-acciones">
        <div className="barra-acciones__grupo">
          <Boton variante="secundario" icono={Plus} onClick={nuevo}>Nuevo</Boton>
          <Boton variante="fantasma" icono={RotateCcw} onClick={nuevo}>Limpiar</Boton>
        </div>
        <div className="barra-acciones__grupo">
          <Boton variante="secundario" icono={Calculator} onClick={calcular}>Calcular</Boton>
          <Boton icono={Save} disabled title="Se habilita en la Etapa 7">Guardar</Boton>
        </div>
      </div>

      {mensaje && <div style={{ marginBottom: 16 }}><Alerta tipo={mensaje.tipo}>{mensaje.texto}</Alerta></div>}

      <SeccionForm titulo="1. Cliente" descripcion="Solo se pueden aperturar préstamos a clientes activos.">
        <div>
          <Boton variante="secundario" icono={Search} cargando={cargandoCredito} onClick={() => setConsultaAbierta(true)}>
            {credito ? 'Cambiar cliente' : 'Seleccionar cliente'}
          </Boton>
          {errores.cliente && <p className="campo__error--bloque" style={{ marginTop: 8 }}>{errores.cliente}</p>}
        </div>

        {credito && (
          <div className="resumen-cliente">
            <div className="resumen-cliente__nombre">
              <UserRound size={18} />
              <strong>{credito.nombreCompleto}</strong>
              <Etiqueta estado={credito.estado} />
            </div>
            <dl className="resumen-cliente__datos">
              <div><dt>Documento</dt><dd>{credito.numeroDocumento}</dd></div>
              <div><dt>Límite de crédito</dt><dd>{formatoMoneda(credito.limiteCredito)}</dd></div>
              <div><dt>Capital pendiente ({credito.prestamosActivos} activo{credito.prestamosActivos === 1 ? '' : 's'})</dt><dd>{formatoMoneda(credito.capitalPendiente)}</dd></div>
              <div><dt>Disponible</dt><dd className="resumen-cliente__disponible">{formatoMoneda(credito.disponible)}</dd></div>
              <div><dt>Garante</dt><dd>{credito.garante ? credito.garante.nombreCompleto : 'Sin garante vigente'}</dd></div>
            </dl>
          </div>
        )}
      </SeccionForm>

      <SeccionForm titulo="2. Condiciones del préstamo">
        <div className="rejilla">
          <div className="campo-con-accion">
            <CampoSelect
              id="prestamo-tipoPrestamoId"
              etiqueta="Tipo de préstamo"
              value={form.tipoPrestamoId}
              error={errores.tipoPrestamoId}
              opciones={tiposPrestamo}
              onChange={(e) => cambiar({ tipoPrestamoId: e.target.value ? Number(e.target.value) : '' })}
            />
            <Boton variante="secundario" tamano="pequeno" icono={Plus} onClick={() => setTipoNuevoAbierto(true)}>
              Nuevo tipo
            </Boton>
          </div>
          <Campo
            id="prestamo-monto"
            etiqueta="Monto (RD$)"
            type="number" min="0" step="0.01"
            value={form.monto}
            error={errores.monto}
            onChange={(e) => cambiar({ monto: e.target.value })}
          />
          <Campo
            id="prestamo-plazo"
            etiqueta="Plazo (cantidad de cuotas)"
            type="number" min="1" step="1"
            value={form.plazo}
            error={errores.plazo}
            onChange={(e) => cambiar({ plazo: e.target.value })}
          />
          <Campo
            id="prestamo-tasa"
            etiqueta="Tasa de interés (% mensual)"
            type="number" min="0" step="0.01"
            value={form.tasa}
            error={errores.tasa}
            onChange={(e) => cambiar({ tasa: e.target.value })}
          />
          <CampoSelect
            id="prestamo-tipoPlazoId"
            etiqueta="Tipo de plazo"
            value={form.tipoPlazoId}
            error={errores.tipoPlazoId}
            opciones={catalogos.tiposPlazo.map((p) => ({ valor: p.id, etiqueta: p.nombre }))}
            onChange={(e) => cambiarPlazo(e.target.value)}
          />
          <CampoSelect
            id="prestamo-tipoAmortizacionId"
            etiqueta="Tipo de amortización"
            value={form.tipoAmortizacionId}
            error={errores.tipoAmortizacionId}
            opciones={catalogos.tiposAmortizacion.map((a) => ({ valor: a.id, etiqueta: a.nombre }))}
            onChange={(e) => cambiar({ tipoAmortizacionId: e.target.value ? Number(e.target.value) : '' })}
          />
        </div>

        {plazo?.permiteExcluirFinesSemana && (
          <div className="opciones-fila">
            <label className="casilla">
              <input type="checkbox" checked={form.incluyeSabado} onChange={(e) => cambiar({ incluyeSabado: e.target.checked })} />
              Incluir sábados
            </label>
            <label className="casilla">
              <input type="checkbox" checked={form.incluyeDomingo} onChange={(e) => cambiar({ incluyeDomingo: e.target.checked })} />
              Incluir domingos
            </label>
          </div>
        )}
      </SeccionForm>

      <SeccionForm titulo="3. Mora y seguro">
        <div className="rejilla">
          <CampoSelect
            id="prestamo-tipoMora"
            etiqueta="Mora por cuota vencida"
            value={form.tipoMora}
            placeholder="Seleccione…"
            opciones={catalogos.tiposMora.map((m) => ({ valor: m, etiqueta: m === 'Porcentaje' ? 'Porcentaje de la cuota' : 'Monto fijo' }))}
            onChange={(e) => cambiar({ tipoMora: e.target.value || 'Porcentaje' })}
          />
          <Campo
            id="prestamo-valorMora"
            etiqueta={form.tipoMora === 'Porcentaje' ? 'Mora (%)' : 'Mora (RD$)'}
            type="number" min="0" step="0.01"
            value={form.valorMora}
            error={errores.valorMora}
            onChange={(e) => cambiar({ valorMora: e.target.value })}
          />
          <CampoSelect
            id="prestamo-seguroTipo"
            etiqueta="Seguro"
            value={form.seguroTipo}
            placeholder="Seleccione…"
            opciones={catalogos.tiposSeguro.map((s) => ({ valor: s, etiqueta: s === 'Fijo' ? 'Fijo (el mismo monto en cada cuota)' : 'Repartido (dividido entre las cuotas)' }))}
            onChange={(e) => cambiar({ seguroTipo: e.target.value || 'Fijo' })}
          />
          <Campo
            id="prestamo-seguroMonto"
            etiqueta={form.seguroTipo === 'Fijo' ? 'Seguro por cuota (RD$)' : 'Seguro total (RD$)'}
            type="number" min="0" step="0.01"
            value={form.seguroMonto}
            error={errores.seguroMonto}
            onChange={(e) => cambiar({ seguroMonto: e.target.value })}
          />
        </div>
      </SeccionForm>

      <SeccionForm titulo="4. Gasto de cierre y fechas">
        <div className="rejilla">
          <Campo
            id="prestamo-gastoCierre"
            etiqueta="Gasto de cierre (RD$)"
            type="number" min="0" step="0.01"
            value={form.gastoCierre}
            error={errores.gastoCierre}
            onChange={(e) => cambiar({ gastoCierre: e.target.value })}
          />
          <div className="campo">
            <span className="campo__etiqueta-falsa">Forma de cobro</span>
            <label className="casilla casilla--alta">
              <input type="checkbox" checked={form.gastoFinanciado} onChange={(e) => cambiar({ gastoFinanciado: e.target.checked })} />
              Financiar el gasto de cierre (se suma a las cuotas en vez de descontarse del desembolso)
            </label>
          </div>
          <Campo
            id="prestamo-fechaApertura"
            etiqueta="Fecha de apertura"
            type="date"
            value={form.fechaApertura}
            error={errores.fechaApertura}
            onChange={(e) => cambiar({ fechaApertura: e.target.value })}
          />
          <div>
            <Campo
              id="prestamo-fechaPrimerPago"
              etiqueta="Fecha del primer pago"
              type="date"
              value={form.fechaPrimerPago}
              error={errores.fechaPrimerPago}
              onChange={(e) => {
                setPrimerPagoManual(true)
                cambiar({ fechaPrimerPago: e.target.value })
              }}
            />
            {primerPagoManual && (
              <button type="button" className="enlace-boton" onClick={() => setPrimerPagoManual(false)}>
                Usar la fecha sugerida
              </button>
            )}
          </div>
        </div>
      </SeccionForm>

      <SeccionForm titulo="5. Tabla de amortización" descripcion="Se genera al presionar Calcular.">
        <EstadoVacio
          compacto
          titulo="Aún no hay cálculo"
          texto="El cálculo de cuotas, desembolso y balances lo hace el servidor y se construye en la Etapa 7."
        />
      </SeccionForm>

      <ModalConsulta
        abierto={consultaAbierta}
        titulo="Seleccionar cliente"
        columnas={COLUMNAS_CLIENTES}
        cargar={cargarClientesActivos}
        onSeleccionar={seleccionarCliente}
        onCerrar={() => setConsultaAbierta(false)}
      />

      <ModalNuevoTipoPrestamo abierto={tipoNuevoAbierto} onCreado={tipoCreado} onCerrar={() => setTipoNuevoAbierto(false)} />
    </div>
  )
}
