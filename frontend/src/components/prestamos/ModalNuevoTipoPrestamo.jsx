import { useState } from 'react'
import { Save } from 'lucide-react'
import { guardarTipoPrestamo } from '../../services/tipoPrestamoService'
import { aPayloadTipoPrestamo, tipoPrestamoVacio, validarTipoPrestamo } from '../../utils/validacionesTipoPrestamo'
import Alerta from '../Alerta'
import Boton from '../Boton'
import Modal from '../Modal'
import CamposTipoPrestamo from './CamposTipoPrestamo'

// Crea un tipo de préstamo sin salir de la apertura. Al guardar devuelve el tipo creado con onCreado.
export default function ModalNuevoTipoPrestamo(props) {
  // El contenido solo existe con el modal abierto: así el formulario siempre arranca vacío.
  if (!props.abierto) return null
  return <Contenido {...props} />
}

function Contenido({ onCreado, onCerrar }) {
  const [tipo, setTipo] = useState(tipoPrestamoVacio)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')
  const [guardando, setGuardando] = useState(false)

  async function guardar() {
    setMensaje('')
    const nuevos = validarTipoPrestamo(tipo)
    setErrores(nuevos)
    if (Object.keys(nuevos).length) return

    setGuardando(true)
    try {
      onCreado(await guardarTipoPrestamo(null, aPayloadTipoPrestamo(tipo)))
    } catch (e) {
      setErrores(e.errores || {})
      setMensaje(e.message)
      setGuardando(false)
    }
  }

  return (
    <Modal
      abierto
      titulo="Nuevo tipo de préstamo"
      ancho="md"
      onCerrar={onCerrar}
      pie={
        <>
          <Boton variante="secundario" onClick={onCerrar} disabled={guardando}>
            Cancelar
          </Boton>
          <Boton icono={Save} cargando={guardando} onClick={guardar}>
            Guardar y usar
          </Boton>
        </>
      }
    >
      {mensaje && <div style={{ marginBottom: 16 }}><Alerta tipo="error">{mensaje}</Alerta></div>}
      <CamposTipoPrestamo
        prefijoId="modal-tipo"
        valor={tipo}
        onCambio={(p) => setTipo((t) => ({ ...t, ...p }))}
        errores={errores}
      />
    </Modal>
  )
}
