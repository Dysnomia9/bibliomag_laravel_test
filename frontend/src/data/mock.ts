import type { Entrada, Libro, Prestamo, Reserva, ReservaLibro, ResumenDashboard, Sala, Usuario } from '@/types'

export const resumenMock: ResumenDashboard = {
  usuariosActivos: 128,
  entradasHoy: 34,
  personasEnSala: 12,
  prestamosActivos: 21,
  prestamosAtrasados: 3,
  ultimasEntradas: [
    { id: 1, usuario_id: 1, fecha_hora_entrada: new Date().toISOString(), fecha_hora_salida: null, via: 'qr', usuario: { id: 1, nombre: 'Camila', apellido: 'Soto', rut: '19.876.543-2' } },
    { id: 2, usuario_id: 2, fecha_hora_entrada: new Date().toISOString(), fecha_hora_salida: null, via: 'manual', usuario: { id: 2, nombre: 'Matías', apellido: 'Vera', rut: '18.234.111-9' } },
  ],
  ultimosPrestamos: [
    { id: 1, usuario_id: 1, libro_titulo: 'Estructuras de Datos y Algoritmos', fecha_prestamo: new Date().toISOString(), fecha_devolucion: new Date().toISOString(), fecha_devolucion_real: null, estado: 'activo', usuario: { id: 1, nombre: 'Camila', apellido: 'Soto', rut: '19.876.543-2' } },
    { id: 2, usuario_id: 3, libro_titulo: 'Bases de Datos', fecha_prestamo: new Date().toISOString(), fecha_devolucion: new Date().toISOString(), fecha_devolucion_real: null, estado: 'atrasado', usuario: { id: 3, nombre: 'Patricia', apellido: 'Maldonado', rut: '15.998.222-4' } },
  ],
}

export const usuariosMock: Usuario[] = [
  { id: 1, rut: '19.876.543-2', nombre: 'Camila', apellido: 'Soto', email: 'camila.soto0@umag.cl', tipo: 'estudiante', carrera: 'Ingeniería Civil Informática', anio_ingreso: 2023, sexo: 'Femenino', activo: true, qr_code: 'QR1A2B3C' },
  { id: 2, rut: '18.234.111-9', nombre: 'Matías', apellido: 'Vera', email: 'matias.vera1@umag.cl', tipo: 'estudiante', carrera: 'Derecho', anio_ingreso: 2022, sexo: 'Masculino', activo: true, qr_code: 'QR4D5E6F' },
  { id: 3, rut: '15.998.222-4', nombre: 'Patricia', apellido: 'Maldonado', email: null, tipo: 'docente', carrera: null, anio_ingreso: null, sexo: 'Femenino', activo: true, qr_code: null },
  { id: 4, rut: '16.543.210-8', nombre: 'Ignacio', apellido: 'Contreras', email: 'ignacio.contreras3@umag.cl', tipo: 'funcionario', carrera: null, anio_ingreso: null, sexo: 'Masculino', activo: false, qr_code: null },
]

export const entradasMock: Entrada[] = [
  { id: 1, usuario_id: 1, fecha_hora_entrada: new Date().toISOString(), fecha_hora_salida: null, via: 'manual', usuario: { id: 1, nombre: 'María', apellido: 'González', rut: '12.345.678-5' } },
  { id: 2, usuario_id: 2, fecha_hora_entrada: new Date().toISOString(), fecha_hora_salida: null, via: 'qr', usuario: { id: 2, nombre: 'Carlos', apellido: 'Muñoz', rut: '16.789.012-3' } },
  { id: 3, usuario_id: 3, fecha_hora_entrada: new Date().toISOString(), fecha_hora_salida: null, via: 'manual', usuario: { id: 3, nombre: 'Javiera', apellido: 'Soto', rut: '19.234.567-8' } },
]

export const prestamosMock: Prestamo[] = [
  { id: 1, usuario_id: 1, libro_titulo: 'Introducción a la Programación en Python', fecha_prestamo: new Date().toISOString(), fecha_devolucion: new Date(Date.now() + 7 * 86400000).toISOString(), fecha_devolucion_real: null, estado: 'activo' },
  { id: 2, usuario_id: 1, libro_titulo: 'Cálculo Diferencial e Integral - Stewart', fecha_prestamo: new Date().toISOString(), fecha_devolucion: new Date(Date.now() - 2 * 86400000).toISOString(), fecha_devolucion_real: null, estado: 'atrasado' },
]

export const reservasLibroMock: ReservaLibro[] = [
  { id: 1, usuario_id: 1, libro_id: 1, fecha_reserva: new Date().toISOString().slice(0, 10), fecha_retiro: new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10), estado: 'pendiente', libro: { id: 1, codigo_barras: '9789563160215', titulo: 'Historia de la Patagonia', autor: 'Mateo Martinic', categoria: 'Historia', disponible: true } },
]

export const salasMock: Sala[] = Array.from({ length: 25 }, (_, i) => ({
  id: i + 1,
  nombre: `Sala ${String(i + 1).padStart(2, '0')}`,
  capacidad: [2, 3, 4][i % 3],
  piso: '1er Piso',
}))

export const catalogoMock: Libro[] = [
  { id: 1, codigo_barras: '9789561228351', titulo: 'Introducción a la Programación en Python', autor: 'John V. Guttag', categoria: 'Computación', disponible: true },
  { id: 2, codigo_barras: '9789706868824', titulo: 'Cálculo Diferencial e Integral - Stewart', autor: 'James Stewart', categoria: 'Matemáticas', disponible: true },
  { id: 3, codigo_barras: '9786073237826', titulo: 'Física Universitaria - Sears & Zemansky', autor: 'Hugh D. Young', categoria: 'Física', disponible: false },
  { id: 4, codigo_barras: '9789561224100', titulo: 'Derecho Constitucional Chileno', autor: 'Humberto Nogueira', categoria: 'Derecho', disponible: false },
  { id: 5, codigo_barras: '9789562014533', titulo: 'Enfermería Comunitaria', autor: 'Marcia Padilla', categoria: 'Enfermería', disponible: true },
  { id: 6, codigo_barras: '9780000000006', titulo: 'Estructuras de Datos y Algoritmos', autor: 'Robert Sedgewick', categoria: 'Computación', disponible: true },
  { id: 7, codigo_barras: '9780000000007', titulo: 'Biología Molecular de la Célula', autor: 'Bruce Alberts', categoria: 'Biología', disponible: true },
]

export const reservasSalasMock: Reserva[] = [
  { id: 1, sala_id: 1, usuario_id: 1, nombre_usuario: 'Carlos Muñoz', rut_usuario: '11.111.111-1', fecha: new Date().toISOString().slice(0, 10), hora_inicio: 8, hora_fin: 10, estado: 'activa' },
  { id: 2, sala_id: 3, usuario_id: 2, nombre_usuario: 'Luisa Fernández', rut_usuario: '44.444.444-4', fecha: new Date().toISOString().slice(0, 10), hora_inicio: 10, hora_fin: 12, estado: 'activa' },
]
