import { useState } from 'react'
import { Plus, Power, RotateCcw, Save, Search } from 'lucide-react'
import useApi from '../hooks/useApi'
import { obtenerCatalogosClientes } from '../services/catalogoService'
import { buscarPersona, cambiarEstadoCliente, guardarCliente, listarClientes, obtenerCliente } from '../services/clienteService'
import { aPayload, clienteDeDetalle, clienteVacio, personaDeDetalle } from '../utils/clienteForm'
import { validarDocumento } from '../utils/documento'
import { validarCliente } from '../utils/validacionesCliente'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import Campo from '../components/Campo'
import CampoSelect from '../components/CampoSelect'
import Cargando from '../components/Cargando'
import ConfirmDialog from '../components/ConfirmDialog'
import Etiqueta from '../components/Etiqueta'
import ModalConsulta from '../components/ModalConsulta'
import SeccionForm from '../components/SeccionForm'
import CamposLaborales from '../components/clientes/CamposLaborales'
import CamposPersona, { CamposDireccion } from '../components/clientes/CamposPersona'
import { BloqueFamiliar, ListaReferencias } from '../components/clientes/Contactos'
import EditorTelefonos from '../components/clientes/EditorTelefonos'
import SeccionGarante from '../components/clientes/SeccionGarante'
import '../styles/formularios.css'

const ETIQUETA_CLASIFICACION = { A: 'A · Excelente', B: 'B · Bueno', C: 'C · Regular' }

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

  const esEdicion = Boolean(form.id)
  const tipoDocumento = catalogos.tiposDocumento.find((t) => t.id === Number(form.tipoDocumentoId))
  const cambiar = (parcial) => setForm((f) => ({ ...f, ...parcial }))

  function nuevo() {
    setForm(clienteVacio())
    setErrores({})
    setMensaje(null)
  }

  async function seleccionarCliente(fila) {
    setConsultaAbierta(false)
    setMensaje(null)
    setErrores({})
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
      setMensaje({ tipo: 'error', texto: 'Revise los campos marcados en rojo.' })
      enfocarPrimerError()
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
      if (Object.keys(e.errores || {}).length) enfocarPrimerError()
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
                min="0"
                step="0.01"
                value={form.limiteCredito}
                error={errores.limiteCredito}
                onChange={(e) => cambiar({ limiteCredito: e.target.value })}
              />
              <CampoSelect
                id="cliente-clasificacion"
                etiqueta="Clasificación"
                value={form.clasificacion}
                error={errores.clasificacion}
                opciones={catalogos.clasificaciones.map((c) => ({ valor: c, etiqueta: ETIQUETA_CLASIFICACION[c] ?? c }))}
                onChange={(e) => cambiar({ clasificacion: e.target.value })}
              />
            </div>
          </SeccionForm>

          <SeccionGarante
            garante={form.garante}
            onCambio={(garante) => cambiar({ garante })}
            errores={errores}
            catalogos={catalogos}
            documentoCliente={form.numeroDocumento}
          />

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

          <SeccionForm titulo="7. Datos laborales">
            <CamposLaborales
              prefijoId="cliente"
              valor={form.laboral}
              onCambio={(p) => cambiar({ laboral: { ...form.laboral, ...p } })}
              errores={errores}
            />
          </SeccionForm>

          <SeccionForm titulo="8. Referencias personales" descripcion="Tres referencias, cada una con nombre y teléfono.">
            <ListaReferencias referencias={form.referencias} onCambio={(referencias) => cambiar({ referencias })} errores={errores} />
          </SeccionForm>
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
