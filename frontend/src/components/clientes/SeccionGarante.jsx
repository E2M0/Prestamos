import { useState } from 'react'
import { Pencil, Search, X } from 'lucide-react'
import { buscarPersona } from '../../services/clienteService'
import { garanteVacio, personaDeDetalle, subErrores } from '../../utils/clienteForm'
import { aplicarMascaraDocumento, soloAlfanumerico, validarDocumento } from '../../utils/documento'
import Alerta from '../Alerta'
import Boton from '../Boton'
import Campo from '../Campo'
import CampoSelect from '../CampoSelect'
import SeccionForm from '../SeccionForm'
import CamposLaborales from './CamposLaborales'
import CamposPersona, { CamposDireccion } from './CamposPersona'
import EditorTelefonos from './EditorTelefonos'

// Sección del garante. Al digitar su documento:
//  - si ya existe en el sistema, se cargan sus datos en modo solo lectura (con opción de editarlos);
//  - si no existe, se completan manualmente y se guarda como una persona nueva (sin duplicar).
// Además se valida que no sea el mismo documento del cliente.
export default function SeccionGarante({ garante, onCambio, errores, catalogos, documentoCliente }) {
  const [buscando, setBuscando] = useState(false)
  const [aviso, setAviso] = useState(null) // { tipo, texto }
  const rel = subErrores(errores, 'garante.')
  const tipo = catalogos.tiposDocumento.find((t) => t.id === Number(garante.tipoDocumentoId))
  const bloqueado = garante.existente && !garante.actualizar

  // Cambios en los campos del garante. Si cambia el documento de uno ya cargado, se descarta esa carga.
  function cambiar(parcial) {
    const cambiaDocumento = 'numeroDocumento' in parcial || 'tipoDocumentoId' in parcial
    if (cambiaDocumento && garante.existente) {
      setAviso(null)
      onCambio({
        ...garanteVacio(),
        tipoDocumentoId: parcial.tipoDocumentoId ?? garante.tipoDocumentoId,
        numeroDocumento: parcial.numeroDocumento ?? garante.numeroDocumento,
      })
      return
    }
    onCambio({ ...garante, ...parcial, ...(cambiaDocumento ? { buscado: false } : {}) })
  }

  async function buscar() {
    const mensaje = validarDocumento(garante.numeroDocumento, tipo)
    if (mensaje) {
      setAviso({ tipo: 'error', texto: mensaje })
      return
    }
    if (soloAlfanumerico(garante.numeroDocumento) === soloAlfanumerico(documentoCliente)) {
      setAviso({ tipo: 'error', texto: 'El garante no puede tener el mismo documento que el cliente.' })
      return
    }

    setBuscando(true)
    setAviso(null)
    try {
      const r = await buscarPersona(garante.numeroDocumento)
      if (!r.existe) {
        onCambio({ ...garante, existente: false, actualizar: false, buscado: true })
        setAviso({ tipo: 'info', texto: 'Documento no registrado. Complete los datos del garante.' })
      } else if (r.estado !== 'Activo') {
        setAviso({ tipo: 'error', texto: 'Esta persona está inactiva y no puede ser garante.' })
      } else {
        onCambio({ ...personaDeDetalle(r.persona), existente: true, actualizar: false, buscado: true })
        setAviso({
          tipo: 'info',
          texto: r.esCliente
            ? 'Esta persona ya está registrada como cliente. Sus datos se cargaron (solo lectura).'
            : 'Esta persona ya está registrada. Sus datos se cargaron (solo lectura).',
        })
      }
    } catch (e) {
      setAviso({ tipo: 'error', texto: e.message })
    } finally {
      setBuscando(false)
    }
  }

  return (
    <SeccionForm titulo="5. Garante" descripcion="Obligatorio. Busque por documento: si ya existe se cargan sus datos automáticamente.">
      <div className="garante__busqueda">
        <CampoSelect
          id="garante-tipoDocumentoId"
          etiqueta="Tipo de documento"
          value={garante.tipoDocumentoId}
          error={rel.tipoDocumentoId}
          opciones={catalogos.tiposDocumento.map((t) => ({ valor: t.id, etiqueta: t.nombre }))}
          onChange={(e) =>
            cambiar({ tipoDocumentoId: e.target.value ? Number(e.target.value) : '', numeroDocumento: '' })
          }
        />
        <Campo
          id="garante-numeroDocumento"
          etiqueta="Número de documento del garante"
          value={garante.numeroDocumento}
          placeholder={tipo?.mascara ?? 'Seleccione primero el tipo'}
          maxLength={tipo?.longitud ?? 20}
          error={rel.numeroDocumento}
          onChange={(e) => cambiar({ numeroDocumento: aplicarMascaraDocumento(e.target.value, tipo?.nombre) })}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              buscar()
            }
          }}
          onBlur={() => {
            if (!garante.buscado && !validarDocumento(garante.numeroDocumento, tipo)) buscar()
          }}
        />
        <Boton variante="secundario" icono={Search} cargando={buscando} onClick={buscar}>
          Buscar
        </Boton>
      </div>

      {aviso && <Alerta tipo={aviso.tipo}>{aviso.texto}</Alerta>}

      {garante.buscado && (
        <>
          {garante.existente && (
            <div>
              {bloqueado ? (
                <Boton variante="secundario" tamano="pequeno" icono={Pencil} onClick={() => onCambio({ ...garante, actualizar: true })}>
                  Editar datos del garante
                </Boton>
              ) : (
                <>
                  <Alerta tipo="info">Al guardar se actualizará el registro de esta persona con los datos modificados.</Alerta>
                  <div style={{ marginTop: 8 }}>
                    <Boton variante="secundario" tamano="pequeno" icono={X} onClick={buscar}>
                      Cancelar edición
                    </Boton>
                  </div>
                </>
              )}
            </div>
          )}

          <CamposPersona
            prefijoId="garante"
            valor={garante}
            onCambio={cambiar}
            errores={rel}
            catalogos={catalogos}
            soloLectura={bloqueado}
            ocultarDocumento
          />
          <CamposDireccion prefijoId="garante" valor={garante} onCambio={cambiar} errores={rel} soloLectura={bloqueado} />

          <p className="subtitulo">Teléfonos del garante</p>
          <EditorTelefonos
            prefijoId="garante"
            telefonos={garante.telefonos}
            onCambio={(telefonos) => cambiar({ telefonos })}
            errores={rel}
            tipos={catalogos.tiposTelefono}
            soloLectura={bloqueado}
          />

          <p className="subtitulo">Datos laborales del garante</p>
          <CamposLaborales
            prefijoId="garante"
            valor={garante.laboral}
            onCambio={(parcial) => cambiar({ laboral: { ...garante.laboral, ...parcial } })}
            errores={rel}
            soloLectura={bloqueado}
          />
        </>
      )}
    </SeccionForm>
  )
}
