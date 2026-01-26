export interface Todo {
    id: string;
    title: string;
    description: string;
    is_done: boolean;
}

export interface GetTodoList {
    id: string;
    title: string;
    description: string;
    is_done: boolean;
}

export interface CreateTodo {
    title: string;
    description: string;
}

export interface UpdateTodo {
    id: number;
    title?: string;
    description?: string;
    is_done?: boolean;
}

export interface DragEndEvent {
    active: { id: string; };
    over: { id: string; } | null;
}

export interface LoginCredentials {
    username?: string;
    password?: string;
}

// Mewakili payload di dalam kunci 'data' dari respons login yang sukses
export interface LoginResponse {
    token: string;
    user: {
        id: string;
        username: string;
    };
}

// Mewakili seluruh objek respons dari API login
export interface LoginApiResponse {
    data: LoginResponse;
    code: number;
    status: string;
    message: string;
}

// Tipe generik untuk respons API standar yang memiliki objek 'data' bersarang
export interface ApiResponse<T> {
    code: number;
    status: string;
    message: string;
    data: T;
}

export interface RegisterUser {
    username: string;
    password: string;
}