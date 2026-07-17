export type Staff = {
  id: number
  email: string
  nombre: string
  rol: 'admin' | 'staff'
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
  codigo_barras: string | null
  es_convenio: boolean
  usuario?: Pick<Usuario, 'id' | 'nombre' | 'apellido' | 'rut' | 'tipo'>
}

export type Prestamo = {
  id: number
  usuario_id: number
  libro_id: number | null
  libro_titulo: string
  tipo_item: 'libro' | 'audifonos' | 'notebook'
  fecha_prestamo: string
  fecha_devolucion: string | null
  fecha_devolucion_real: string | null
  estado: 'activo' | 'atrasado' | 'devuelto'
  prestado_por: string | null
  devuelto_por: string | null
  multa_monto: number | null
  multa_estado: 'pendiente' | 'pagada' | null
  multa_pagada_en: string | null
  multa_pagada_por: string | null
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

export type EstadoProcesoLibro =
  | 'inventario'
  | 'procesos_tecnicos'
  | 'por_colocar'
  | 'en_estante'
  | 'estanteria_auxiliar'
  | 'de_baja'

export type TipoMaterialLibro = 'libro' | 'revista' | 'tesis' | 'dvd' | 'otro'

export type Libro = {
  id: number
  codigo_barras: string
  titulo: string
  autor: string | null
  categoria: string | null
  disponible: boolean
  clasificacion: string | null
  coleccion: string | null
  editorial: string | null
  anio_publicacion: number | null
  ubicacion: string | null
  tipo_material: TipoMaterialLibro
  volumen: string | null
  nota_interna: string | null
  nota_publica: string | null
  precio: string | null
  estado_proceso: EstadoProcesoLibro
  fecha_inventario: string | null
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
  tipo: 'logia' | 'puesto' | 'sala'
  codigo_barras: string | null
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
  prestado_por: string | null
  devuelto_por: string | null
  hora_prestamo_real: string | null
  hora_devolucion_real: string | null
  via: 'manual' | 'BC'
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
