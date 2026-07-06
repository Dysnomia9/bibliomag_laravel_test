import * as XLSX from 'xlsx'

export type SeccionExcel = {
  titulo: string
  columnas: string[]
  filas: (string | number)[][]
}

const CARACTERES_INVALIDOS_HOJA = /[\\/?*[\]:]/g

function nombreHojaUnico(titulo: string, usados: Set<string>): string {
  const base = titulo.replace(CARACTERES_INVALIDOS_HOJA, ' ').trim().slice(0, 31) || 'Hoja'
  let candidato = base
  let i = 2
  while (usados.has(candidato)) {
    const sufijo = ` (${i})`
    candidato = base.slice(0, 31 - sufijo.length) + sufijo
    i++
  }
  usados.add(candidato)
  return candidato
}

/** Genera un libro .xlsx con una hoja por sección (nunca se usa esta librería para leer archivos externos). */
export function descargarExcel(nombreArchivo: string, secciones: SeccionExcel[]) {
  const workbook = XLSX.utils.book_new()
  const nombresUsados = new Set<string>()

  for (const seccion of secciones) {
    // Fila de título arriba de la tabla (fila 1), una fila en blanco de separación,
    // y recién ahí los encabezados de columna — así cada hoja se identifica sola
    // sin depender del nombre de la pestaña.
    const filas = [[seccion.titulo], [], seccion.columnas, ...seccion.filas]
    const worksheet = XLSX.utils.aoa_to_sheet(filas)

    const anchoColumnas = Math.max(1, seccion.columnas.length)
    worksheet['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: anchoColumnas - 1 } }]
    worksheet['!cols'] = seccion.columnas.map(() => ({ wch: 22 }))

    XLSX.utils.book_append_sheet(workbook, worksheet, nombreHojaUnico(seccion.titulo, nombresUsados))
  }

  XLSX.writeFile(workbook, nombreArchivo)
}
