import { RegisterUser } from '@/types';
import { apiClient } from './client';

export const registerUser = async (payload: RegisterUser): Promise<any> => {
  const response = await apiClient(`/account/register`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload)
  });

  return response;
};
