import React, { useState, useEffect, useMemo } from 'react';
import { XIcon, MoneyIcon, CreditCardIcon, QrcodeIcon } from './Icons';
import { PaymentMethod, CheckoutPayload } from '../types';
import { fetchPaymentMethods } from '../api/payment';
import { processCheckout } from '../api/checkout';
import { formatRupiah } from '../utils/formatters';

type PaymentType = 'CASH' | 'EDC' | 'QRIS';

interface CheckoutModalProps {
  isOpen: boolean;
  onClose: () => void;
  payableAmount: number;
  cartId: string;
  onSuccess: () => void;
}

const PaymentTypeTab: React.FC<{
  type: PaymentType;
  icon: React.ElementType;
  label: string;
  isActive: boolean;
  onClick: (type: PaymentType) => void;
  disabled: boolean;
}> = ({ type, icon: Icon, label, isActive, onClick, disabled }) => (
  <button
    onClick={() => onClick(type)}
    disabled={disabled}
    className={`flex-1 flex items-center justify-center p-4 border-b-2 font-semibold transition-colors duration-200 disabled:opacity-50 ${
      isActive
        ? 'border-primary text-primary'
        : 'border-transparent text-gray-500 hover:bg-gray-50'
    }`}
  >
    <Icon className="h-5 w-5 mr-2" />
    <span>{label}</span>
  </button>
);

