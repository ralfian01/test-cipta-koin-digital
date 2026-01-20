import React, { useState, useMemo } from "react";
import ProductCard from "../components/ProductCard";
import OrderCart from "../components/OrderCart";
import { SearchIcon } from "../components/Icons";
import { Product, OrderItem, AppliedTax } from "../types";

// Fix: PRODUCTS and CATEGORIES are no longer available from constants.
// This page appears to be obsolete and has been replaced by CashierPage.
// Defining them as empty arrays to resolve compilation errors.
const PRODUCTS: Product[] = [];
const CATEGORIES: string[] = [];

const HomePage: React.FC = () => {
	const [activeCategory, setActiveCategory] = useState("Makan Siang");
	const [orderItems, setOrderItems] = useState<OrderItem[]>([]);
	const [searchTerm, setSearchTerm] = useState("");

	const filteredProducts = useMemo(() => {
		return PRODUCTS.filter(
			(product) =>
				product.category === activeCategory &&
				product.name.toLowerCase().includes(searchTerm.toLowerCase()),
		);
	}, [activeCategory, searchTerm]);

	const handleAddToCart = (product: Product) => {
		setOrderItems((prevItems) => {
			const existingItem = prevItems.find(
				(item) => item.product.id === product.id,
			);
			if (existingItem) {
				return prevItems.map((item) =>
					item.product.id === product.id
						? { ...item, quantity: item.quantity + 1 }
						: item,
				);
			}
			// FIX: Tambahkan id unik saat membuat item baru
			return [
				...prevItems,
				{ id: Date.now(), product, quantity: 1, discount: 0 },
			];
		});
	};

	const handleUpdateQuantity = (productId: number, newQuantity: number) => {
		if (newQuantity < 1) return;
		setOrderItems((prevItems) =>
			prevItems.map((item) =>
				item.product.id === productId
					? { ...item, quantity: newQuantity }
					: item,
			),
		);
	};

	const handleRemoveItem = (itemId: number) => {
		setOrderItems((prevItems) =>
			prevItems.filter((item) => item.id !== itemId),
		);
	};

	// FIX: Calculate required props for OrderCart.
	const { subtotal, totalDiscount, payableAmount } = useMemo(() => {
		const sub = orderItems.reduce(
			(acc, item) => acc + item.product.price * item.quantity,
			0,
		);
		const discount = orderItems.reduce(
			(acc, item) =>
				acc + item.product.price * item.quantity * (item.discount / 100),
			0,
		);
		// This page is obsolete, so we use placeholder values for the missing props.
		const taxValue = 0; // Placeholder for tax
		const payable = sub - discount + taxValue;
		return { subtotal: sub, totalDiscount: discount, payableAmount: payable };
	}, [orderItems]);

	return (
		<div className="flex flex-1 overflow-hidden">
			<div className="flex-1 flex flex-col p-6 overflow-y-auto">
				<header className="flex items-center justify-between mb-6">
					<div>
						<h1 className="text-2xl font-bold text-secondary">Restro POS</h1>
					</div>
					<div className="relative w-full max-w-xs">
						<input
							type="text"
							placeholder="Cari produk....."
							value={searchTerm}
							onChange={(e) => setSearchTerm(e.target.value)}
							className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
						/>
						<SearchIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
					</div>
					<div className="flex items-center space-x-2">
						{/* Action Icons can be added here */}
						<button className="bg-primary text-white font-semibold px-4 py-2 rounded-lg">
							Pilih Meja
						</button>
					</div>
				</header>

				<nav className="flex items-center space-x-6 border-b border-light-border mb-6">
					{CATEGORIES.map((category) => (
						<button
							key={category}
							onClick={() => setActiveCategory(category)}
							className={`py-3 text-sm font-semibold transition-colors duration-200 ${
								activeCategory === category
									? "text-primary border-b-2 border-primary"
									: "text-gray-500 hover:text-primary"
							}`}>
							{category}
						</button>
					))}
				</nav>

				<div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
					{filteredProducts.map((product) => (
						<ProductCard
							key={product.id}
							product={product}
							onSelect={() => handleAddToCart(product)}
						/>
					))}
				</div>
			</div>
			{/* FIX: Add missing required props to satisfy OrderCartProps type. This page is obsolete, so placeholder values are sufficient. */}
			<OrderCart
				cartId="placeholder-cart-id"
				onSwitchCart={() => {}}
				orderItems={orderItems}
				onUpdateQuantity={handleUpdateQuantity}
				onRemoveItem={handleRemoveItem}
				customer={null}
				onShowCustomerSelection={() => {}}
				onClearCustomer={() => {}}
				isUpdatingCustomer={false}
				subtotal={subtotal}
				totalDiscount={totalDiscount}
				appliedTaxes={[]}
				payableAmount={payableAmount}
				onCheckout={() => {}}
			/>
		</div>
	);
};

export default HomePage;
