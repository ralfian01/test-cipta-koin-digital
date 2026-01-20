
import React, { useEffect } from 'react';
import { ApiProduct, Variant } from '../types';
import { XIcon } from './Icons';

interface VariantSelectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: ApiProduct | null;
  onSelect: (variant: Variant) => void;
  isAdding: boolean;
}

const VariantSelectionModal: React.FC<VariantSelectionModalProps> = ({ isOpen, onClose, product, onSelect, isAdding }) => {

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    if (isOpen) {
      window.addEventListener('keydown', handleKeyDown);
    }

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, onClose]);

  if (!isOpen || !product) {
    return null;
  }

  return (
    <div 
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300"
      onClick={onClose}
      aria-modal="true"
      role="dialog"
    >
      <div 
        className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md m-4 transform transition-all duration-300 scale-95 opacity-0 animate-fade-in-scale"
        onClick={(e) => e.stopPropagation()}
        style={{ animation: 'fade-in-scale 0.3s forwards' }}
      >
        <div className="flex justify-between items-center mb-4 pb-4 border-b border-light-border">
          <div>
            <h2 className="text-xl font-bold text-secondary">Pilih Varian</h2>
            <p className="text-gray-600">Untuk produk: <span className="font-semibold">{product.name}</span></p>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600" disabled={isAdding}>
            <XIcon className="h-6 w-6" />
          </button>
        </div>
        
        <div className="max-h-[60vh] overflow-y-auto -mr-6 pr-6">
          <ul className={`space-y-2 ${isAdding ? 'opacity-50 cursor-wait' : ''}`}>
            {product.variants.map(variant => (
              <li key={variant.variant_id}>
                <button
                  onClick={() => onSelect(variant)}
                  disabled={isAdding}
                  className="w-full text-left p-4 rounded-lg hover:bg-gray-100 transition-colors flex justify-between items-center disabled:cursor-wait"
                >
                  <span className="font-medium text-secondary">{variant.name}</span>
                  {variant.sku && (
                    <span className="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded">
                      SKU: {variant.sku}
                    </span>
                  )}
                </button>
              </li>
            ))}
          </ul>
        </div>
      </div>
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

export default VariantSelectionModal;