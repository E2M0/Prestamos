import Boton from './Boton'
import Modal from './Modal'

// Confirmación antes de una acción importante (por ejemplo, inactivar un cliente).
export default function ConfirmDialog({
  abierto, titulo, mensaje, textoConfirmar = 'Confirmar', peligro = false, cargando = false, onConfirmar, onCancelar,
}) {
  return (
    <Modal
      abierto={abierto}
      titulo={titulo}
      ancho="sm"
      onCerrar={onCancelar}
      pie={
        <>
          <Boton variante="secundario" onClick={onCancelar} disabled={cargando}>
            Cancelar
          </Boton>
          <Boton variante={peligro ? 'peligro' : 'primario'} onClick={onConfirmar} cargando={cargando}>
            {textoConfirmar}
          </Boton>
        </>
      }
    >
      <p>{mensaje}</p>
    </Modal>
  )
}
