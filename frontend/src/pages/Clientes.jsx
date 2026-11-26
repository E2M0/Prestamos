import { useState } from 'react'
import { Plus, Power, RotateCcw, Save, Search } from 'lucide-react'
import useApi from '../hooks/useApi'
import { obtenerCatalogosClientes } from '../services/catalogoService'
import { buscarPersona, cambiarEstadoCliente, guardarCliente, listarClientes, obtenerCliente } from '../services/clienteService'
import { aPayload, clienteDeDetalle, clienteVacio, personaDeDetalle } from '../utils/clienteForm'
import { clasificacionPara, erroresCredito, etiquetaClasificacion, LIMITE_MAXIMO, LIMITE_MINIMO } from '../utils/creditoCliente'
import { validarDocumento } from '../utils/documento'
import { validarCliente } from '../utils/validacionesCliente'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import Campo from '../components/Campo'
import CampoSelect from '../components/CampoSelect'
import Cargando from '../components/Cargando'
import ConfirmDialog from '../components/ConfirmDialog'
import Etiqueta from '../components/Etiqueta'
import Pestanas, { PanelPestana } from '../components/Pestanas'
import ModalConsulta from '../components/ModalConsulta'
import SeccionForm from '../components/SeccionForm'
import CamposLaborales from '../components/clientes/CamposLaborales'
import CamposPersona, { CamposDireccion } from '../components/clientes/CamposPersona'
import { BloqueFamiliar, ListaReferencias } from '../components/clientes/Contactos'
import EditorTelefonos from '../components/clientes/EditorTelefonos'
import SeccionGarante from '../components/clientes/SeccionGarante'
import '../styles/formularios.css'


// Definidos fuera del componente para que ModalConsulta reciba referencias estables.
const cargarClientes = (filtros) => listarClientes({ ...filtros, rol: 'cliente' })
const COLUMNAS = [
  { clave: 'numeroDocumento', titulo: 'Documento' },
  { clave: 'nombreCompleto', titulo: 'Nombre' },
  { clave: 'telefono', titulo: 'Teléfono', render: (f) => f.telefono || '—' },
  { clave: 'clasificacion', titulo: 'Clasif.', render: (f) => f.clasificacion || '—' },
  { clave: 'estado', titulo: 'Estado', render: (f) => <Etiqueta estado={f.estado} /> },
]
const FILTROS = [
  {
    clave: 'estado',
    etiqueta: 'Estado',
    opciones: [
      { valor: '', etiqueta: 'Todos los estados' },
      { valor: 'Activo', etiqueta: 'Activos' },
      { valor: 'Inactivo', etiqueta: 'Inactivos' },
    ],
  },
]

// Pestañas del formulario (cada una agrupa secciones que se siguen numerando como en el enunciado).
const PESTANAS = [
  { id: 'general', titulo: 'Datos generales' },
  { id: 'contacto', titulo: 'Teléfonos y crédito' },
  { id: 'garante', titulo: 'Garante' },
  { id: 'laboral', titulo: 'Datos laborales' },
  { id: 'extras', titulo: 'Familiares y referencias' },
]

// A qué pestaña pertenece un campo con error, según el nombre de su clave.
function pestanaDeError(clave) {
  if (clave.startsWith('garante')) return 'garante'
  if (clave.startsWith('laboral')) return 'laboral'
  if (clave.startsWith('familiares') || clave.startsWith('referencias')) return 'extras'
  if (clave.startsWith('telefonos') || clave === 'limiteCredito' || clave === 'clasificacion') return 'contacto'
  return 'general'
}

const enfocarPrimerError = () =>
  setTimeout(() => {
    const campo = document.querySelector('[aria-invalid="true"]')
    campo?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    campo?.focus({ preventScroll: true })
  }, 60)

export default function Clientes() {
  const { datos: catalogos, cargando, error, recargar } = useApi(obtenerCatalogosClientes)

  if (cargando && !catalogos) return <Cargando texto="Cargando formulario…" />
  if (error) {
    return (
      <Alerta tipo="error" accion={<Boton variante="secundario" tamano="pequeno" onClick={recargar}>Reintentar</Boton>}>
        {error}
      </Alerta>
    )
  }
  return <FormularioClientes catalogos={catalogos} />
}