const CheckoutModal: React.FC<CheckoutModalProps> = ({ isOpen, onClose, payableAmount, cartId, onSuccess }) => {
  const [activeTab, setActiveTab] = useState<PaymentType>('CASH');
  const [paymentMethods, setPaymentMethods] = useState<Record<PaymentType, PaymentMethod[]>>({
    CASH: [],
    EDC: [],
    QRIS: [],
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  
  // State untuk input pembayaran
  const [selectedMethodId, setSelectedMethodId] = useState<number | null>(null);
  const [cashReceived, setCashReceived] = useState('');
  const [lastCardNumber, setLastCardNumber] = useState('');

  const [isProcessing, setIsProcessing] = useState(false);


  useEffect(() => {
    if (isOpen) {
      const loadAllMethods = async () => {
        setLoading(true);
        setError('');
        try {
          const [cash, edc, qris] = await Promise.all([
            fetchPaymentMethods('CASH'),
            fetchPaymentMethods('EDC'),
            fetchPaymentMethods('QRIS'),
          ]);
          const methods = { CASH: cash, EDC: edc, QRIS: qris };
          setPaymentMethods(methods);
          
          // Set metode default jika tersedia
          if (methods.CASH.length > 0) {
            setActiveTab('CASH');
            setSelectedMethodId(methods.CASH[0].id);
          } else if (methods.EDC.length > 0) {
            setActiveTab('EDC');
          } else if (methods.QRIS.length > 0) {
            setActiveTab('QRIS');
          }

        } catch (err: any) {
          setError(err.message || 'Gagal memuat metode pembayaran.');
        } finally {
          setLoading(false);
        }
      };
      loadAllMethods();
    } else {
        // Reset state saat ditutup
        setTimeout(() => {
            setActiveTab('CASH');
            setCashReceived('');
            setLastCardNumber('');
            setSelectedMethodId(null);
            setError('');
            setIsProcessing(false);
        }, 300); // Tunggu animasi
    }
  }, [isOpen]);

  const handleTabClick = (type: PaymentType) => {
    setActiveTab(type);
    setSelectedMethodId(null); // Reset pilihan saat ganti tab
    setLastCardNumber('');
    setError('');
  };
  
  const change = useMemo(() => {
    const received = parseFloat(cashReceived.replace(/[^0-9]/g, ''));
    if (isNaN(received) || received < payableAmount) return 0;
    return received - payableAmount;
  }, [cashReceived, payableAmount]);

  const handleCashChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const value = e.target.value.replace(/[^0-9]/g, '');
      setCashReceived(value);
  };
  
  const handleQuickCash = (amount: number) => {
      setCashReceived(amount.toString());
  };
  
  const isConfirmDisabled = useMemo(() => {
    if (isProcessing) return true;
    if (activeTab === 'CASH') {
        if (!selectedMethodId) return true;
        const received = parseFloat(cashReceived.replace(/[^0-9]/g, ''));
        return isNaN(received) || received < payableAmount;
    }
    if (activeTab === 'EDC') {
        if (!selectedMethodId) return true;
        return lastCardNumber.length !== 4;
    }
    if (activeTab === 'QRIS') {
        return !selectedMethodId;
    }
    return true;
  }, [isProcessing, activeTab, selectedMethodId, cashReceived, payableAmount, lastCardNumber]);


  const handleConfirmPayment = async () => {
    if (!cartId || isConfirmDisabled) return;
    
    setIsProcessing(true);
    setError('');

    try {
        // Fix: Explicitly type the payload to allow adding optional properties to `payment`.
        const payload: CheckoutPayload = {
            cart_id: parseInt(cartId, 10),
            payment: {
                payment_method_id: selectedMethodId!,
            }
        };

        if (activeTab === 'CASH') {
            payload.payment.cash_received = parseFloat(cashReceived);
        } else if (activeTab === 'EDC') {
            payload.payment.last_card_number = lastCardNumber;
        }

        await processCheckout(payload);
        onSuccess();

    } catch (err: any) {
        setError(err.message || 'Transaksi gagal.');
    } finally {
        setIsProcessing(false);
    }
  };

  const renderContent = () => {
    if (loading) {
      return <div className="p-8 text-center text-gray-500 animate-pulse">Memuat metode pembayaran...</div>;
    }
    
    const currentMethods = paymentMethods[activeTab];
    
    if (currentMethods.length === 0) {
        return <div className="p-8 text-center text-gray-500">Metode pembayaran {activeTab} tidak tersedia.</div>;
    }

    if (activeTab === 'CASH') {
      const quickCashValues = [
          payableAmount, 
          Math.ceil(payableAmount / 50000) * 50000, 
          Math.ceil(payableAmount / 100000) * 100000
      ].filter((v, i, a) => a.indexOf(v) === i && v > 0 && v !== Infinity);

      return (
        <div className="p-6">
            <label htmlFor="cash-received" className="block font-semibold text-secondary mb-2">Uang Tunai Diterima</label>
            <input 
                id="cash-received"
                type="text"
                value={cashReceived ? new Intl.NumberFormat('id-ID').format(Number(cashReceived)) : ''}
                onChange={handleCashChange}
                placeholder="0"
                className="w-full px-4 py-3 text-2xl font-bold text-right border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                autoFocus
                disabled={isProcessing}
            />
             <div className="grid grid-cols-3 gap-2 mt-3 text-sm">
                {quickCashValues.map(amount => (
                    <button key={amount} onClick={() => handleQuickCash(amount)} disabled={isProcessing} className="bg-gray-100 p-2 rounded-md hover:bg-gray-200 transition-colors">{formatRupiah(amount)}</button>
                ))}
            </div>
            {parseFloat(cashReceived) >= payableAmount && (
                 <div className="mt-4 p-4 bg-green-50 rounded-lg text-center">
                    <p className="text-sm text-green-700">Kembalian</p>
                    <p className="text-2xl font-bold text-green-800">{formatRupiah(change)}</p>
                </div>
            )}
        </div>
      );
    }
    
    return (
        <div className="p-6 max-h-[300px] overflow-y-auto">
            <ul className="space-y-3">
                {currentMethods.map(method => (
                    <li key={method.id}>
                        <button 
                            onClick={() => setSelectedMethodId(method.id)}
                            disabled={isProcessing}
                            className={`w-full text-left p-4 border rounded-lg hover:bg-gray-50 transition-colors ${selectedMethodId === method.id ? 'border-primary bg-primary/5' : 'border-gray-200'}`}
                        >
                            <span className="font-semibold text-secondary">{method.name}</span>
                        </button>
                    </li>
                ))}
                {activeTab === 'EDC' && selectedMethodId && (
                     <div className="pt-4">
                        <label htmlFor="last-card-number" className="block font-semibold text-secondary mb-2">4 Digit Terakhir Kartu</label>
                        <input 
                            id="last-card-number"
                            type="text"
                            value={lastCardNumber}
                            onChange={(e) => setLastCardNumber(e.target.value.replace(/[^0-9]/g, ''))}
                            placeholder="1234"
                            maxLength={4}
                            className="w-full px-4 py-2 text-lg text-center tracking-widest border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                            disabled={isProcessing}
                        />
                    </div>
                )}
            </ul>
        </div>
    );
  };
  
  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300"
      onClick={onClose}
    >
      <div
        className="bg-white rounded-lg shadow-xl w-full max-w-lg m-4 transform transition-all duration-300 flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex justify-between items-center p-4 border-b border-light-border">
          <div>
            <h2 className="text-xl font-bold text-secondary">Pembayaran</h2>
            <p className="text-sm text-gray-500">Total: <span className="font-bold text-secondary">{formatRupiah(payableAmount)}</span></p>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600" disabled={isProcessing}>
            <XIcon className="h-6 w-6" />
          </button>
        </header>

        <nav className="flex border-b border-light-border">
          <PaymentTypeTab type="CASH" icon={MoneyIcon} label="Tunai" isActive={activeTab === 'CASH'} onClick={handleTabClick} disabled={isProcessing} />
          <PaymentTypeTab type="EDC" icon={CreditCardIcon} label="EDC" isActive={activeTab === 'EDC'} onClick={handleTabClick} disabled={isProcessing} />
          <PaymentTypeTab type="QRIS" icon={QrcodeIcon} label="QRIS" isActive={activeTab === 'QRIS'} onClick={handleTabClick} disabled={isProcessing} />
        </nav>
        
        <div className="flex-grow bg-white">
          {renderContent()}
        </div>
        
        <footer className="p-4 bg-gray-50 border-t border-light-border">
            {error && <p className="text-red-500 text-sm text-center mb-3">{error}</p>}
            <button 
                onClick={handleConfirmPayment}
                disabled={isConfirmDisabled}
                className="w-full bg-green-500 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-600 transition-colors duration-300 disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
                {isProcessing ? 'Memproses...' : 'Konfirmasi Pembayaran'}
            </button>
        </footer>
      </div>
    </div>
  );
};

export default CheckoutModal;