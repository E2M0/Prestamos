import { useEffect, useState } from 'react'
import { Search } from 'lucide-react'
import useDebounce from '../hooks/useDebounce'
import Alerta from './Alerta'
import Boton from './Boton'
import EstadoVacio from './EstadoVacio'
import Modal from './Modal'
import Tabla from './Tabla'

// Modal de consulta reutilizable (convención "modificar" del proyecto): buscador + lista.
// Se elige con doble clic sobre la fila, o con un clic y el botón "Seleccionar".
//  - cargar(filtros) -> Promise<array>. Debe ser una función estable (definida fuera del componente).
//  - columnas: [{ clave, titulo, render? }]
//  - filtros: [{ clave, etiqueta, opciones: [{ valor, etiqueta }] }]   (listas desplegables opcionales)
export default function ModalConsulta(props) {
  // El contenido solo existe mientras el modal está abierto: así el buscador y la selección se reinician.
  if (!props.abierto) return null
  return <ContenidoConsulta {...props} />
}

function ContenidoConsulta({
  titulo, columnas, cargar, filtros = [], onSeleccionar, onCerrar, placeholder = 'Buscar por documento o nombre…',
}) {
  const [texto, setTexto] = useState('')
  const [valoresFiltro, setValoresFiltro] = useState({})
  const [filas, setFilas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [seleccion, setSeleccion] = useState(null)
  const textoDiferido = useDebounce(texto, 300)

  useEffect(() => {
    let vigente = true
    setCargando(true)
    setError('')
    cargar({ buscar: textoDiferido, ...valoresFiltro })
      .then((datos) => {
        if (!vigente) return
        setFilas(datos)
        setSeleccion(null)
      })
      .catch((e) => vigente && setError(e.message))
      .finally(() => vigente && setCargando(false))
    return () => {
      vigente = false
    }
  }, [cargar, textoDiferido, valoresFiltro])

  return (
    <Modal
      abierto
      titulo={titulo}
      ancho="lg"
      onCerrar={onCerrar}
      pie={
        <>
          <Boton variante="secundario" onClick={onCerrar}>
            Cancelar
          </Boton>
          <Boton disabled={!seleccion} onClick={() => onSeleccionar(seleccion)}>
            Seleccionar
          </Boton>
        </>
      }
    >
      <div className="consulta__filtros">
        <div className="consulta__buscador">
          <Search size={16} />
          <input
            type="search"
            autoFocus
            value={texto}
            onChange={(e) => setTexto(e.target.value)}
            placeholder={placeholder}
            aria-label="Buscar"
          />
        </div>
        {filtros.map((f) => (
          <label key={f.clave} className="consulta__filtro">
            <span className="sr-only">{f.etiqueta}</span>
            <select
              aria-label={f.etiqueta}
              value={valoresFiltro[f.clave] ?? ''}
              onChange={(e) => setValoresFiltro((v) => ({ ...v, [f.clave]: e.target.value }))}
            >
              {f.opciones.map((o) => (
                <option key={o.valor} value={o.valor}>
                  {o.etiqueta}
                </option>
              ))}
            </select>
          </label>
        ))}
      </div>

      {error && <Alerta tipo="error">{error}</Alerta>}

      {!error && cargando && !filas.length && <div className="cargando">Buscando…</div>}

      {!error && !cargando && !filas.length && (
        <EstadoVacio compacto titulo="Sin resultados" texto="Pruebe con otro documento o nombre." />
      )}

      {!error && filas.length > 0 && (
        <Tabla
          columnas={columnas}
          filas={filas}
          seleccionada={seleccion?.id}
          onSeleccionar={setSeleccion}
          onDobleClic={onSeleccionar}
        />
      )}

      <p className="consulta__ayuda">Doble clic sobre una fila para elegirla, o un clic y el botón Seleccionar.</p>
    </Modal>
  )
}