function FormularioClientes({ catalogos }) {
  const [form, setForm] = useState(clienteVacio)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState(null) // { tipo: 'error' | 'exito' | 'info', texto }
  const [guardando, setGuardando] = useState(false)
  const [cargandoFicha, setCargandoFicha] = useState(false)
  const [consultaAbierta, setConsultaAbierta] = useState(false)
  const [confirmarEstado, setConfirmarEstado] = useState(false)
  const [cambiandoEstado, setCambiandoEstado] = useState(false)
  const [pestana, setPestana] = useState('general')

  const esEdicion = Boolean(form.id)
  const tipoDocumento = catalogos.tiposDocumento.find((t) => t.id === Number(form.tipoDocumentoId))
  const cambiar = (parcial) => setForm((f) => ({ ...f, ...parcial }))

  // Al cambiar el límite se sugiere la clasificación que le corresponde (el usuario aún puede cambiarla).
  function cambiarLimite(e) {
    const limiteCredito = e.target.value
    const sugerida = clasificacionPara(limiteCredito)
    cambiar(sugerida ? { limiteCredito, clasificacion: sugerida } : { limiteCredito })
  }

  // Advertencia en vivo, antes de guardar: fuera de rango o clasificación que no corresponde al límite.
  const erroresCreditoVivo = erroresCredito(form.limiteCredito, form.clasificacion)
  const avisoCredito = erroresCreditoVivo.limiteCredito ?? erroresCreditoVivo.clasificacion ?? null

  // Muestra la pestaña con el primer error (en el orden de las pestañas) y enfoca ese campo.
  function mostrarErrores(lista) {
    const conError = new Set(Object.keys(lista).map(pestanaDeError))
    const primera = PESTANAS.find((p) => conError.has(p.id))
    if (primera) setPestana(primera.id)
    enfocarPrimerError()
  }

  function nuevo() {
    setPestana('general')
    setForm(clienteVacio())
    setErrores({})
    setMensaje(null)
  }

  async function seleccionarCliente(fila) {
    setConsultaAbierta(false)
    setMensaje(null)
    setErrores({})
    setPestana('general')
    setCargandoFicha(true)
    try {
      setForm(clienteDeDetalle(await obtenerCliente(fila.id)))
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    } finally {
      setCargandoFicha(false)
    }
  }

  // Búsqueda inversa: si el documento del cliente ya existe...
  async function alSalirDelDocumento() {
    if (esEdicion || validarDocumento(form.numeroDocumento, tipoDocumento)) return
    try {
      const r = await buscarPersona(form.numeroDocumento)
      if (!r.existe) return
      if (r.esCliente) {
        setErrores((e) => ({
          ...e,
          numeroDocumento: 'Este documento ya pertenece a un cliente. Use "Modificar" para editarlo.',
        }))
      } else {
        // ...y es una persona registrada como garante, se cargan sus datos para no digitarlos dos veces.
        setForm((f) => ({ ...f, ...personaDeDetalle(r.persona), id: null }))
        setErrores({})
        setMensaje({
          tipo: 'info',
          texto: 'Esta persona ya está registrada como garante. Sus datos se cargaron; complete los datos de cliente.',
        })
      }
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    }
  }

  async function guardar() {
    setMensaje(null)
    const nuevos = validarCliente(form, catalogos)
    setErrores(nuevos)
    if (Object.keys(nuevos).length) {
      setMensaje({ tipo: 'error', texto: 'Revise los campos marcados en rojo (las pestañas con un punto rojo).' })
      mostrarErrores(nuevos)
      return
    }

    setGuardando(true)
    try {
      const guardado = await guardarCliente(form.id, aPayload(form))
      setForm(clienteDeDetalle(guardado))
      setMensaje({ tipo: 'exito', texto: esEdicion ? 'Cliente actualizado correctamente.' : 'Cliente registrado correctamente.' })
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (e) {
      setErrores(e.errores || {})
      setMensaje({ tipo: 'error', texto: e.message })
      if (Object.keys(e.errores || {}).length) mostrarErrores(e.errores)
    } finally {
      setGuardando(false)
    }
  }

  async function alternarEstado() {
    const nuevoEstado = form.estado === 'Activo' ? 'Inactivo' : 'Activo'
    setCambiandoEstado(true)
    try {
      setForm(clienteDeDetalle(await cambiarEstadoCliente(form.id, nuevoEstado)))
      setMensaje({ tipo: 'exito', texto: `Cliente ${nuevoEstado === 'Activo' ? 'activado' : 'inactivado'} correctamente.` })
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    } finally {
      setCambiandoEstado(false)
      setConfirmarEstado(false)
    }
  }

  const inactivando = form.estado === 'Activo'

  return (
    <div>
      <div className="barra-acciones">
        <div className="barra-acciones__grupo">
          <Boton variante="secundario" icono={Plus} onClick={nuevo}>Nuevo</Boton>
          <Boton variante="secundario" icono={Search} onClick={() => setConsultaAbierta(true)}>Modificar</Boton>
          <Boton variante="fantasma" icono={RotateCcw} onClick={nuevo}>Limpiar</Boton>
        </div>
        <div className="barra-acciones__grupo">
          {esEdicion && (
            <Boton variante={inactivando ? 'peligro' : 'secundario'} icono={Power} onClick={() => setConfirmarEstado(true)}>
              {inactivando ? 'Inactivar' : 'Activar'}
            </Boton>
          )}
          <Boton icono={Save} cargando={guardando} onClick={guardar}>Guardar</Boton>
        </div>
      </div>

      {mensaje && <div style={{ marginBottom: 16 }}><Alerta tipo={mensaje.tipo}>{mensaje.texto}</Alerta></div>}

      {cargandoFicha ? (
        <Cargando texto="Cargando cliente…" />
      ) : (
        <>
          <div className="cabecera-registro">
            <h2>{esEdicion ? `${form.nombres} ${form.apellidos}` : 'Nuevo cliente'}</h2>
            {esEdicion && <Etiqueta estado={form.estado} />}
          </div>

          <Pestanas
            pestanas={PESTANAS.map((p) => ({ ...p, conError: Object.keys(errores).some((k) => pestanaDeError(k) === p.id) }))}
            activa={pestana}
            onCambio={setPestana}
          />

          <PanelPestana id="general" activa={pestana}>
          <SeccionForm titulo="1. Datos generales">
            <CamposPersona
              prefijoId="cliente"
              valor={form}
              onCambio={cambiar}
              errores={errores}
              catalogos={catalogos}
              onDocumentoSalir={alSalirDelDocumento}
            />
          </SeccionForm>

          <SeccionForm titulo="2. Dirección" descripcion="Dónde vive y cómo llegar.">
            <CamposDireccion prefijoId="cliente" valor={form} onCambio={cambiar} errores={errores} />
          </SeccionForm>

          </PanelPestana>

          <PanelPestana id="contacto" activa={pestana}>
          <SeccionForm titulo="3. Teléfonos" descripcion="Uno o varios; marque uno como principal.">
            <EditorTelefonos
              prefijoId="cliente"
              telefonos={form.telefonos}
              onCambio={(telefonos) => cambiar({ telefonos })}
              errores={errores}
              tipos={catalogos.tiposTelefono}
            />
          </SeccionForm>

          <SeccionForm titulo="4. Datos crediticios">
            <div className="rejilla">
              <Campo
                id="cliente-limiteCredito"
                etiqueta="Límite de crédito (RD$)"
                type="number"
                min={LIMITE_MINIMO}
                max={LIMITE_MAXIMO}
                step="0.01"
                value={form.limiteCredito}
                error={errores.limiteCredito}
                onChange={cambiarLimite}
              />
              <CampoSelect
                id="cliente-clasificacion"
                etiqueta="Clasificación"
                value={form.clasificacion}
                error={errores.clasificacion}
                opciones={catalogos.clasificaciones.map((c) => ({ valor: c, etiqueta: etiquetaClasificacion(c) }))}
                onChange={(e) => cambiar({ clasificacion: e.target.value })}
              />
            </div>
            {avisoCredito && <Alerta tipo="error">{avisoCredito}</Alerta>}
          </SeccionForm>

          </PanelPestana>

          <PanelPestana id="garante" activa={pestana}>
          <SeccionGarante
            garante={form.garante}
            onCambio={(garante) => cambiar({ garante })}
            errores={errores}
            catalogos={catalogos}
            documentoCliente={form.numeroDocumento}
          />

          </PanelPestana>

          <PanelPestana id="extras" activa={pestana}>
          <SeccionForm titulo="6. Familiares (opcional)" descripcion="Un familiar cercano y uno que no viva con el cliente.">
            <BloqueFamiliar
              clave="cercano"
              titulo="Familiar cercano"
              valor={form.familiares.cercano}
              errores={errores}
              onCambio={(p) => cambiar({ familiares: { ...form.familiares, cercano: { ...form.familiares.cercano, ...p } } })}
            />
            <BloqueFamiliar
              clave="noConvive"
              titulo="Familiar que no vive con el cliente"
              valor={form.familiares.noConvive}
              errores={errores}
              onCambio={(p) => cambiar({ familiares: { ...form.familiares, noConvive: { ...form.familiares.noConvive, ...p } } })}
            />
          </SeccionForm>


          <SeccionForm titulo="8. Referencias personales (opcional)" descripcion="Hasta tres referencias, cada una con nombre y teléfono.">
            <ListaReferencias referencias={form.referencias} onCambio={(referencias) => cambiar({ referencias })} errores={errores} />
          </SeccionForm>
          </PanelPestana>

          <PanelPestana id="laboral" activa={pestana}>
          <SeccionForm titulo="7. Datos laborales">
            <CamposLaborales
              prefijoId="cliente"
              valor={form.laboral}
              onCambio={(p) => cambiar({ laboral: { ...form.laboral, ...p } })}
              errores={errores}
            />
          </SeccionForm>

          </PanelPestana>
        </>
      )}

      <ModalConsulta
        abierto={consultaAbierta}
        titulo="Consultar clientes"
        columnas={COLUMNAS}
        filtros={FILTROS}
        cargar={cargarClientes}
        onSeleccionar={seleccionarCliente}
        onCerrar={() => setConsultaAbierta(false)}
      />

      <ConfirmDialog
        abierto={confirmarEstado}
        titulo={inactivando ? 'Inactivar cliente' : 'Activar cliente'}
        mensaje={
          inactivando
            ? `${form.nombres} ${form.apellidos} quedará inactivo y no podrá recibir nuevos préstamos. El historial se conserva.`
            : `${form.nombres} ${form.apellidos} volverá a estar activo.`
        }
        textoConfirmar={inactivando ? 'Inactivar' : 'Activar'}
        peligro={inactivando}
        cargando={cambiandoEstado}
        onConfirmar={alternarEstado}
        onCancelar={() => setConfirmarEstado(false)}
      />
    </div>
  )
}
