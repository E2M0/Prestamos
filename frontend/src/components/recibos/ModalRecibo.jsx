import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { Copy, Mail, Printer, Share2 } from 'lucide-react'
import Alerta from '../Alerta'
import Boton from '../Boton'
import CampoSelect from '../CampoSelect'
import Campo from '../Campo'
import Modal from '../Modal'
import { cambiarFormatoRecibo, enviarReciboPorCorreo } from '../../services/reciboService'
import { formatoMoneda } from '../../utils/formato'
import { FORMATOS, NOMBRE_TIPO } from '../../utils/validacionesRecibo'
import ReciboImprimible from './ReciboImprimible'
import '../../styles/recibos.css'

const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function textoParaCompartir(r) {
  return [
    `${r.empresa} · Recibo ${r.numero}`,
    `Cliente: ${r.cliente}`,
    `Préstamo: ${r.prestamoNumero}`,
    `${NOMBRE_TIPO[r.tipoPago]}: ${formatoMoneda(r.monto)}`,
    `Balance posterior: ${formatoMoneda(r.balancePosterior)}`,
  ].join('\n')
}

// Muestra el recibo y permite imprimirlo (A4 o POS 80 mm), compartirlo y enviarlo por correo.
export default function ModalRecibo({ abierto, recibo: inicial, onCerrar }) {
  if (!abierto || !inicial) return null
  return <Contenido inicial={inicial} onCerrar={onCerrar} />
}

function Contenido({ inicial, onCerrar }) {
  const [recibo, setRecibo] = useState(inicial)
  const [correo, setCorreo] = useState(inicial.correo || '')
  const [enviando, setEnviando] = useState(false)
  const [mensaje, setMensaje] = useState(null)

  // Zona de impresión: se monta directamente en <body> para que el CSS de impresión oculte el resto de la app.
  const [zona] = useState(() => {
    const div = document.createElement('div')
    div.className = 'zona-impresion'
    return div
  })
  useEffect(() => {
    document.body.appendChild(zona)
    return () => zona.remove()
  }, [zona])

  async function cambiarFormato(formato) {
    setRecibo((r) => ({ ...r, formato })) // cambio inmediato en pantalla
    try {
      await cambiarFormatoRecibo(recibo.id, formato)
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    }
  }

  function imprimir() {
    // @page cambia según el formato: hoja A4 o rollo de 80 mm de ancho.
    let estilo = document.getElementById('estilo-pagina-recibo')
    if (!estilo) {
      estilo = document.createElement('style')
      estilo.id = 'estilo-pagina-recibo'
      document.head.appendChild(estilo)
    }
    estilo.textContent = recibo.formato === 'POS80' ? '@page { size: 80mm auto; margin: 3mm; }' : '@page { size: A4; margin: 14mm; }'
    window.print()
  }

  async function compartir() {
    const texto = textoParaCompartir(recibo)
    if (navigator.share) {
      try {
        await navigator.share({ title: `Recibo ${recibo.numero}`, text: texto })
        return
      } catch (e) {
        if (e.name === 'AbortError') return
      }
    }
    window.open(`https://wa.me/?text=${encodeURIComponent(texto)}`, '_blank', 'noopener')
  }

  async function copiar() {
    try {
      await navigator.clipboard.writeText(textoParaCompartir(recibo))
      setMensaje({ tipo: 'exito', texto: 'Resumen copiado al portapapeles.' })
    } catch {
      setMensaje({ tipo: 'error', texto: 'No se pudo copiar el resumen.' })
    }
  }

  async function enviar() {
    if (!CORREO_VALIDO.test(correo.trim())) {
      setMensaje({ tipo: 'error', texto: 'Ingrese un correo válido.' })
      return
    }
    setEnviando(true)
    setMensaje(null)
    try {
      await enviarReciboPorCorreo(recibo.id, correo.trim())
      setMensaje({ tipo: 'exito', texto: `Recibo enviado a ${correo.trim()}.` })
    } catch (e) {
      setMensaje({ tipo: 'error', texto: e.message })
    } finally {
      setEnviando(false)
    }
  }

  return (
    <>
      <Modal
        abierto
        titulo={`Recibo ${recibo.numero}`}
        ancho="lg"
        onCerrar={onCerrar}
        pie={
          <>
            <Boton variante="secundario" onClick={onCerrar}>Cerrar</Boton>
            <Boton icono={Printer} onClick={imprimir}>Imprimir</Boton>
          </>
        }
      >
        {mensaje && <div style={{ marginBottom: 12 }}><Alerta tipo={mensaje.tipo}>{mensaje.texto}</Alerta></div>}

        <div className="recibo-acciones">
          <CampoSelect
            id="formato-recibo"
            etiqueta="Formato de impresión"
            opciones={FORMATOS}
            placeholder="Formato…"
            value={recibo.formato}
            onChange={(e) => e.target.value && cambiarFormato(e.target.value)}
          />
          <div className="recibo-acciones__botones">
            <Boton variante="secundario" icono={Share2} onClick={compartir}>Compartir</Boton>
            <Boton variante="fantasma" icono={Copy} onClick={copiar}>Copiar resumen</Boton>
          </div>
        </div>

        <div className="recibo-correo">
          <Campo
            id="correo-recibo"
            etiqueta="Enviar por correo"
            icono={Mail}
            type="email"
            value={correo}
            onChange={(e) => setCorreo(e.target.value)}
            placeholder="correo@ejemplo.com"
          />
          <Boton variante="secundario" icono={Mail} cargando={enviando} onClick={enviar}>Enviar</Boton>
        </div>

        <div className="recibo-vista">
          <ReciboImprimible recibo={recibo} formato={recibo.formato} />
        </div>
      </Modal>
      {createPortal(<ReciboImprimible recibo={recibo} formato={recibo.formato} />, zona)}
    </>
  )
}
