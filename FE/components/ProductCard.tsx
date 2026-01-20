

import React, { useState } from 'react';
import { Product } from '../types';
import { DefaultProductImageIcon } from './Icons';

interface ProductCardProps {
  product: Pick<Product, 'id' | 'name' | 'image_url'>;
  onSelect: () => void;
}

const ProductCard: React.FC<ProductCardProps> = ({ product, onSelect }) => {
  const [imageError, setImageError] = useState(false);

  const handleImageError = () => {
    setImageError(true);
  };

  return (
    <div 
      className="bg-white rounded-xl shadow-md p-4 flex flex-col items-center text-center cursor-pointer hover:shadow-lg transition-shadow duration-200"
      onClick={onSelect}
    >
      <div className="w-32 h-32 flex items-center justify-center mb-4">
        {imageError || !product.image_url ? (
            <div className="w-full h-full rounded-full flex items-center justify-center bg-gray-100">
                <DefaultProductImageIcon className="w-16 h-16 text-gray-300" />
            </div>
        ) : (
          <img 
            src={product.image_url} 
            alt={product.name} 
            className="w-full h-full rounded-full object-cover"
            onError={handleImageError}
          />
        )}
      </div>
      <h3 className="font-semibold text-sm text-secondary flex-grow">{product.name}</h3>
      {/* Elemen harga dihapus sesuai permintaan */}
    </div>
  );
}

export default ProductCard;