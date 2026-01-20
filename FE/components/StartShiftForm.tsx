import React, { useState } from 'react';

interface StartShiftFormProps {
  onStartShift: (pin: string) => void;
  isLoading: boolean;
  error: string | null;
}

const StartShiftForm: React.FC<StartShiftFormProps> = ({ onStartShift, isLoading, error }) => {
  const [pin, setPin] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (pin.trim() && !isLoading) {
      onStartShift(pin);
    }
  };

  return (
    <div className="flex-1 flex items-center justify-center bg-light-bg p-4">
      <div className="bg-white p-8 rounded-lg shadow-md max-w-sm w-full text-center">
        <h2 className="text-2xl font-bold text-secondary mb-2">Mulai Shift</h2>
        <p className="text-gray-500 mb-6">Masukkan PIN Anda untuk memulai sesi kasir.</p>
        <form onSubmit={handleSubmit}>
          {error && (
            <div className="mb-4 text-center text-red-600 bg-red-100 p-3 rounded-lg text-sm">
              {error}
            </div>
          )}
          <div className="mb-6">
            <label htmlFor="pin" className="sr-only">PIN Karyawan</label>
            <input
              id="pin"
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
          <button
            type="submit"
            className="w-full bg-primary text-white font-bold py-3 px-4 rounded-lg hover:bg-primary-light transition-colors duration-300 disabled:bg-gray-400 disabled:cursor-not-allowed"
            disabled={!pin.trim() || isLoading}
          >
            {isLoading ? 'Memproses...' : 'Mulai Shift'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default StartShiftForm;
