import React, { useEffect } from 'react';
import { XIcon, AlertCircleIcon } from './Icons';

interface ToastProps {
  message: string;
  type: 'error' | 'info' | 'success';
  onClose: () => void;
}

const Toast: React.FC<ToastProps> = ({ message, type, onClose }) => {
  useEffect(() => {
    const timer = setTimeout(() => {
      onClose();
    }, 5000); // Otomatis tutup setelah 5 detik

    return () => {
      clearTimeout(timer);
    };
  }, [onClose]);

  const baseClasses = "fixed top-5 right-5 z-50 flex items-center p-4 rounded-lg shadow-lg text-white max-w-sm animate-slide-in-right";
  
  const typeClasses = {
    error: 'bg-red-500',
    info: 'bg-blue-500',
    success: 'bg-green-500',
  };

  const Icon = type === 'error' ? AlertCircleIcon : null; // Tambahkan ikon untuk 'info' atau 'success' jika perlu

  return (
    <div className={`${baseClasses} ${typeClasses[type]}`} role="alert">
      {Icon && <Icon className="h-6 w-6 mr-3" />}
      <span className="flex-grow text-sm font-medium">{message}</span>
      <button 
        onClick={onClose} 
        className="ml-4 -mr-2 p-1 rounded-full hover:bg-white/20 transition-colors"
        aria-label="Tutup"
      >
        <XIcon className="h-5 w-5" />
      </button>
      <style>{`
        @keyframes slide-in-right {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        .animate-slide-in-right {
          animation: slide-in-right 0.3s ease-out forwards;
        }
      `}</style>
    </div>
  );
};

export default Toast;