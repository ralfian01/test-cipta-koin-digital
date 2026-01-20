import { apiClient } from './client';
import { PaymentMethod } from '../types';

interface PaymentMethodsApiResponse {
    data: PaymentMethod[];
}

/**
 * Mengambil daftar metode pembayaran yang tersedia berdasarkan tipe.
 * @param type Tipe metode pembayaran ('CASH', 'EDC', 'QRIS').
 */
export const fetchPaymentMethods = async (type: 'CASH' | 'EDC' | 'QRIS'): Promise<PaymentMethod[]> => {
  const response = await apiClient<PaymentMethodsApiResponse>(`/pos/payment_methods?type=${type}`);
  return response.data;
};
