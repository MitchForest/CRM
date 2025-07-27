import { useQuery } from '@tanstack/react-query';
import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8080';

export function useEnums(table: string) {
  return useQuery({
    queryKey: ['enums', table],
    queryFn: async () => {
      const response = await axios.get(`${API_BASE}/api/schema/enums`, {
        params: { table }
      });
      return response.data.enums;
    },
    staleTime: 60 * 60 * 1000, // Cache for 1 hour
  });
}

export function useAllEnums() {
  return useQuery({
    queryKey: ['enums', 'all'],
    queryFn: async () => {
      const response = await axios.get(`${API_BASE}/api/schema/enums`);
      return response.data.enums;
    },
    staleTime: 60 * 60 * 1000, // Cache for 1 hour
  });
}