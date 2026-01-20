import { apiClient } from './client';
import { CheckoutPayload } from '../types';

/**
 * Memproses checkout untuk keranjang yang diberikan.
 * @param payload Payload checkout yang berisi ID keranjang dan detail pembayaran.
 */
export const processCheckout = async (payload: CheckoutPayload): Promise<void> => {
  await apiClient<void>('/pos/checkout', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
};
