import React from "react";
import { Outlet } from "react-router-dom";
import Sidebar from "../components/Sidebar";

interface MainLayoutProps {
	onLogout: () => void;
}

const MainLayout: React.FC<MainLayoutProps> = ({ onLogout }) => {
	return (
		<div
			className="flex h-screen bg-light-bg font-sans text-gray-800"
			style={{ background: "white" }}>
			<Sidebar onLogout={onLogout} />
			<main className="flex-1 flex flex-col">
				<div className="h-screen max-h-screen overflow-y-scroll">
					<Outlet />
				</div>
			</main>
		</div>
	);
};

export default MainLayout;
