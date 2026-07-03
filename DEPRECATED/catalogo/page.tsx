'use client';
import { useState } from 'react';
import { Search, BookOpen, ArrowLeft, BookCheck, BookX } from 'lucide-react';
import Link from 'next/link';
import { motion } from 'framer-motion';

const libros = [
  { titulo: 'Introducción a la Programación', autor: 'John V. Guttag', area: 'Computación', disponible: true },
  { titulo: 'Cálculo Diferencial e Integral', autor: 'James Stewart', area: 'Matemáticas', disponible: true },
  { titulo: 'Historia de Chile Contemporáneo', autor: 'Alfredo Jocelyn-Holt', area: 'Historia', disponible: false },
  { titulo: 'Fundamentos de Física', autor: 'David Halliday', area: 'Física', disponible: true },
  { titulo: 'Derecho Civil Chileno', autor: 'Arturo Alessandri', area: 'Derecho', disponible: false },
  { titulo: 'Macroeconomía', autor: 'Gregory Mankiw', area: 'Economía', disponible: true },
  { titulo: 'Biología Molecular de la Célula', autor: 'Bruce Alberts', area: 'Biología', disponible: true },
  { titulo: 'Química General', autor: 'Raymond Chang', area: 'Química', disponible: true },
  { titulo: 'Psicología Social', autor: 'David Myers', area: 'Psicología', disponible: false },
  { titulo: 'Ingeniería de Software', autor: 'Ian Sommerville', area: 'Computación', disponible: true },
];

export default function CatalogoPage() {
  const [search, setSearch] = useState('');

  const filtered = libros.filter((l) =>
    l.titulo.toLowerCase().includes(search.toLowerCase()) ||
    l.autor.toLowerCase().includes(search.toLowerCase()) ||
    l.area.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="min-h-screen p-6 max-w-lg mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <Link href="/kiosko/entrada" className="w-10 h-10 flex items-center justify-center rounded-lg bg-white shadow-md">
          <ArrowLeft className="w-5 h-5 text-gray-600" />
        </Link>
        <div>
          <h1 className="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-indigo-700" /> Catálogo
          </h1>
          <p className="text-xs text-gray-500">Consulta disponibilidad de libros</p>
        </div>
      </div>

      <div className="relative mb-4">
        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar libro, autor o área..."
          className="w-full pl-12 pr-4 py-4 bg-white rounded-xl shadow-md text-lg border-0 focus:ring-2 focus:ring-indigo-500 outline-none"
        />
      </div>

      <div className="space-y-3">
        {filtered.map((libro, i) => (
          <motion.div
            key={libro.titulo}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="bg-white rounded-xl shadow-md p-4 flex items-center gap-4"
          >
            <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
              libro.disponible ? 'bg-emerald-100' : 'bg-red-100'
            }`}>
              {libro.disponible ? <BookCheck className="w-5 h-5 text-emerald-600" /> : <BookX className="w-5 h-5 text-red-600" />}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-medium text-gray-900 text-sm truncate">{libro.titulo}</p>
              <p className="text-xs text-gray-500">{libro.autor} • {libro.area}</p>
            </div>
            <span className={`text-xs px-2 py-1 rounded-full font-medium ${
              libro.disponible ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
            }`}>
              {libro.disponible ? 'Disponible' : 'Prestado'}
            </span>
          </motion.div>
        ))}
        {filtered.length === 0 && (
          <div className="text-center py-12 text-gray-400">Sin resultados</div>
        )}
      </div>
    </div>
  );
}
