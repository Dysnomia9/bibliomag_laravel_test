export type Staff = {
  id: number
  email: string
  nombre: string
  rol: string
}

export type Usuario = {
  id: number
  rut: string
  nombre: string
  apellido: string
  email: string | null
  tipo: 'estudiante' | 'docente' | 'funcionario'
  carrera: string | null
  anio_ingreso: number | null
  sexo: string | null
  activo: boolean
  qr_code: string | null
}

export type Entrada = {
  id: number
  usuario_id: number | null
  rut_externo: string | null
  nombre_externo: string | null
  fecha_hora_entrada: string
  fecha_hora_salida: string | null
  via: 'manual' | 'qr'
  usuario?: Pick<Usuario, 'id' | 'nombre' | 'apellido' | 'rut'>
}

export type Prestamo = {
  id: number
  usuario_id: number
  libro_titulo: string
  tipo_item: 'libro' | 'audifonos' | 'notebook'
  fecha_prestamo: string
  fecha_devolucion: string | null
  fecha_devolucion_real: string | null
  estado: 'activo' | 'atrasado' | 'devuelto'
  usuario?: Pick<Usuario, 'id' | 'nombre' | 'apellido' | 'rut'>
}

export type ResumenDashboard = {
  usuariosActivos: number
  entradasHoy: number
  personasEnSala: number
  prestamosActivos: number
  prestamosAtrasados: number
  ultimasEntradas: Entrada[]
  ultimosPrestamos: Prestamo[]
}

export type Libro = {
  id: number
  codigo_barras: string
  titulo: string
  autor: string | null
  categoria: string | null
  disponible: boolean
}

export type EstadoPortal = {
  usuario: Usuario
  personasEnSala: number
  capacidad: number
}

export type ReservaLibro = {
  id: number
  usuario_id: number
  libro_id: number
  fecha_reserva: string
  fecha_retiro: string
  estado: 'pendiente' | 'retirado' | 'cancelado'
  libro?: Libro
}

export type Sala = {
  id: number
  nombre: string
  capacidad: number
  piso: string
}

export type Reserva = {
  id: number
  sala_id: number
  usuario_id: number | null
  rut_usuario: string
  cantidad_personas: number
  ruts: string[]
  personas?: { rut: string; nombre: string | null }[]
  fecha: string
  hora_inicio: number
  hora_fin: number
  estado: string
}

export type SerieItem = {
  label: string
  value: number
}

export type ReporteResumen = {
  total: number
  promedioPorPeriodo: number
  categoriaMasFrecuente: string
  serie: SerieItem[]
  porCarrera: SerieItem[]
  porSexo: SerieItem[]
  porAnioIngreso: SerieItem[]
  porTipoUsuario: SerieItem[]
  porHora: SerieItem[]
}

export type ReporteOpciones = {
  carreras: string[]
  aniosIngreso: number[]
  sexos: string[]
  tiposUsuario: string[]
}

export type Periodo = 'dia' | 'semana' | 'mes' | 'semestre' | 'anio'
export type ReporteTab = 'prestamos' | 'ingresos' | 'logias'

export type CodigoAcceso = {
  id: number
  codigo: string
  generado_por: number | null
  created_at: string
  updated_at: string
}
