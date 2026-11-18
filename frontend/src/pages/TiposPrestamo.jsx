import { useState } from 'react'
import { Plus, Power, RotateCcw, Save, Search } from 'lucide-react'
import {
  cambiarEstadoTipoPrestamo, guardarTipoPrestamo, listarTiposPrestamo, obtenerTipoPrestamo,
} from '../services/tipoPrestamoService'
import { aPayloadTipoPrestamo, tipoPrestamoVacio, validarTipoPrestamo } from '../utils/validacionesTipoPrestamo'
import Alerta from '../components/Alerta'
import Boton from '../components/Boton'
import ConfirmDialog from '../components/ConfirmDialog'
import Etiqueta from '../components/Etiqueta'
import ModalConsulta from '../components/ModalConsulta'
import SeccionForm from '../components/SeccionForm'
import CamposTipoPrestamo from '../components/prestamos/CamposTipoPrestamo'
import '../styles/formularios.css'

// Fuera del componente para que ModalConsulta reciba referencias estables.
const COLUMNAS = [
  { clave: 'nombre', titulo: 'Nombre' },
  { clave: 'descripcion', titulo: 'Descripción', render: (f) => f.descripcion || '—' },
  { clave: 'prestamos', titulo: 'Préstamos' },
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

export default function TiposPrestamo() {
  const [tipo, setTipo] = useState(tipoPrestamoVacio)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState(null) // { tipo: 'error' | 'exito', texto }
  const [guardando, setGuardando] = useState(false)
  const [consultaAbierta, setConsultaAbierta] = useState(false)
  const [confirmarEstado, setConfirmarEstado] = useState(false)
  const [cambiandoEstado, setCambiandoEstado] = useState(false)

  const esEdicion = Boolean(tipo.id)
  const inactivando = tipo.estado === 'Activo'

  function nuevo() {
    setTipo(tipoPrestamoVacio())
    setErrores({})
    setMensaje(null)
  }

  async function seleccionar(fila) {
    setConsultaAbierta(false)
    setErrores({})
    setMensaje(null)
    try {
      setTipo(await obtenerTipoPrestamo(fila.id))
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    }
  }

  async function guardar() {
    setMensaje(null)
    const nuevos = validarTipoPrestamo(tipo)
    setErrores(nuevos)
    if (Object.keys(nuevos).length) {
      setMensaje({ tipo: 'error', texto: 'Revise los campos marcados en rojo.' })
      return
    }
    setGuardando(true)
    try {
      setTipo(await guardarTipoPrestamo(tipo.id, aPayloadTipoPrestamo(tipo)))
      setMensaje({ tipo: 'exito', texto: esEdicion ? 'Tipo de préstamo actualizado.' : 'Tipo de préstamo registrado.' })
    } catch (e) {
      setErrores(e.errores || {})
      setMensaje({ tipo: 'error', texto: e.message })
    } finally {
      setGuardando(false)
    }
  }

  async function alternarEstado() {
    setCambiandoEstado(true)
    try {
      setTipo(await cambiarEstadoTipoPrestamo(tipo.id, inactivando ? 'Inactivo' : 'Activo'))
      setMensaje({ tipo: 'exito', texto: `Tipo de préstamo ${inactivando ? 'inactivado' : 'activado'}.` })
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    } finally {
      setCambiandoEstado(false)
      setConfirmarEstado(false)
    }
  }

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

      <div className="cabecera-registro">
        <h2>{esEdicion ? tipo.nombre : 'Nuevo tipo de préstamo'}</h2>
        {esEdicion && <Etiqueta estado={tipo.estado} />}
      </div>

      <SeccionForm
        titulo="Datos del tipo de préstamo"
        descripcion="Sirve para clasificar los préstamos al aperturarlos. Un tipo inactivo no se puede elegir en préstamos nuevos."
      >
        <CamposTipoPrestamo
          prefijoId="tipo"
          valor={tipo}
          onCambio={(p) => setTipo((t) => ({ ...t, ...p }))}
          errores={errores}
        />
        {esEdicion && <p className="nota">Préstamos registrados con este tipo: {tipo.prestamos}</p>}
      </SeccionForm>

      <ModalConsulta
        abierto={consultaAbierta}
        titulo="Consultar tipos de préstamo"
        placeholder="Buscar por nombre o descripción…"
        columnas={COLUMNAS}
        filtros={FILTROS}
        cargar={listarTiposPrestamo}
        onSeleccionar={seleccionar}
        onCerrar={() => setConsultaAbierta(false)}
      />

      <ConfirmDialog
        abierto={confirmarEstado}
        titulo={inactivando ? 'Inactivar tipo de préstamo' : 'Activar tipo de préstamo'}
        mensaje={
          inactivando
            ? `"${tipo.nombre}" dejará de estar disponible para préstamos nuevos. Los préstamos existentes no cambian.`
            : `"${tipo.nombre}" volverá a estar disponible para préstamos nuevos.`
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
