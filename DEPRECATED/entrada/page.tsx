'use client';
import { useState } from 'react';
import { QrCode, Keyboard, BookOpen, CheckCircle2, AlertCircle, Users } from 'lucide-react';
import { formatRut, validarRut } from '@/lib/rut';
import { motion, AnimatePresence } from 'framer-motion';
import Link from 'next/link';

export default function KioskoEntradaPage() {
  const [mode, setMode] = useState<'menu' | 'rut' | 'success' | 'error'>('menu');
  const [rut, setRut] = useState('');
  const personasEnSala = 47;

  const teclas = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'K', '0', '⌫'];

  const handleTecla = (t: string) => {
    if (t === '⌫') {
      setRut((prev) => {
        const cleaned = prev.replace(/[^0-9kK]/g, '');
        return formatRut(cleaned.slice(0, -1));
      });
    } else {
      const cleaned = rut.replace(/[^0-9kK]/g, '') + t;
      if (cleaned.length <= 9) setRut(formatRut(cleaned));
    }
  };

  const registrar = () => {
    if (!validarRut(rut)) {
      setMode('error');
      setTimeout(() => setMode('rut'), 3000);
      return;
    }
    setMode('success');
    setTimeout(() => { setMode('menu'); setRut(''); }, 4000);
  };

  return (
    <div className="min-h-screen flex flex-col items-center justify-center p-6">
      {/* Header */}
      <div className="text-center mb-8">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white mb-3">
          <BookOpen className="w-8 h-8" />
        </div>
        <h1 className="text-2xl font-display font-bold text-gray-900">Biblioteca Universitaria</h1>
        <div className="flex items-center justify-center gap-2 mt-2 text-sm text-gray-500">
          <Users className="w-4 h-4" />
          <span>En sala: <strong className="text-indigo-700">{personasEnSala}</strong> / 220</span>
        </div>
      </div>

      <AnimatePresence mode="wait">
        {mode === 'menu' && (
          <motion.div key="menu" initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.95 }} className="w-full max-w-sm space-y-4">
            <button
              onClick={() => {
                setMode('success');
                setTimeout(() => setMode('menu'), 4000);
              }}
              className="w-full flex items-center justify-center gap-3 py-6 bg-indigo-600 text-white text-xl font-bold rounded-2xl shadow-lg hover:bg-indigo-700 transition-all active:scale-95"
            >
              <QrCode className="w-8 h-8" />
              Escanear QR
            </button>
            <button
              onClick={() => setMode('rut')}
              className="w-full flex items-center justify-center gap-3 py-6 bg-white text-gray-900 text-xl font-bold rounded-2xl shadow-lg border-2 border-gray-200 hover:border-indigo-600 transition-all active:scale-95"
            >
              <Keyboard className="w-8 h-8" />
              Ingresar RUT
            </button>
            <Link href="/kiosko/catalogo" className="block w-full text-center py-4 text-indigo-700 text-lg font-medium hover:underline">
              Ver catálogo
            </Link>
          </motion.div>
        )}

        {mode === 'rut' && (
          <motion.div key="rut" initial={{ opacity: 0, x: 50 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -50 }} className="w-full max-w-sm">
            <div className="bg-white rounded-2xl shadow-lg p-6 mb-4">
              <label className="block text-sm font-medium text-gray-500 mb-2">Ingresa tu RUT</label>
              <div className="text-3xl font-mono font-bold text-center py-4 bg-gray-50 rounded-xl text-gray-900 min-h-[60px]">
                {rut || <span className="text-gray-300">XX.XXX.XXX-X</span>}
              </div>
            </div>

            <div className="grid grid-cols-3 gap-2 mb-4">
              {teclas.map((t) => (
                <button
                  key={t}
                  onClick={() => handleTecla(t)}
                  className={`py-5 text-2xl font-bold rounded-xl transition-all active:scale-95 ${
                    t === '⌫' ? 'bg-red-100 text-red-600 hover:bg-red-200' : 'bg-white shadow-md text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  {t}
                </button>
              ))}
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => { setMode('menu'); setRut(''); }}
                className="flex-1 py-4 bg-gray-200 text-gray-700 text-lg font-bold rounded-xl hover:bg-gray-300 transition-all"
              >
                Volver
              </button>
              <button
                onClick={registrar}
                disabled={!rut}
                className="flex-1 py-4 bg-indigo-600 text-white text-lg font-bold rounded-xl hover:bg-indigo-700 transition-all disabled:opacity-50"
              >
                Confirmar
              </button>
            </div>
          </motion.div>
        )}

        {mode === 'success' && (
          <motion.div key="success" initial={{ opacity: 0, scale: 0.8 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0 }} className="text-center">
            <div className="inline-flex items-center justify-center w-24 h-24 rounded-full bg-emerald-100 text-emerald-600 mb-4">
              <CheckCircle2 className="w-12 h-12" />
            </div>
            <h2 className="text-2xl font-display font-bold text-gray-900 mb-2">¡Bienvenido/a!</h2>
            <p className="text-xl text-gray-600">María González</p>
            <p className="text-sm text-gray-400 mt-3">Entrada registrada correctamente</p>
          </motion.div>
        )}

        {mode === 'error' && (
          <motion.div key="error" initial={{ opacity: 0, scale: 0.8 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0 }} className="text-center">
            <div className="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-100 text-red-600 mb-4">
              <AlertCircle className="w-12 h-12" />
            </div>
            <h2 className="text-2xl font-display font-bold text-red-700 mb-2">Error</h2>
            <p className="text-lg text-gray-600">RUT inválido. Verifica el formato.</p>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
