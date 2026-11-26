import { useEffect, useState } from 'react'
import { Calculator, Plus, RotateCcw, Save, Search, UserRound } from 'lucide-react'
import useApi from '../hooks/useApi'
import { listarClientes } from '../services/clienteService'
import {
  calcularPrestamo, guardarPrestamo, obtenerCatalogosPrestamos, obtenerCreditoCliente, obtenerFechaPrimerPago,
} from '../services/prestamoService'
import {
  calcularRenovacion, guardarRenovacion, obtenerPrestamosRenovables,
} from '../services/renovacionService'
import { formatoMoneda } from '../utils/formato'
import {
  aPayloadApertura, aperturaVacia, esFechaReal, validarApertura, validarRenovar,
} from '../utils/validacionesPrestamo'
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
import ModalNuevoTipoPrestamo from '../components/prestamos/ModalNuevoTipoPrestamo'
import ResumenRenovacion from '../components/renovaciones/ResumenRenovacion'
import SeccionRenovar from '../components/renovaciones/SeccionRenovar'
import ResumenCalculo from '../components/prestamos/ResumenCalculo'
import TablaAmortizacion from '../components/prestamos/TablaAmortizacion'
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

// modo: 'apertura' (Etapa 6-7) o 'renovacion' (Etapa 9: mismo formulario + préstamos a cancelar).
export default function Prestamos({ modo = 'apertura' }) {
  const { datos: catalogos, cargando, error, recargar } = useApi(obtenerCatalogosPrestamos)

  if (cargando && !catalogos) return <Cargando texto="Cargando formulario…" />
  if (error) {
    return (
      <Alerta tipo="error" accion={<Boton variante="secundario" tamano="pequeno" onClick={recargar}>Reintentar</Boton>}>
        {error}
      </Alerta>
    )
  }
  return <FormularioApertura catalogos={catalogos} alCrearTipo={recargar} modo={modo} />
}

