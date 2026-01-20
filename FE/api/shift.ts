import { apiClient } from './client';

interface ShiftCheckData {
  has_shift: boolean;
}

export const checkActiveShift = async (): Promise<ShiftCheckData> => {
  return apiClient<{ has_shift: boolean; }>('/pos/shifts');
};

export const startShift = async (pin: string): Promise<void> => {
  await apiClient<void>('/pos/shifts/clock-in', {
    method: 'POST',
    body: JSON.stringify({ pin }),
  });
};

export const endShift = async (pin: string): Promise<void> => {
    await apiClient<void>('/pos/shifts/clock-out', {
        method: 'POST',
        body: JSON.stringify({ pin }),
    });
};
