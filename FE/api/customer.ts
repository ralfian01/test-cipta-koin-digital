import { apiClient } from './client';
import { Customer } from '../types';

interface CustomersResponse {
    data: Customer[];
}

export const fetchCustomers = async (keyword?: string): Promise<Customer[]> => {
    let endpoint = '/pos/customers';
    if (keyword && keyword.trim() !== '') {
        endpoint += `?keyword=${encodeURIComponent(keyword)}`;
    }
    const response = await apiClient<CustomersResponse>(endpoint);
    return response.data;
};
