import React from 'react';
import { CartSummary } from '../types';
import { CartIcon, ChevronRightIcon } from './Icons';

interface ActiveCartsListProps {
  carts: CartSummary[];
  onSelectCart: (cartId: string) => void;
}

const ActiveCartsList: React.FC<ActiveCartsListProps> = ({ carts, onSelectCart }) => {
  return (
    <div className="w-[400px] bg-white flex flex-col p-6 border-l border-light-border">
      <header className="pb-4 border-b border-light-border">
        <h2 className="text-lg font-bold text-secondary">Keranjang Aktif</h2>
        <p className="text-sm text-gray-500">Pilih keranjang untuk melanjutkan transaksi.</p>
      </header>

      <div className="flex-grow overflow-y-auto -mr-6 pr-6 mt-4">
        {carts.map(cart => {
          const createdAtTime = new Date(cart.created_at.replace(' ', 'T')).toLocaleTimeString('id-ID', {
              hour: '2-digit',
              minute: '2-digit'
          });

          return (
            <button
                key={cart.id}
                onClick={() => onSelectCart(cart.id.toString())}
                className="w-full flex items-center justify-between p-4 mb-3 bg-gray-50 rounded-lg text-left hover:bg-primary hover:text-white transition-colors duration-200 group"
            >
                <div className="flex items-center">
                    <CartIcon className="h-6 w-6 mr-4 text-gray-400 group-hover:text-white" />
                    <div>
                        <span className="font-semibold text-secondary group-hover:text-white block">Keranjang #{cart.id}</span>
                        <span className="text-xs text-gray-500 group-hover:text-gray-200">{cart.customer_name} &middot; {cart.items_count} {cart.items_count === 1 ? 'item' : 'items'}</span>
                    </div>
                </div>
                <div className="flex items-center">
                    <span className="text-sm text-gray-500 mr-2 group-hover:text-gray-200">{createdAtTime}</span>
                    <ChevronRightIcon className="h-5 w-5 text-gray-400 group-hover:text-white" />
                </div>
            </button>
          )
        })}
      </div>
    </div>
  );
};

export default ActiveCartsList;