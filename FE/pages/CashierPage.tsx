import React, { useState, useEffect, useCallback } from "react";
import StartShiftForm from "../components/StartShiftForm";
import VariantSelectionModal from "../components/VariantSelectionModal";
import Toast from "../components/Toast";
import {
	OrderItem,
	ApiProduct,
	Variant,
	CartDetails,
	ApiCartItem,
} from "../types";
import { checkActiveShift, startShift } from "../api/shift";
import {
	addItemToCart,
	removeItemFromCart,
	fetchCartDetails,
} from "../api/cart";
import ProductView from "../components/ProductView";
import CartView from "../components/CartView";
import CheckoutModal from "../components/CheckoutModal";
import ReceiptView from "../components/ReceiptView";

type ShiftStatus = "checking" | "active" | "inactive" | "error";
type ToastState = {
	message: string;
	type: "error" | "info" | "success";
} | null;

const mapCartDetailsToOrderItems = (items: ApiCartItem[]): OrderItem[] => {
	return items.map((item: ApiCartItem): OrderItem => {
		const unitPrice = parseFloat(item.unit_price);
		const quantity = parseFloat(item.quantity);

		return {
			id: item.id,
			product: {
				id: item.product_id,
				name: item.name,
				price: unitPrice,
				image_url: "",
				category: "",
			},
			quantity: quantity,
			discount: 0,
		};
	});
};

