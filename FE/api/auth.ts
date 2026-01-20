import { LoginCredentials, LoginResponse, LoginApiResponse } from '../types';

export const loginUser = async (credentials: LoginCredentials): Promise<LoginResponse> => {
  const { username, password } = credentials;
  // btoa tersedia di lingkungan browser untuk encoding Base64
  const basicAuth = btoa(`${username}:${password}`);

  const response = await fetch(`${process.env.API_URL}/auth/account`, {
    method: 'POST',
    headers: {
      'Authorization': `Basic ${basicAuth}`,
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    // Coba parse error dari body, jika gagal, berikan pesan default
    const errorData = await response.json().catch(() => ({ message: 'Terjadi kesalahan saat login.' }));
    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
  }

  const responseData: LoginApiResponse = await response.json();

  // Validasi bahwa respons memiliki struktur yang diharapkan
  if (!responseData.data || !responseData.data.token) {
    throw new Error('Respons login tidak valid dari server.');
  }

  return responseData.data;
};
