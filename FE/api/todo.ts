import { apiClient } from './client';
import { ApiResponse, CreateTodo, GetTodoList, Todo, UpdateTodo } from '../types';

export const getTodoList = async (id?: number): Promise<any> => {
  const result = await apiClient<{ data: Todo; }>(`/my/todo/${id ?? ""}`, {
    method: 'GET',
  });

  return result.data;
};

export const createTodo = async (payload: CreateTodo): Promise<any> => {
  const result = await apiClient<any>('/my/todo', {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  return result;
};

export const deleteTodo = async (id: number): Promise<any> => {
  const result = await apiClient<any>(`/my/todo/${id}`, {
    method: 'DELETE',
  });

  return result;
};

export const updateTodo = async (id: number, payload: UpdateTodo): Promise<any> => {
  const result = await apiClient<any>(`/my/todo/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });

  return result;
};

// /**
//  * Menghapus item dari keranjang.
//  * @param cartId ID keranjang.
//  * @param itemId ID item yang akan dihapus.
//  */
// export const removeItemFromCart = async (cartId: string, itemId: number): Promise<void> => {
//   await apiClient<void>(`/ pos / carts / ${ cartId; } /items/${ itemId; } `, {
//     method: 'DELETE',
//   });
// };;
