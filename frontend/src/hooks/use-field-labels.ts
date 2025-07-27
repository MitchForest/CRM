import { useQuery } from '@tanstack/react-query';
import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8080';

export function useFieldLabels(table: string) {
  return useQuery({
    queryKey: ['field-labels', table],
    queryFn: async () => {
      const response = await axios.get(`${API_BASE}/api/schema/validation`);
      const data = response.data;
      return data.rules?.[table]?.field_labels || {};
    },
    staleTime: 60 * 60 * 1000, // Cache for 1 hour
  });
}

export function useFieldLabel(table: string, field: string): string {
  const { data: labels } = useFieldLabels(table);
  return labels?.[field] || field;
}