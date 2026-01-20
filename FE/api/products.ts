
import { apiClient } from './client';
import { ProductCategory, ApiProduct } from '../types';

interface CategoriesResponse {
    data: ProductCategory[];
}

export const fetchProductCategories = async (): Promise<ProductCategory[]> => {
  const response = await apiClient<CategoriesResponse>('/pos/product-categories');
  return response.data;
};

interface ProductsResponse {
    data: ApiProduct[];
}

export const fetchProducts = async (categoryId?: number): Promise<ApiProduct[]> => {
    let endpoint = '/pos/products';
    // Jika categoryId diberikan dan bukan 0 (untuk "Semua Produk"), tambahkan sebagai query param
    if (categoryId) {
        endpoint += `?category_id=${categoryId}`;
    }
    const response = await apiClient<ProductsResponse>(endpoint);
    return response.data;
};
