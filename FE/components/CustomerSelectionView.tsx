import React, { useState, useEffect, useCallback } from 'react';
import { Customer } from '../types';
import { fetchCustomers } from '../api/customer';
import { ChevronLeftIcon, SearchIcon, CustomersIcon } from './Icons';

// Custom hook untuk debouncing input
function useDebounce(value: string, delay: number) {
  const [debouncedValue, setDebouncedValue] = useState(value);
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);
    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);
  return debouncedValue;
}

interface CustomerSelectionViewProps {
  onBack: () => void;
  onSelectCustomer: (customer: Customer) => void;
  isAssigning: boolean;
}

const CustomerSelectionView: React.FC<CustomerSelectionViewProps> = ({ onBack, onSelectCustomer, isAssigning }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const debouncedSearchTerm = useDebounce(searchTerm, 300);

  const loadCustomers = useCallback(async (keyword: string) => {
    setIsLoading(true);
    setError(null);
    try {
      const result = await fetchCustomers(keyword);
      setCustomers(result);
    } catch (err: any) {
      setError(err.message || 'Gagal memuat daftar pelanggan.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCustomers(debouncedSearchTerm);
  }, [debouncedSearchTerm, loadCustomers]);

  return (
    <div className="w-[400px] bg-white flex flex-col p-6 border-l border-light-border">
      <header className="flex items-center pb-4 border-b border-light-border">
        <button onClick={onBack} className="mr-3 text-gray-500 hover:text-secondary" disabled={isAssigning}>
          <ChevronLeftIcon className="h-6 w-6" />
        </button>
        <div>
            <h2 className="text-lg font-bold text-secondary">Pilih Pelanggan</h2>
            <p className="text-sm text-gray-500">Cari dan pilih pelanggan.</p>
        </div>
      </header>

      <div className="relative my-4">
        <input
            type="text"
            placeholder="Cari nama, email, atau no. telp..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
            autoFocus
            disabled={isAssigning}
        />
        <SearchIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
      </div>

      <div className="flex-grow overflow-y-auto -mr-6 pr-6">
        {isLoading ? (
            <div className="flex items-center justify-center h-full text-gray-500 animate-pulse">Memuat pelanggan...</div>
        ) : error ? (
            <div className="flex items-center justify-center h-full text-red-500 text-center p-4">{error}</div>
        ) : customers.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-gray-500 text-center">
                <CustomersIcon className="w-16 h-16 text-gray-300 mb-4"/>
                <p className="font-semibold">Pelanggan tidak ditemukan</p>
                <p className="text-sm">Coba kata kunci lain atau tambahkan pelanggan baru.</p>
            </div>
        ) : (
            <ul className={isAssigning ? 'opacity-50' : ''}>
                {customers.map(customer => (
                    <li key={customer.id}>
                        <button 
                            onClick={() => onSelectCustomer(customer)}
                            className="w-full text-left p-3 rounded-lg hover:bg-gray-100 transition-colors flex justify-between items-center disabled:cursor-wait"
                            disabled={isAssigning}
                        >
                            <div>
                                <p className="font-semibold text-secondary">{customer.name}</p>
                                <p className="text-sm text-gray-500">{customer.phone_number || customer.email}</p>
                            </div>
                            {isAssigning && (
                                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-primary"></div>
                            )}
                        </button>
                    </li>
                ))}
            </ul>
        )}
      </div>
    </div>
  );
};

export default CustomerSelectionView;
