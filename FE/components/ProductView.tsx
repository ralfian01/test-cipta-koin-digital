
import React, { useState, useMemo, useEffect } from 'react';
import ProductCard from './ProductCard';
import { SearchIcon, FolderIcon, ChevronRightIcon, ChevronLeftIcon } from './Icons';
import { ProductCategory, ApiProduct } from '../types';
import { fetchProductCategories, fetchProducts } from '../api/products';

interface ProductViewProps {
    onProductSelect: (product: ApiProduct) => void;
}

const ProductView: React.FC<ProductViewProps> = ({ onProductSelect }) => {
    const [categories, setCategories] = useState<ProductCategory[]>([]);
    const [selectedCategory, setSelectedCategory] = useState<ProductCategory | null>(null);
    const [categoriesLoading, setCategoriesLoading] = useState(true);
    const [categoriesError, setCategoriesError] = useState<string>('');

    const [apiProducts, setApiProducts] = useState<ApiProduct[]>([]);
    const [productsLoading, setProductsLoading] = useState(false);
    const [productsError, setProductsError] = useState<string>('');
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        const getCategories = async () => {
            try {
                setCategoriesLoading(true);
                const fetchedCategories = await fetchProductCategories();
                
                const totalProducts = fetchedCategories.reduce((sum, cat) => sum + cat.products_count, 0);
                const allProductsCategory: ProductCategory = {
                    id: 0,
                    name: 'Semua Produk',
                    products_count: totalProducts,
                };
                setCategories([allProductsCategory, ...fetchedCategories]);
            } catch (err: any) {
                setCategoriesError(err.message || 'Gagal memuat kategori produk.');
            } finally {
                setCategoriesLoading(false);
            }
        };
        getCategories();
    }, []);

    useEffect(() => {
        if (selectedCategory) {
            const getProducts = async () => {
                try {
                    setProductsLoading(true);
                    setProductsError('');
                    setApiProducts([]); // Kosongkan produk sebelum fetch baru
                    const categoryId = selectedCategory.id === 0 ? undefined : selectedCategory.id;
                    const fetchedApiProducts = await fetchProducts(categoryId);
                    setApiProducts(fetchedApiProducts);
                } catch (err: any) {
                    setProductsError(err.message || `Gagal memuat produk untuk kategori ${selectedCategory.name}.`);
                } finally {
                    setProductsLoading(false);
                }
            };
            getProducts();
        }
    }, [selectedCategory]);

    const filteredProducts = useMemo(() => {
        return apiProducts.filter(product =>
            product.name.toLowerCase().includes(searchTerm.toLowerCase())
        );
    }, [apiProducts, searchTerm]);

    const renderContent = () => {
        if (categoriesLoading) {
            return <div className="flex-1 flex items-center justify-center"><p className="text-lg text-gray-500 animate-pulse">Memuat kategori...</p></div>;
        }
        if (categoriesError) {
            return <div className="flex-1 flex items-center justify-center p-4"><div className="text-center text-red-600 bg-red-100 p-4 rounded-lg shadow-md"><p className="font-bold mb-2">Gagal Memuat Kategori</p><p>{categoriesError}</p></div></div>;
        }
        
        if (selectedCategory) {
            return (
                <>
                    <header className="flex items-center justify-between mb-6">
                         <button 
                            onClick={() => { setSelectedCategory(null); setApiProducts([]); setSearchTerm(''); }} 
                            className="flex items-center text-left group"
                        >
                            <ChevronLeftIcon className="h-6 w-6 mr-2 text-secondary group-hover:text-primary transition-colors duration-200" />
                            <h1 className="text-2xl font-bold text-secondary group-hover:text-primary transition-colors duration-200">{selectedCategory.name}</h1>
                        </button>
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
                    </header>
                    {productsLoading && <div className="flex-1 flex items-center justify-center"><p className="text-lg text-gray-500 animate-pulse">Memuat produk...</p></div>}
                    {productsError && <div className="flex-1 flex items-center justify-center p-4"><div className="text-center text-red-600 bg-red-100 p-4 rounded-lg shadow-md"><p className="font-bold mb-2">Gagal Memuat Produk</p><p>{productsError}</p></div></div>}
                    {!productsLoading && !productsError && (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                            {filteredProducts.map(product => (
                                <ProductCard 
                                    key={product.product_id} 
                                    product={{ id: product.product_id, name: product.name, image_url: '' }} 
                                    onSelect={() => onProductSelect(product)}
                                />
                            ))}
                        </div>
                    )}
                </>
            );
        }

        return (
            <>
                <header className="mb-6">
                    <h1 className="text-2xl font-bold text-secondary">Kategori Produk</h1>
                </header>
                <div className="bg-white rounded-lg border border-light-border">
                    <ul>
                        {categories.map((category, index) => (
                            <li 
                                key={category.id} 
                                className={`flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 ${index < categories.length - 1 ? 'border-b border-light-border' : ''}`}
                                onClick={() => setSelectedCategory(category)}
                            >
                                <div className="flex items-center">
                                    <FolderIcon className="h-5 w-5 mr-3 text-gray-400"/>
                                    <span className="font-medium text-secondary">{category.name}</span>
                                </div>
                                <div className="flex items-center text-gray-500">
                                    <span className="text-sm mr-2">{category.products_count}</span>
                                    <ChevronRightIcon className="h-5 w-5"/>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </>
        );
    };

    return (
        <div className="flex-1 flex flex-col p-6 overflow-y-auto">
            {renderContent()}
        </div>
    )
};

export default ProductView;