function FormularioApertura({ catalogos, alCrearTipo, modo }) {
  const renov = modo === 'renovacion'
  const num = (n) => n + (renov ? 1 : 0) // numeración de las secciones
  const [credito, setCredito] = useState(null)
  const [form, setForm] = useState(() => aperturaVacia(catalogos.hoy))
  const [primerPagoManual, setPrimerPagoManual] = useState(false)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState(null)
  const [consultaAbierta, setConsultaAbierta] = useState(false)
  const [tipoNuevoAbierto, setTipoNuevoAbierto] = useState(false)
  const [cargandoCredito, setCargandoCredito] = useState(false)
  const [calculo, setCalculo] = useState(null) // { cuotas, resumen } devuelto por el servidor
  const [huella, setHuella] = useState('') // datos con los que se hizo el cálculo
  const [calculando, setCalculando] = useState(false)
  const [confirmarGuardar, setConfirmarGuardar] = useState(false)
  const [guardando, setGuardando] = useState(false)
  const [renovables, setRenovables] = useState([]) // préstamos activos del cliente (solo renovación)
  const [renovar, setRenovar] = useState([]) // [{ prestamoId, base }]

  const plazo = catalogos.tiposPlazo.find((p) => p.id === Number(form.tipoPlazoId))
  const cambiar = (parcial) => setForm((f) => ({ ...f, ...parcial }))

  // El cálculo solo vale mientras los datos no cambien: si se modifica algo, hay que calcular de nuevo.
  const armarPayload = () => ({ ...aPayloadApertura(form, credito), ...(renov ? { renovar } : {}) })
  const huellaActual = JSON.stringify(armarPayload())
  const calculoVigente = calculo !== null && huella === huellaActual

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
    setCalculo(null)
    setHuella('')
    setCredito(null)
    setRenovables([])
    setRenovar([])
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
      if (renov) {
        setRenovables(await obtenerPrestamosRenovables(fila.id))
        setRenovar([])
      }
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

  async function calcular() {
    setMensaje(null)
    const nuevos = validarApertura(form, catalogos, credito, { omitirLimite: renov })
    if (renov) {
      const errorRenovar = validarRenovar(renovar)
      if (errorRenovar) nuevos.renovar = errorRenovar
    }
    setErrores(nuevos)
    if (Object.keys(nuevos).length) {
      setCalculo(null)
      setMensaje({ tipo: 'error', texto: 'Revise los campos marcados en rojo.' })
      enfocarPrimerError()
      return
    }

    setCalculando(true)
    try {
      setCalculo(await (renov ? calcularRenovacion : calcularPrestamo)(armarPayload()))
      setHuella(huellaActual)
      setMensaje({
        tipo: 'exito',
        texto: `Cálculo generado. Revise la tabla de amortización y presione ${renov ? 'Renovar' : 'Guardar'}.`,
      })
    } catch (e) {
      setCalculo(null)
      setErrores(e.errores || {})
      setMensaje({ tipo: 'error', texto: e.message })
      if (Object.keys(e.errores || {}).length) enfocarPrimerError()
    } finally {
      setCalculando(false)
    }
  }

  async function guardar() {
    setGuardando(true)
    try {
      const prestamo = await (renov ? guardarRenovacion : guardarPrestamo)(armarPayload())
      const { resumen } = prestamo
      setConfirmarGuardar(false)
      nuevo()
      setMensaje({
        tipo: 'exito',
        texto: renov
          ? `Renovación registrada: préstamo nuevo ${prestamo.numero} por ${formatoMoneda(resumen.monto)}. Recibos de cancelación: ${prestamo.renovacion.recibos.map((r) => r.numero).join(', ')}. Desembolso final: ${formatoMoneda(prestamo.renovacion.desembolsoFinal)}.`
          : `Préstamo ${prestamo.numero} registrado: ${formatoMoneda(resumen.monto)} en ${resumen.plazo} cuota(s). Desembolso: ${formatoMoneda(resumen.desembolso)}.`,
      })
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (e) {
      // El servidor revalida: si ya no cumple (por ejemplo, el límite cambió), se vuelve a Calcular.
      setConfirmarGuardar(false)
      setCalculo(null)
      setErrores(e.errores || {})
      setMensaje({ tipo: 'error', texto: e.message })
      if (credito) {
        obtenerCreditoCliente(credito.id).then(setCredito).catch(() => {}) // refresca el disponible
        if (renov) obtenerPrestamosRenovables(credito.id).then(setRenovables).catch(() => {})
      }
    } finally {
      setGuardando(false)
    }
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
          <Boton variante="secundario" icono={Calculator} cargando={calculando} onClick={calcular}>Calcular</Boton>
          <Boton icono={Save} disabled={!calculoVigente} onClick={() => setConfirmarGuardar(true)}>{renov ? 'Renovar' : 'Guardar'}</Boton>
        </div>
      </div>

      {mensaje && <div style={{ marginBottom: 16 }}><Alerta tipo={mensaje.tipo}>{mensaje.texto}</Alerta></div>}

      <SeccionForm titulo="1. Cliente" descripcion={renov ? 'Primero elija el cliente: se consultan sus préstamos activos.' : 'Solo se pueden aperturar préstamos a clientes activos.'}>
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
              <div><dt>{renov ? 'Disponible (antes de cancelar)' : 'Disponible'}</dt><dd className="resumen-cliente__disponible">{formatoMoneda(credito.disponible)}</dd></div>
              <div><dt>Garante</dt><dd>{credito.garante ? credito.garante.nombreCompleto : 'Sin garante vigente'}</dd></div>
            </dl>
          </div>
        )}
      </SeccionForm>

      {renov && (
        <SeccionForm titulo="2. Préstamos a renovar" descripcion="De 1 a 3 préstamos activos. Elija la base de saldo a cancelar de cada uno.">
          {credito ? (
            <SeccionRenovar renovables={renovables} renovar={renovar} onCambiar={setRenovar} error={errores.renovar} />
          ) : (
            <EstadoVacio compacto titulo="Seleccione un cliente" texto="Aquí aparecerán sus préstamos activos." />
          )}
        </SeccionForm>
      )}

      <SeccionForm titulo={`${num(2)}. Condiciones del préstamo${renov ? ' nuevo' : ''}`}>
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
            etiqueta={plazo?.plazoMinimo != null && plazo?.plazoMaximo != null ? `Plazo (cuotas, de ${plazo.plazoMinimo} a ${plazo.plazoMaximo})` : 'Plazo (cantidad de cuotas)'}
            type="number" min={plazo?.plazoMinimo ?? 1} max={plazo?.plazoMaximo} step="1"
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

      <SeccionForm titulo={`${num(3)}. Mora y seguro`}>
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

      <SeccionForm titulo={`${num(4)}. Gasto de cierre y fechas`}>
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

      <SeccionForm titulo={`${num(5)}. Tabla de amortización`} descripcion="Se genera al presionar Calcular. Los valores los calcula el servidor.">
        {calculoVigente ? (
          <>
            {renov && calculo.renovacion && <ResumenRenovacion renovacion={calculo.renovacion} />}
            <ResumenCalculo resumen={calculo.resumen} />
            {calculo.resumen.soloPrimeraCuota && (
              <Alerta tipo="info">
                Saldo fijo: al aperturar solo se genera la primera cuota. Las siguientes se generan al saldarse la anterior.
              </Alerta>
            )}
            <TablaAmortizacion cuotas={calculo.cuotas} />
          </>
        ) : calculo ? (
          <Alerta tipo="info">Los datos cambiaron desde el último cálculo. Presione Calcular de nuevo para poder guardar.</Alerta>
        ) : (
          <EstadoVacio
            compacto
            titulo="Aún no hay cálculo"
            texto="Complete el formulario y presione Calcular para ver las cuotas, el desembolso y la fecha del primer pago."
          />
        )}
      </SeccionForm>

      <ModalConsulta
        abierto={consultaAbierta}
        titulo="Seleccionar cliente"
        columnas={COLUMNAS_CLIENTES}
        cargar={cargarClientesActivos}
        onSeleccionar={seleccionarCliente}
        onCerrar={() => setConsultaAbierta(false)}
      />

      <ConfirmDialog
        abierto={confirmarGuardar}
        titulo={renov ? 'Confirmar renovación' : 'Confirmar apertura del préstamo'}
        mensaje={
          calculoVigente && credito
            ? renov && calculo.renovacion
              ? `Se cancelarán ${calculo.renovacion.prestamos.length} préstamo(s) por ${formatoMoneda(calculo.renovacion.totalSaldoCancelado)} (descuento ${formatoMoneda(calculo.renovacion.totalDescuento)}) y se aperturará un préstamo nuevo de ${formatoMoneda(calculo.resumen.monto)} a ${credito.nombreCompleto}. Desembolso final: ${formatoMoneda(calculo.renovacion.desembolsoFinal)}. Esta acción no se puede deshacer.`
              : `Se aperturará un préstamo de ${formatoMoneda(calculo.resumen.monto)} a ${credito.nombreCompleto}, con desembolso de ${formatoMoneda(calculo.resumen.desembolso)}. Los préstamos no se eliminan una vez registrados.`
            : ''
        }
        textoConfirmar={renov ? 'Renovar préstamos' : 'Aperturar préstamo'}
        cargando={guardando}
        onConfirmar={guardar}
        onCancelar={() => setConfirmarGuardar(false)}
      />

      <ModalNuevoTipoPrestamo abierto={tipoNuevoAbierto} onCreado={tipoCreado} onCerrar={() => setTipoNuevoAbierto(false)} />
    </div>
  )
}
