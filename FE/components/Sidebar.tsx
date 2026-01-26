import React, { useState } from "react";
import { Link, NavLink } from "react-router-dom";
import { CashierIcon, LogoutIcon } from "./Icons";
import LogoutConfirmationModal from "./LogoutConfirmationModal";
import { checkActiveShift } from "../api/shift";

const navigationItems = [
	{ path: "/todo-list", icon: CashierIcon, label: "Todo" },
];

const NavItem: React.FC<{ item: (typeof navigationItems)[0] }> = ({ item }) => (
	<NavLink
		to={item.path}
		className={({ isActive }) =>
			`flex flex-col items-center justify-center p-3 rounded-lg transition-colors duration-200 ${
				isActive ? "bg-primary text-white" : "text-gray-500 hover:bg-red-50"
			}`
		}>
		<item.icon className="h-6 w-6" />
		<span className="text-xs text-center mt-1">{item.label}</span>
	</NavLink>
);

interface SidebarProps {
	onLogout: () => void;
}

const Sidebar: React.FC<SidebarProps> = ({ onLogout }) => {
	const [isLogoutModalOpen, setIsLogoutModalOpen] = useState(false);
	const [isCheckingShift, setIsCheckingShift] = useState(false);

	const handleLogoutClick = async () => {
		setIsCheckingShift(true);
		try {
			onLogout();
		} catch (error) {
			console.error("Gagal memeriksa status shift saat logout:", error);
			alert("Gagal memeriksa status shift. Tidak dapat melanjutkan logout.");
		} finally {
			setIsCheckingShift(false);
		}
	};

	return (
		<>
			<aside className="w-30 bg-white flex flex-col items-center p-4 border-r border-light-border">
				<div className="text-primary font-bold text-xl mb-8">To do APP</div>
				<nav className="w-full flex flex-col space-y-4">
					{navigationItems.map((item) => (
						<NavItem key={item.path} item={item} />
					))}
				</nav>
				<div className="mt-auto flex flex-col items-center space-y-4">
					<Link to="/profile" className="flex flex-col items-center">
						<img
							src="https://picsum.photos/seed/avatar/40"
							alt="User Avatar"
							className="w-10 h-10 rounded-full"
						/>
					</Link>
					<button
						onClick={handleLogoutClick}
						className="flex flex-col items-center text-gray-500 hover:text-primary transition-colors disabled:opacity-50 disabled:cursor-wait">
						<LogoutIcon className="h-6 w-6" />
						<span className="text-xs mt-1">
							{isCheckingShift ? "Memeriksa..." : "Keluar"}
						</span>
					</button>
				</div>
			</aside>

			{/* Modal dirender di sini tetapi hanya terlihat jika isOpen true */}
			<LogoutConfirmationModal
				isOpen={isLogoutModalOpen}
				onClose={() => setIsLogoutModalOpen(false)}
				onSuccess={onLogout}
			/>
		</>
	);
};

export default Sidebar;
