

import React from 'react';
import { OrderItem, Customer, AppliedTax } from '../types';
import { ChevronDownIcon, ChevronRightIcon, XIcon, CustomersIcon, CartIcon, MoneyIcon } from './Icons';
import { formatRupiah } from '../utils/formatters';

interface OrderCartProps {
  cartId: string;
  onSwitchCart: () => void;
  orderItems: OrderItem[];
  onUpdateQuantity: (productId: number, newQuantity: number) => void;
  onRemoveItem: (itemId: number) => void;
  customer: Customer | null;
  onShowCustomerSelection: () => void;
  onClearCustomer: () => void;
  isUpdatingCustomer: boolean;
  subtotal: number;
  totalDiscount: number;
  appliedTaxes: AppliedTax[];
  payableAmount: number;
  onCheckout: () => void;
}

const CartItem: React.FC<{ 
    item: OrderItem;
    onUpdateQuantity: (productId: number, newQuantity: number) => void;
    onRemoveItem: (itemId: number) => void;
}> = ({ item, onUpdateQuantity, onRemoveItem }) => {
    const [isExpanded, setIsExpanded] = React.useState(false);
    const finalPrice = item.product.price * item.quantity * (1 - (item.discount || 0) / 100);

    return (
        <div className="py-3 border-b border-light-border last:border-b-0">
            <div className="flex items-center justify-between text-sm">
                <div className="flex items-center">
                    <button onClick={() => setIsExpanded(!isExpanded)} className="mr-2 text-gray-500">
                        {isExpanded ? <ChevronDownIcon className="h-4 w-4"/> : <ChevronRightIcon className="h-4 w-4"/>}
                    </button>
                    <span className="font-semibold text-secondary">{item.quantity.toFixed(2)}</span>
                    <span className="mx-2 text-gray-400">x</span>
                    <span className="font-medium text-secondary">{item.product.name}</span>
                </div>
                <div className="flex items-center">
                    <span className="font-bold text-secondary mr-3">{formatRupiah(finalPrice)}</span>
                    <button onClick={() => onRemoveItem(item.id)} className="text-gray-400 hover:text-red-500">
                        <XIcon className="h-4 w-4"/>
                    </button>
                </div>
            </div>
            {isExpanded && (
                <div className="pl-8 pt-3 grid grid-cols-1 gap-3 text-sm">
                    <div>
                        <label htmlFor={`quantity-${item.product.id}`} className="block text-gray-500 mb-1">Jumlah</label>
                        <div className="flex items-center border border-gray-300 rounded-md h-10">
                            <button
                                onClick={() => onUpdateQuantity(item.product.id, Math.max(1, item.quantity - 1))}
                                className="bg-red-500 text-white font-bold w-10 h-full flex items-center justify-center rounded-l-md hover:bg-red-600 transition-colors"
                                aria-label="Kurangi jumlah"
                            >
                                -
                            </button>
                            <span className="flex-1 text-center font-semibold text-secondary bg-white">{item.quantity}</span>
                            <button
                                onClick={() => onUpdateQuantity(item.product.id, item.quantity + 1)}
                                className="bg-green-500 text-white font-bold w-10 h-full flex items-center justify-center rounded-r-md hover:bg-green-600 transition-colors"
                                aria-label="Tambah jumlah"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const OrderCart: React.FC<OrderCartProps> = ({ cartId, onSwitchCart, orderItems, onUpdateQuantity, onRemoveItem, customer, onShowCustomerSelection, onClearCustomer, isUpdatingCustomer, subtotal, totalDiscount, appliedTaxes, payableAmount, onCheckout }) => {
    return (
        <aside className="w-[400px] bg-white flex flex-col p-6 border-l border-light-border">
            <div className="pb-4 border-b border-light-border">
                <h2 className="text-lg font-bold text-secondary mb-3">Keranjang #{cartId}</h2>
                {customer ? (
                    <div className="w-full flex items-center justify-between text-sm font-medium bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                        <div 
                            onClick={onShowCustomerSelection} 
                            className="flex items-center flex-grow cursor-pointer"
                        >
                            <CustomersIcon className="h-5 w-5 mr-2 text-green-600" />
                            <span className="font-semibold text-green-800">{customer.name}</span>
                        </div>
                        <button 
                            onClick={onClearCustomer} 
                            className="text-gray-400 hover:text-red-500 disabled:opacity-50 disabled:cursor-wait"
                            disabled={isUpdatingCustomer}
                        >
                            <XIcon className="h-4 w-4" />
                        </button>
                    </div>
                ) : (
                    <button 
                        onClick={onShowCustomerSelection}
                        className="w-full flex items-center justify-center text-sm font-medium text-secondary bg-gray-100 px-3 py-2 rounded-lg hover:bg-gray-200"
                    >
                        <CustomersIcon className="h-5 w-5 mr-2" />
                        Pilih customer
                    </button>
                )}
            </div>

            <div className="flex-grow overflow-y-auto -mr-6 pr-6 mt-4">
                {orderItems.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full text-center text-gray-500 px-4">
                        <CartIcon className="w-16 h-16 text-gray-300 mb-4"/>
                        <p className="font-semibold text-secondary mb-1">Keranjang Kosong</p>
                        <p className="text-sm">Pilih produk dari daftar di sebelah kiri untuk memulai.</p>
                    </div>
                ) : (
                    orderItems.map(item => (
                        <CartItem key={item.id} item={item} onUpdateQuantity={onUpdateQuantity} onRemoveItem={onRemoveItem}/>
                    ))
                )}
            </div>

            <div className="pt-6 border-t border-light-border mt-auto">
                <div className="space-y-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-gray-500">Subtotal</span>
                        <span className="font-medium text-secondary">{formatRupiah(subtotal)}</span>
                    </div>
                    {totalDiscount > 0 && (
                        <div className="flex justify-between">
                            <span className="text-gray-500">Diskon</span>
                            <span className="font-medium text-red-500">-{formatRupiah(totalDiscount)}</span>
                        </div>
                    )}
                    {appliedTaxes.map((tax, index) => (
                        <div className="flex justify-between" key={index}>
                            <span className="text-gray-500">
                                {tax.name} {tax.type === 'PERCENTAGE' && `(${tax.rate}%)`}
                            </span>
                            <span className="font-medium text-secondary">{formatRupiah(tax.amount)}</span>
                        </div>
                    ))}
                    <div className="flex justify-between items-center text-lg font-bold text-secondary pt-2">
                        <span>Jumlah Bayar</span>
                        <span>{formatRupiah(payableAmount)}</span>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 mt-6">
                    <button 
                        onClick={onSwitchCart}
                        className="flex items-center justify-center w-full bg-accent text-white py-3 rounded-lg font-semibold hover:bg-orange-600 transition-colors"
                    >
                        <CartIcon className="h-5 w-5 mr-2"/>
                        Ganti Keranjang
                    </button>
                    <button 
                        onClick={onCheckout}
                        disabled={orderItems.length === 0}
                        className="flex items-center justify-center w-full bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        <MoneyIcon className="h-5 w-5 mr-2"/>
                        Checkout
                    </button>
                </div>
            </div>
        </aside>
    );
};

export default OrderCart;