const TodoListPage: React.FC = () => {
	const [shiftStatus, setShiftStatus] = useState<ShiftStatus>("checking");
	const [shiftError, setShiftError] = useState<string>("");
	const [isStartingShift, setIsStartingShift] = useState(false);

	// State yang diangkat dari CartView
	const [currentCartId, setCurrentCartId] = useState<string | null>(() =>
		sessionStorage.getItem("pos_current_cart_id"),
	);
	const [completedCartId, setCompletedCartId] = useState<string | null>(null);
	const [cartRefreshTrigger, setCartRefreshTrigger] = useState(0);
	const [cartDetails, setCartDetails] = useState<CartDetails | null>(null);
	const [cartDetailsLoading, setCartDetailsLoading] = useState(false);
	const [cartDetailsError, setCartDetailsError] = useState<string>("");
	const [orderItems, setOrderItems] = useState<OrderItem[]>([]);

	// State untuk modal
	const [productForVariantSelection, setProductForVariantSelection] =
		useState<ApiProduct | null>(null);
	const [isCheckoutModalOpen, setIsCheckoutModalOpen] = useState(false);

	const [isAddingItem, setIsAddingItem] = useState(false);
	const [toast, setToast] = useState<ToastState>(null);

	useEffect(() => {
		const verifyShift = async () => {
			try {
				const data = await checkActiveShift();
				setShiftStatus(data.has_shift ? "active" : "inactive");
			} catch (err: any) {
				setShiftStatus("error");
				setShiftError(err.message || "Gagal memverifikasi status shift.");
			}
		};
		verifyShift();
	}, []);

	useEffect(() => {
		if (currentCartId) {
			const getCartDetails = async () => {
				setCartDetailsError("");
				const cachedDetailsRaw = sessionStorage.getItem(
					`pos_cart_details_${currentCartId}`,
				);
				let hasCache = false;
				if (cachedDetailsRaw) {
					try {
						const cachedDetails: CartDetails = JSON.parse(cachedDetailsRaw);
						setCartDetails(cachedDetails);
						setOrderItems(mapCartDetailsToOrderItems(cachedDetails.items));
						hasCache = true;
					} catch (e) {
						sessionStorage.removeItem(`pos_cart_details_${currentCartId}`);
					}
				}

				if (!hasCache) setCartDetailsLoading(true);

				try {
					const details = await fetchCartDetails(currentCartId);
					setCartDetails(details);
					sessionStorage.setItem(
						`pos_cart_details_${currentCartId}`,
						JSON.stringify(details),
					);
					setOrderItems(mapCartDetailsToOrderItems(details.items));
				} catch (err: any) {
					setCartDetailsError(err.message || "Gagal memuat detail keranjang.");
					if (!hasCache) {
						setOrderItems([]);
						setCartDetails(null);
					}
				} finally {
					setCartDetailsLoading(false);
				}
			};
			getCartDetails();
		} else {
			setCartDetails(null);
			setOrderItems([]);
		}
	}, [currentCartId, cartRefreshTrigger]);

	const handleStartShift = async (pin: string) => {
		setIsStartingShift(true);
		setShiftError("");
		try {
			await startShift(pin);
			setShiftStatus("active");
		} catch (err: any) {
			setShiftError(err.message || "Gagal memulai shift. Silakan coba lagi.");
		} finally {
			setIsStartingShift(false);
		}
	};

	const handleProductSelect = (product: ApiProduct) => {
		if (!currentCartId) {
			setToast({
				message: "Silakan pilih atau buat keranjang terlebih dahulu.",
				type: "error",
			});
			return;
		}

		if (product.product_type === "CONSUMPTION") {
			if (!product.variants || product.variants.length === 0) {
				setToast({
					message: "Produk ini tidak memiliki varian yang tersedia.",
					type: "error",
				});
				return;
			}
			setProductForVariantSelection(product);
		} else {
			setToast({
				message: `Tipe produk ${product.product_type} belum didukung.`,
				type: "info",
			});
		}
	};

	const handleVariantSelected = async (variant: Variant) => {
		if (!currentCartId || !productForVariantSelection) return;

		setIsAddingItem(true);
		try {
			await addItemToCart(currentCartId, {
				type: productForVariantSelection.product_type,
				variant_id: variant.variant_id,
				quantity: 1,
			});
			setProductForVariantSelection(null);
			setCartRefreshTrigger((prev) => prev + 1);
		} catch (error: any) {
			setToast({
				message: `Gagal menambahkan item: ${error.message}`,
				type: "error",
			});
		} finally {
			setIsAddingItem(false);
		}
	};

	const handleSetCurrentCartId = (id: string | null) => {
		if (id) {
			sessionStorage.setItem("pos_current_cart_id", id);
		} else {
			sessionStorage.removeItem("pos_current_cart_id");
		}
		setCurrentCartId(id);
	};

	const handleUpdateQuantity = (productId: number, newQuantity: number) => {
		if (newQuantity < 1) return;
		console.log(
			`Memperbarui kuantitas produk ${productId} menjadi ${newQuantity}`,
		);
	};

	const handleRemoveItem = async (itemId: number) => {
		if (!currentCartId) return;
		try {
			await removeItemFromCart(currentCartId, itemId);
			setCartRefreshTrigger((prev) => prev + 1);
		} catch (error: any) {
			setToast({
				message: `Gagal menghapus item: ${error.message}`,
				type: "error",
			});
		}
	};

	const handleCheckout = () => {
		if (cartDetails && orderItems.length > 0) {
			setIsCheckoutModalOpen(true);
		} else {
			setToast({
				message: "Keranjang kosong, tidak bisa checkout.",
				type: "error",
			});
		}
	};

	const handleCheckoutSuccess = () => {
		setIsCheckoutModalOpen(false);
		setToast({ message: "Transaksi berhasil diselesaikan!", type: "success" });

		// Simpan ID keranjang yang selesai untuk ditampilkan di struk
		setCompletedCartId(currentCartId);

		// Reset keranjang saat ini dan picu pembaruan daftar keranjang
		handleSetCurrentCartId(null);
		setCartRefreshTrigger((prev) => prev + 1);
	};

	const handleNewTransaction = () => {
		setCompletedCartId(null);
	};

	if (shiftStatus === "checking") {
		return (
			<div className="flex-1 flex items-center justify-center">
				<p className="text-lg text-gray-500 animate-pulse">
					Memeriksa status shift...
				</p>
			</div>
		);
	}
	if (shiftStatus === "error") {
		return (
			<div className="flex-1 flex items-center justify-center p-4">
				<div className="text-center text-red-600 bg-red-100 p-4 rounded-lg shadow-md">
					<p className="font-bold mb-2">Terjadi Kesalahan</p>
					<p>{shiftError}</p>
				</div>
			</div>
		);
	}
	if (shiftStatus === "inactive") {
		return (
			<StartShiftForm
				onStartShift={handleStartShift}
				isLoading={isStartingShift}
				error={shiftError}
			/>
		);
	}

	if (completedCartId) {
		return (
			<ReceiptView
				cartId={completedCartId}
				onNewTransaction={handleNewTransaction}
				outletName="Toko Serba Ada"
			/>
		);
	}

	return (
		<>
			{toast && (
				<Toast
					message={toast.message}
					type={toast.type}
					onClose={() => setToast(null)}
				/>
			)}
			<div className="flex flex-1 overflow-hidden">
				<ProductView onProductSelect={handleProductSelect} />
				<CartView
					currentCartId={currentCartId}
					setCurrentCartId={handleSetCurrentCartId}
					refreshTrigger={cartRefreshTrigger}
					cartDetails={cartDetails}
					cartDetailsLoading={cartDetailsLoading}
					cartDetailsError={cartDetailsError}
					orderItems={orderItems}
					onUpdateQuantity={handleUpdateQuantity}
					onRemoveItem={handleRemoveItem}
					onCheckout={handleCheckout}
				/>
			</div>
			<VariantSelectionModal
				isOpen={!!productForVariantSelection}
				onClose={() => setProductForVariantSelection(null)}
				product={productForVariantSelection}
				onSelect={handleVariantSelected}
				isAdding={isAddingItem}
			/>
			{currentCartId && (
				<CheckoutModal
					isOpen={isCheckoutModalOpen}
					onClose={() => setIsCheckoutModalOpen(false)}
					payableAmount={cartDetails?.final_total || 0}
					cartId={currentCartId}
					onSuccess={handleCheckoutSuccess}
				/>
			)}
		</>
	);
};

export default CashierPage;
