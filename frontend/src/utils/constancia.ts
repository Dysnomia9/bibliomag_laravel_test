import { jsPDF } from 'jspdf'
import type { ConfiguracionInstitucional, Usuario } from '@/types'

/**
 * Constancia de No Multa — mismo patrón que el QR (librería de terceros dibujando
 * sobre un canvas/documento) y el Excel (descarga directa), pero para PDF. No hay
 * logo institucional como imagen en el repo todavía (solo favicon.png), así que el
 * encabezado es de texto — no es una réplica pixel-perfect del documento con timbre y
 * firma manuscrita, pero sí el mismo contenido y estructura formal.
 */
export function generarConstanciaNoMulta(usuario: Usuario, configuracion: ConfiguracionInstitucional) {
  const doc = new jsPDF({ unit: 'mm', format: 'letter' })
  const anchoPagina = doc.internal.pageSize.getWidth()
  const margen = 25

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(18)
  doc.setTextColor(30, 30, 30)
  doc.text('UMAG', margen, 25)

  doc.setFontSize(11)
  doc.setFont('helvetica', 'normal')
  doc.text('Universidad de Magallanes', margen, 32)

  doc.setFont('helvetica', 'bold')
  doc.text('Unidad de Gestión de Recursos Educativos', anchoPagina - margen, 25, { align: 'right' })

  doc.setDrawColor(180, 180, 180)
  doc.line(margen, 38, anchoPagina - margen, 38)

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(14)
  doc.text('CONSTANCIA', anchoPagina / 2, 58, { align: 'center' })

  const nombreCompleto = `${usuario.nombre} ${usuario.apellido}`.toUpperCase()
  const detalleUsuario = usuario.carrera
    ? `del programa de ${usuario.carrera}`
    : `de tipo ${usuario.tipo}`

  const parrafo =
    `${configuracion.jefe_unidad_nombre}, ${configuracion.jefe_unidad_cargo}, deja constancia que ` +
    `Don/Doña ${nombreCompleto}, RUT ${usuario.rut}, ${detalleUsuario}, no registra deuda pendiente con la Biblioteca.`

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(11)
  const lineas = doc.splitTextToSize(parrafo, anchoPagina - margen * 2)
  doc.text(lineas, margen, 78)

  const notaFinal = 'Se extiende el presente documento a solicitud del interesado, para los fines que estime pertinentes.'
  const lineasNota = doc.splitTextToSize(notaFinal, anchoPagina - margen * 2)
  doc.text(lineasNota, margen, 78 + lineas.length * 6 + 10)

  const fecha = new Date().toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' })
  doc.text(`Punta Arenas, ${fecha}.`, anchoPagina - margen, 150, { align: 'right' })

  doc.line(margen + 40, 190, anchoPagina - margen - 40, 190)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(10)
  doc.text(configuracion.jefe_unidad_nombre, anchoPagina / 2, 196, { align: 'center' })
  doc.setFont('helvetica', 'normal')
  doc.text(configuracion.jefe_unidad_cargo, anchoPagina / 2, 201, { align: 'center' })

  const nombreArchivo = `constancia-no-multa-${usuario.rut.replace(/\./g, '')}.pdf`
  doc.save(nombreArchivo)
}
