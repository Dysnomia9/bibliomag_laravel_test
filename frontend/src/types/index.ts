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
  usuario_id: number
  fecha_hora_entrada: string
  fecha_hora_salida: string | null
  via: 'manual' | 'qr'
  usuario?: Pick<Usuario, 'id' | 'nombre' | 'apellido' | 'rut'>
}

export type Prestamo = {
  id: number
  usuario_id: number
  libro_titulo: string
  fecha_prestamo: string
  fecha_devolucion: string
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
