import React, { useState } from "react";
import { HashRouter, Routes, Route, Navigate } from "react-router-dom";
import MainLayout from "./layouts/MainLayout";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import TodoListPage from "./pages/TodoListPage";

function App() {
	const [isAuthenticated, setIsAuthenticated] = useState(
		() => !!sessionStorage.getItem("auth_token"),
	);

	const handleLogin = () => {
		// Fungsi ini sekarang hanya memicu render ulang. Token di sessionStorage adalah sumber kebenaran.
		setIsAuthenticated(true);
	};

	const handleLogout = () => {
		sessionStorage.removeItem("auth_token");
		setIsAuthenticated(false);
	};

	return (
		<HashRouter>
			<Routes>
				{/* Rute publik untuk login */}
				<Route
					path="/login"
					element={
						isAuthenticated ? (
							<Navigate to="/todo-list" replace />
						) : (
							<LoginPage onLogin={handleLogin} />
						)
					}
				/>
				<Route path="/register" element={<RegisterPage />} />

				{/* Rute yang dilindungi */}
				<Route
					path="/"
					element={
						isAuthenticated ? (
							<MainLayout onLogout={handleLogout} />
						) : (
							<Navigate to="/login" replace />
						)
					}>
					<Route index element={<Navigate to="/todo-list" replace />} />
					<Route path="todo-list" element={<TodoListPage />} />
				</Route>

				{/* Rute fallback untuk mengarahkan ke halaman awal yang benar */}
				<Route path="*" element={<Navigate to="/" replace />} />
			</Routes>
		</HashRouter>
	);
}

export default App;
