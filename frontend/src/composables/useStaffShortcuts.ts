export type StaffShortcut = {
  key: 'F1' | 'F2' | 'F3' | 'F4'
  label: string
  desc: string
  name: string
  icon: string
}

/** Atajos de teclado F1–F4 para navegar entre los módulos principales del panel de staff. */
export const STAFF_SHORTCUTS: StaffShortcut[] = [
  { key: 'F1', label: 'Registrar Entrada', desc: 'Ingreso por RUT o QR', name: 'entrada', icon: 'M11 16l-4-4m0 0l4-4m-4 4h14M5 5v14' },
  { key: 'F2', label: 'Préstamo Libro', desc: 'Préstamo y devolución', name: 'prestamo', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { key: 'F3', label: 'Reservar Logia', desc: 'Logias de estudio, 1er y 2do piso', name: 'salas', icon: 'M4 6h16M4 12h16M4 18h7' },
  { key: 'F4', label: 'Usuario Externo', desc: 'Visitantes y comunidad', name: 'usuarios', icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3a4 4 0 00-3-3.87M9 12a4 4 0 100-8 4 4 0 000 8z' },
]

export const STAFF_SHORTCUT_MAP: Record<string, string> = Object.fromEntries(
  STAFF_SHORTCUTS.map((s) => [s.key, s.name]),
)
