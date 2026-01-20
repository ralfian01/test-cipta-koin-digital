import React, { useEffect, useState } from 'react';
import { CartDetails } from '../types';
import { fetchCartDetails } from '../api/cart';
import { formatRupiah } from '../utils/formatters';

interface ReceiptViewProps {
  cartId: string;
  onNewTransaction: () => void;
  outletName: string;
}

const ReceiptView: React.FC<ReceiptViewProps> = ({ cartId, onNewTransaction, outletName }) => {
  const [receiptData, setReceiptData] = useState<CartDetails | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const getReceiptData = async () => {
      try {
        setLoading(true);
        setError('');
        const data = await fetchCartDetails(cartId);
        setReceiptData(data);
      } catch (err: any) {
        setError(err.message || 'Gagal memuat detail transaksi.');
      } finally {
        setLoading(false);
      }
    };
    getReceiptData();
  }, [cartId]);

  const handlePrint = () => {
    window.print();
  };
  
  const receiptContent = () => {
    if (loading) {
      return <div className="p-8 text-center text-gray-500 animate-pulse">Memuat struk...</div>;
    }
    if (error) {
      return <div className="p-8 text-center text-red-500">{error}</div>;
    }
    if (!receiptData) {
      return <div className="p-8 text-center text-gray-500">Data transaksi tidak ditemukan.</div>;
    }

    const transactionDate = new Date(receiptData.updated_at).toLocaleDateString('id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
    const transactionTime = new Date(receiptData.updated_at).toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });

    return (
        <div id="receipt-printable" className="bg-white p-8 max-w-sm w-full mx-auto font-mono text-sm">
            <div className="text-center mb-6">
                <h1 className="text-xl font-bold">Struk Belanja</h1>
                <p>{outletName}</p>
            </div>
            
            <div className="border-t border-b border-dashed border-gray-400 py-4 mb-4">
                <div className="flex justify-between"><span>No. Transaksi:</span> <span>{receiptData.transaction_code}</span></div>
                <div className="flex justify-between"><span>Tanggal:</span> <span>{transactionDate}</span></div>
                <div className="flex justify-between"><span>Waktu:</span> <span>{transactionTime}</span></div>
                <div className="flex justify-between"><span>Pelanggan:</span> <span>{receiptData.customer?.name || 'Umum'}</span></div>
            </div>

            <div className="border-b border-dashed border-gray-400 pb-4 mb-4">
                {receiptData.items.map(item => (
                    <div key={item.id} className="mb-2">
                        <p className="font-semibold">{item.name}</p>
                        <div className="flex justify-between">
                            <span>{parseFloat(item.quantity).toFixed(0)} x @{formatRupiah(parseFloat(item.unit_price))}</span>
                            <span>{formatRupiah(parseFloat(item.subtotal))}</span>
                        </div>
                    </div>
                ))}
            </div>
            
            <div className="space-y-1 mb-4">
                <div className="flex justify-between"><span>Subtotal</span> <span>{formatRupiah(parseFloat(receiptData.subtotal))}</span></div>
                {parseFloat(receiptData.total_discount) > 0 && (
                    <div className="flex justify-between"><span>Diskon</span> <span>-{formatRupiah(parseFloat(receiptData.total_discount))}</span></div>
                )}
                 {receiptData.applied_taxes.map((tax, index) => (
                    <div key={index} className="flex justify-between">
                        <span>{tax.name} ({tax.rate}%)</span> <span>{formatRupiah(tax.amount)}</span>
                    </div>
                ))}
            </div>

            <div className="border-t border-b border-dashed border-gray-400 py-2 mb-4">
                <div className="flex justify-between font-bold text-base">
                    <span>Total</span>
                    <span>{formatRupiah(receiptData.final_total)}</span>
                </div>
            </div>

            {receiptData.payment && (
                 <div className="mb-6">
                    <div className="flex justify-between">
                        <span>{receiptData.payment.payment_method.name}</span>
                        {receiptData.payment.payment_method.type === 'EDC' && receiptData.payment.last_card_number && (
                           <span>**** {receiptData.payment.last_card_number}</span>
                        )}
                    </div>
                 </div>
            )}
           
            <p className="text-center text-xs">Terima kasih telah berbelanja!</p>
        </div>
    );
  };


  return (
    <>
      <div className="flex-1 flex flex-col items-center justify-center p-6 bg-gray-100 receipt-view">
        <div className="bg-white rounded-lg shadow-xl overflow-hidden w-full max-w-sm">
            {receiptContent()}
        </div>
        <div className="flex gap-4 mt-6 print-hidden">
            <button
                onClick={onNewTransaction}
                className="bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors"
            >
                Transaksi Baru
            </button>
            <button
                onClick={handlePrint}
                className="bg-green-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-600 transition-colors"
            >
                Cetak Struk
            </button>
        </div>
      </div>
      <style>{`
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-view {
                background-color: white !important;
            }
            #receipt-printable, #receipt-printable * {
                visibility: visible;
            }
            #receipt-printable {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                font-size: 10pt; /* Ukuran font yang lebih sesuai untuk cetak */
            }
            .print-hidden {
                display: none;
            }
        }
      `}</style>
    </>
  );
};

export default ReceiptView;
