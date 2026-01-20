

import React, { useState, useEffect, useCallback } from 'react';
import OrderCart from './OrderCart';
import ActiveCartsList from './ActiveCartsList';
import CustomerSelectionView from './CustomerSelectionView';
import { CartIcon, PlusIcon } from './Icons';
import { OrderItem, CartSummary, Customer, CartDetails } from '../types';
import { fetchCartsSummary, createCart, assignCustomerToCart, unassignCustomerFromCart } from '../api/cart';

type CartViewMode = 'order' | 'customerSelection';

interface CartViewProps {
    orderItems: OrderItem[];
    onUpdateQuantity: (productId: number, newQuantity: number) => void;
    onRemoveItem: (itemId: number) => void;
    onCheckout: () => void;
    currentCartId: string | null;
    setCurrentCartId: (id: string | null) => void;
    refreshTrigger: number;
    cartDetails: CartDetails | null;
    cartDetailsLoading: boolean;
    cartDetailsError: string;
}

const CartView: React.FC<CartViewProps> = ({ 
    orderItems, 
    onUpdateQuantity, 
    onRemoveItem, 
    onCheckout,
    currentCartId, 
    setCurrentCartId,
    refreshTrigger,
    cartDetails,
    cartDetailsLoading,
    cartDetailsError 
}) => {
    const [viewMode, setViewMode] = useState<CartViewMode>('order');
    
    const [carts, setCarts] = useState<CartSummary[] | null>(() => {
        const cached = sessionStorage.getItem('pos_carts_summary');
        if (cached) {
            try { return JSON.parse(cached); } catch (e) {
                console.error("Gagal mem-parsing ringkasan keranjang dari cache:", e);
                sessionStorage.removeItem('pos_carts_summary');
                return null;
            }
        }
        return null;
    });
    const [cartsLoading, setCartsLoading] = useState(() => !sessionStorage.getItem('pos_carts_summary'));
    const [cartsError, setCartsError] = useState<string>('');
    const [isCreatingCart, setIsCreatingCart] = useState(false);
    
    const [isUpdatingCustomer, setIsUpdatingCustomer] = useState(false);
    
    const getCarts = useCallback(async ({ isRetry = false }: { isRetry?: boolean } = {}) => {
        if (isRetry) setCartsLoading(true);
        setCartsError('');
        try {
            const freshCartsSummary = await fetchCartsSummary();
            setCarts(freshCartsSummary);
            sessionStorage.setItem('pos_carts_summary', JSON.stringify(freshCartsSummary));
        } catch (err: any) {
            setCartsError(err.message || 'Gagal memuat keranjang aktif.');
        } finally {
            if (isRetry || cartsLoading) setCartsLoading(false);
        }
    }, [cartsLoading]);

    useEffect(() => {
        if (!currentCartId) {
            getCarts();
        }
    }, [currentCartId, getCarts, refreshTrigger]); // Pemicu refresh juga akan memuat ulang daftar keranjang

    const handleSelectCart = (cartId: string) => {
        setCurrentCartId(cartId);
        setViewMode('order');
    };

    const handleSwitchCart = () => {
        setCurrentCartId(null);
        sessionStorage.removeItem('pos_carts_summary');
    };
    
    const handleCreateCart = async () => {
        setIsCreatingCart(true);
        setCartsError('');
        try {
            const newCartData = await createCart();
            sessionStorage.removeItem('pos_carts_summary');
            handleSelectCart(newCartData.id.toString());
        } catch (err: any) {
            setCartsError(err.message || 'Gagal membuat keranjang baru.');
        } finally {
            setIsCreatingCart(false);
        }
    };

    const handleSelectCustomer = async (customer: Customer) => {
        if (isUpdatingCustomer || !currentCartId) return;
        setIsUpdatingCustomer(true);
        try {
            await assignCustomerToCart(currentCartId, customer.id);
            // Pemicu refresh tidak ada di sini, jadi kita akan mengandalkan pembaruan state induk
            // Untuk pengalaman pengguna yang lebih baik, kita dapat memperbarui customer secara lokal
            // tetapi ini akan ditangani oleh state yang diangkat.
            setViewMode('order');
            // Memaksa pembaruan data di CashierPage dengan memanggil setCurrentCartId
            setCurrentCartId(currentCartId);
        } catch (err: any) {
            console.error('Gagal menetapkan pelanggan ke keranjang:', err);
            alert(`Terjadi kesalahan: ${err.message}`);
        } finally {
            setIsUpdatingCustomer(false);
        }
    };

    const handleClearCustomer = async () => {
        if (isUpdatingCustomer || !currentCartId) return;
        setIsUpdatingCustomer(true);
        try {
            await unassignCustomerFromCart(currentCartId);
            setCurrentCartId(currentCartId); // Sama seperti di atas
        } catch (err: any) {
            console.error('Gagal menghapus pelanggan dari keranjang:', err);
            alert(`Terjadi kesalahan: ${err.message}`);
        } finally {
            setIsUpdatingCustomer(false);
        }
    };
    
    if (currentCartId) {
        if (cartDetailsLoading && !cartDetails) {
            return (
                <div className="w-[400px] bg-white flex flex-col items-center justify-center p-6 border-l border-light-border">
                    <p className="text-gray-500 animate-pulse">Memuat detail keranjang...</p>
                </div>
            );
        }
        if (cartDetailsError) {
            return (
                <div className="w-[400px] bg-white flex flex-col items-center justify-center p-6 border-l border-light-border text-center">
                    <div className="text-red-600">
                        <p className="font-bold mb-2">Gagal Memuat Detail</p>
                        <p className="text-sm">{cartDetailsError}</p>
                    </div>
                </div>
            );
        }

        if (viewMode === 'customerSelection') {
            return (
                <CustomerSelectionView 
                    onBack={() => setViewMode('order')}
                    onSelectCustomer={handleSelectCustomer}
                    isAssigning={isUpdatingCustomer}
                />
            );
        }

        if (!cartDetails) {
            return (
                 <div className="w-[400px] bg-white flex flex-col items-center justify-center p-6 border-l border-light-border">
                    <p className="text-gray-500 animate-pulse">Menyiapkan keranjang...</p>
                </div>
            );
        }
        
        return (
            <OrderCart 
                cartId={currentCartId}
                onSwitchCart={handleSwitchCart}
                orderItems={orderItems}
                onUpdateQuantity={onUpdateQuantity}
                onRemoveItem={onRemoveItem}
                customer={cartDetails.customer}
                onShowCustomerSelection={() => setViewMode('customerSelection')}
                onClearCustomer={handleClearCustomer}
                isUpdatingCustomer={isUpdatingCustomer}
                subtotal={parseFloat(cartDetails.subtotal)}
                totalDiscount={parseFloat(cartDetails.total_discount)}
                appliedTaxes={cartDetails.applied_taxes || []}
                payableAmount={cartDetails.final_total}
                onCheckout={onCheckout}
            />
        );
    }
    
    if (cartsLoading) {
        return (
            <div className="w-[400px] bg-white flex flex-col items-center justify-center p-6 border-l border-light-border">
                <p className="text-gray-500 animate-pulse">Memuat keranjang...</p>
            </div>
        );
    }

    if (cartsError) {
        return (
            <div className="w-[400px] bg-white flex flex-col items-center justify-center p-6 border-l border-light-border text-center">
                <div className="text-red-600">
                    <p className="font-bold mb-2">Gagal Memuat Keranjang</p>
                    <p className="text-sm">{cartsError}</p>
                </div>
                <button onClick={() => getCarts({ isRetry: true })} className="mt-4 bg-primary text-white py-2 px-4 rounded-lg text-sm">Coba Lagi</button>
            </div>
        );
    }

    if (carts && carts.length > 0) {
        return <ActiveCartsList carts={carts} onSelectCart={handleSelectCart} />;
    }

    return (
        <div className="w-[400px] bg-white flex flex-col items-center justify-center p-6 border-l border-light-border">
            {cartsError && <p className="text-red-500 mb-4 text-center">{cartsError}</p>}
            <button 
                onClick={handleCreateCart}
                disabled={isCreatingCart}
                className="flex items-center justify-center w-full max-w-xs bg-primary text-white py-3 px-5 rounded-lg font-semibold hover:bg-primary-light transition-colors text-base shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:bg-primary-light disabled:cursor-wait"
            >
                {isCreatingCart ? 'Membuat Keranjang...' : (
                    <>
                        <CartIcon className="h-5 w-5 mr-1" />
                        <PlusIcon className="h-5 w-5 mr-2" />
                        <span>Tambah Keranjang</span>
                    </>
                )}
            </button>
        </div>
    );
};

export default CartView;