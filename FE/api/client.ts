

import { ApiResponse } from '../types';

const getAuthToken = (): string | null => sessionStorage.getItem('auth_token');

/**
 * Wrapper fetch API terpusat untuk menangani otentikasi dan respons.
 * @param endpoint Path endpoint API (mis. '/pos/shifts').
 * @param options Opsi fetch standar (method, body, dll.).
 * @returns Promise yang resolve dengan data yang sudah di-unwrap dari respons.
 */
export const apiClient = async <T>(endpoint: string, options: RequestInit = {}): Promise<any> => {
  const token = getAuthToken();
  const headers = new Headers(options.headers || {});

  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  if (!headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }

  headers.set('Accept', 'application/json');

  const response = await fetch(`${process.env.API_URL}${endpoint}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({ message: 'Terjadi kesalahan pada server.' }));
    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
  }

  // Menangani respons yang berhasil tetapi tidak memiliki body konten.
  // Ini mencakup 204 No Content dan kasus lain seperti 201 Created tanpa body.
  if (response.status === 204 || response.headers.get('Content-Length') === '0') {
    return null as T;
  }

  const responseData: ApiResponse<T> = await response.json();

  // Memeriksa status sukses dari format API spesifik kita
  if (responseData.status !== 'SUCCESS' && responseData.status !== 'CREATED') {
    throw new Error(responseData.message || 'Respons API menandakan kegagalan.');
  }

  return responseData;
};