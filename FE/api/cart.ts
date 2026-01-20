

import { apiClient } from './client';
import { CartSummary, CreateCartData, CartsSummaryResponse, CartDetails } from '../types';

/**
 * Payload untuk menambahkan item ke keranjang.
 */
export interface AddToCartPayload {
  type: 'CONSUMPTION' | 'RENTAL';
  variant_id: number;
  quantity: number;
}

/**
 * Mengambil ringkasan semua keranjang aktif untuk shift saat ini.
 * Mengembalikan null jika tidak ada keranjang yang aktif.
 */
export const fetchCartsSummary = async (): Promise<CartSummary[] | null> => {
    // Mengharapkan { "data": { "data": [...] } } atau { "data": null }.
    const responseData = await apiClient<CartsSummaryResponse | null>('/pos/carts/summary');
    return responseData ? responseData.data : null;
};

/**
 * Membuat keranjang baru untuk shift saat ini.
 */
export const createCart = async (): Promise<CreateCartData> => {
  // apiClient akan menangani respons 201 dengan body JSON dengan benar.
  return apiClient<CreateCartData>('/pos/carts', {
    method: 'POST',
  });
};

/**
 * Menetapkan pelanggan ke keranjang tertentu.
 * @param cartId ID keranjang yang akan diperbarui.
 * @param customerId ID pelanggan yang akan ditetapkan.
 */
export const assignCustomerToCart = async (cartId: string, customerId: number): Promise<void> => {
  // Menggunakan POST dengan _method tunneling untuk menghindari potensi masalah CORS dengan metode PATCH.
  // "Failed to fetch" seringkali terjadi jika server tidak dikonfigurasi untuk menangani preflight OPTIONS untuk PATCH.
  await apiClient<void>(`/pos/carts/${cartId}`, {
    method: 'POST',
    body: JSON.stringify({ 
      customer_id: customerId,
      _method: 'PATCH' 
    }),
  });
};

/**
 * Melepaskan penetapan pelanggan dari keranjang tertentu.
 * @param cartId ID keranjang yang akan diperbarui.
 */
export const unassignCustomerFromCart = async (cartId: string): Promise<void> => {
  await apiClient<void>(`/pos/carts/${cartId}`, {
    method: 'POST', // Menggunakan tunneling
    body: JSON.stringify({ 
      customer_id: null,
      _method: 'PATCH' 
    }),
  });
};

/**
 * Mengambil detail informasi keranjang berdasarkan ID.
 * @param cartId ID keranjang yang akan diambil detailnya.
 */
export const fetchCartDetails = async (cartId: string): Promise<CartDetails> => {
  return apiClient<CartDetails>(`/pos/carts/${cartId}`);
};

/**
 * Menambahkan item baru ke keranjang.
 * @param cartId ID keranjang tujuan.
 * @param item Payload item yang akan ditambahkan.
 */
export const addItemToCart = async (cartId: string, item: AddToCartPayload): Promise<void> => {
  await apiClient<void>(`/pos/carts/${cartId}/items`, {
    method: 'POST',
    body: JSON.stringify(item),
  });
};

/**
 * Menghapus item dari keranjang.
 * @param cartId ID keranjang.
 * @param itemId ID item yang akan dihapus.
 */
export const removeItemFromCart = async (cartId: string, itemId: number): Promise<void> => {
  await apiClient<void>(`/pos/carts/${cartId}/items/${itemId}`, {
    method: 'DELETE',
  });
};
