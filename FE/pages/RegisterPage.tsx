import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { EyeIcon, EyeOffIcon } from "../components/Icons";
import { loginUser } from "../api/auth";
import { registerUser } from "@/api/account";

const RegisterPage: React.FC<{ onLogin: () => void }> = ({ onLogin }) => {
	const [username, setUsername] = useState("");
	const [password, setPassword] = useState("");
	const [showPassword, setShowPassword] = useState(false);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [errorDetail, setErrorDetail] = useState<any>(null);
	const [registerSuccess, setRegisterSuccess] = useState<boolean>(false);

	const handleSubmit = async (e: React.FormEvent) => {
		e.preventDefault();
		setError(null);
		setErrorDetail(null);

		if (!username || !password) {
			setError("Username dan password harus diisi.");
			return;
		}

		setIsLoading(true);

		try {
			// Verifikasi login dan dapatkan data yang sudah di-unwrap (termasuk token)
			const data = await registerUser({ username, password });

			if (data.code == 201) {
				setRegisterSuccess(true);
			}
		} catch (err: any) {
			if (err?.error_detail) {
				setErrorDetail(err.error_detail);
			} else {
				setError(err.message || "Register akun gagal");
			}
		} finally {
			setIsLoading(false);
		}
	};

	const loginPageStyle: React.CSSProperties = {
		backgroundColor: "#FEF2F2",
		backgroundImage:
			"linear-gradient(rgba(214, 40, 40, 0.07) 1px, transparent 1px), linear-gradient(to right, rgba(214, 40, 40, 0.07) 1px, transparent 1px)",
		backgroundSize: "3rem 3rem",
	};

	const formCardStyle: React.CSSProperties = {
		backgroundColor: "white",
		backgroundImage: `
      linear-gradient(to bottom, rgba(214, 40, 40, 0.2), transparent 30%),
      linear-gradient(rgba(214, 40, 40, 0.07) 1px, transparent 1px),
      linear-gradient(to right, rgba(214, 40, 40, 0.07) 1px, transparent 1px)
    `,
		backgroundSize: "100% 100%, 3rem 3rem, 3rem 3rem",
	};

	return (
		<div
			className="min-h-screen flex items-center justify-center p-4"
			style={loginPageStyle}>
			<div
				className="rounded-2xl shadow-xl p-8 max-w-sm w-full"
				style={formCardStyle}>
				<div className="text-center mb-8">
					<p className="text-primary font-bold">To Do List App</p>
					<h1 className="text-3xl font-bold text-secondary mt-2">
						Daftar Akun Baru
					</h1>
				</div>

				{registerSuccess ? (
					<div className="mb-4 text-center text-green-600 bg-green-100 p-3 rounded-lg text-sm">
						Pendaftaran akun berhasil
					</div>
				) : (
					error && (
						<div className="mb-4 text-center text-red-600 bg-red-100 p-3 rounded-lg text-sm">
							{error}
						</div>
					)
				)}

				<form onSubmit={handleSubmit} noValidate>
					<div className="mb-4">
						<label
							htmlFor="username"
							className="block text-sm font-medium text-gray-700 mb-2">
							Username
						</label>
						<input
							id="username"
							type="text"
							name="username"
							value={username}
							onChange={(e) => setUsername(e.target.value)}
							placeholder="Username"
							className="w-full px-4 py-2 border border-primary rounded-lg focus:outline-none focus:ring-1 focus:ring-primary"
							required
							disabled={isLoading}
						/>
					</div>
					{errorDetail?.username && (
						<div className="text-sm text-red-600 mt-[-10px] mb-4">
							{errorDetail?.username?.[0]}
						</div>
					)}

					<div className="mb-8">
						<label
							htmlFor="password"
							className="block text-sm font-medium text-gray-700 mb-2">
							Password
						</label>
						<div className="relative">
							<input
								id="password"
								name="password"
								type={showPassword ? "text" : "password"}
								value={password}
								onChange={(e) => setPassword(e.target.value)}
								placeholder="Password"
								className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
								required
								disabled={isLoading}
							/>
							<button
								type="button"
								onClick={() => setShowPassword(!showPassword)}
								className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
								aria-label={
									showPassword ? "Sembunyikan password" : "Tampilkan password"
								}
								disabled={isLoading}>
								{showPassword ? (
									<EyeOffIcon className="h-5 w-5" />
								) : (
									<EyeIcon className="h-5 w-5" />
								)}
							</button>
						</div>
					</div>
					{errorDetail?.password && (
						<div className="text-sm text-red-600 mt-[-20px] mb-b">
							{errorDetail?.password?.[0]}
						</div>
					)}

					<button
						type="submit"
						className="w-full bg-primary text-white font-bold py-3 px-4 rounded-lg hover:bg-primary-light transition-colors duration-300 disabled:bg-gray-400 disabled:cursor-not-allowed"
						disabled={isLoading}>
						{isLoading ? "Memproses..." : "Daftar Akun"}
					</button>

					<div className="block mt-5">
						Sudah punya akun?{" "}
						<Link
							to="/login"
							className="text-primary font-bold hover:text-red-400">
							Login Sekarang
						</Link>
					</div>
				</form>
			</div>
		</div>
	);
};

export default RegisterPage;
