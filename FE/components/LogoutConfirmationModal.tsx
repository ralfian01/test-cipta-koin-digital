import React, { useState, useEffect } from 'react';
import { endShift } from '../api/shift';
import { XIcon } from './Icons';

interface LogoutConfirmationModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const LogoutConfirmationModal: React.FC<LogoutConfirmationModalProps> = ({ isOpen, onClose, onSuccess }) => {
  const [pin, setPin] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Reset state saat modal ditutup
  useEffect(() => {
    if (!isOpen) {
      setTimeout(() => {
        setPin('');
        setError(null);
        setIsLoading(false);
      }, 300); // Tunggu animasi penutupan
    }
  }, [isOpen]);

  if (!isOpen) {
    return null;
  }

  const handleConfirm = async () => {
    if (!pin.trim()) {
      setError("PIN tidak boleh kosong.");
      return;
    }
    setError(null);
    setIsLoading(true);

    try {
      // endShift akan melempar error jika respons API tidak sukses (mis. bukan status 200).
      await endShift(pin);
      
      // Jika panggilan di atas berhasil, panggil onSuccess untuk logout final.
      onSuccess();

    } catch (err: any) {
      // Jika endShift gagal, tangkap error, tampilkan, dan hentikan loading.
      setError(err.message || "PIN salah atau terjadi kesalahan.");
      setIsLoading(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    handleConfirm();
  };

  return (
    <div 
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300"
      onClick={onClose}
    >
      <div 
        className="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm m-4 text-center transform transition-all duration-300 scale-95 opacity-0 animate-fade-in-scale"
        onClick={(e) => e.stopPropagation()}
        style={{ animation: 'fade-in-scale 0.3s forwards' }}
      >
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold text-secondary">Akhiri Shift</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <XIcon className="h-6 w-6" />
          </button>
        </div>
        
        <p className="text-gray-600 mb-6">Konfirmasi PIN untuk menyelesaikan shift dan logout.</p>
        
        <form onSubmit={handleSubmit}>
          {error && (
            <div className="mb-4 text-center text-red-600 bg-red-100 p-3 rounded-lg text-sm">
              {error}
            </div>
          )}
          <div className="mb-6">
            <label htmlFor="logout-pin" className="sr-only">PIN Karyawan</label>
            <input
              id="logout-pin"
              type="password"
              value={pin}
              onChange={(e) => setPin(e.target.value)}
              placeholder="••••••"
              className="w-full px-4 py-3 text-center text-2xl tracking-[.5em] border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
              maxLength={6}
              autoFocus
              disabled={isLoading}
            />
          </div>
          <div className="flex gap-4">
            <button
              type="button"
              onClick={onClose}
              className="w-full bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded-lg hover:bg-gray-300 transition-colors duration-300 disabled:opacity-50"
              disabled={isLoading}
            >
              Batal
            </button>
            <button
              type="submit"
              className="w-full bg-primary text-white font-bold py-3 px-4 rounded-lg hover:bg-primary-light transition-colors duration-300 disabled:bg-gray-400 disabled:cursor-not-allowed"
              disabled={!pin.trim() || isLoading}
            >
              {isLoading ? 'Memproses...' : 'Konfirmasi & Keluar'}
            </button>
          </div>
        </form>
      </div>
      {/* Tambahkan keyframes untuk animasi */}
      <style>{`
        @keyframes fade-in-scale {
          from { transform: scale(0.95); opacity: 0; }
          to { transform: scale(1); opacity: 1; }
        }
        .animate-fade-in-scale {
          animation: fade-in-scale 0.2s ease-out forwards;
        }
      `}</style>
    </div>
  );
};

export default LogoutConfirmationModal;