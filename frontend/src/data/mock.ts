import type { ResumenDashboard, Usuario } from '@/types'

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
