/**
 * Field mapping utilities for converting between SuiteCRM snake_case and frontend camelCase
 */

/**
 * Convert snake_case string to camelCase
 */
function snakeToCamel(str: string): string {
  return str.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
}

/**
 * Convert camelCase string to snake_case
 */
function camelToSnake(str: string): string {
  return str.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
}

/**
 * Recursively convert all keys in an object from snake_case to camelCase
 */
export function toCamelCase<T = unknown>(obj: unknown): T {
  if (obj === null || obj === undefined) {
    return obj as T;
  }

  if (Array.isArray(obj)) {
    return obj.map(item => toCamelCase(item)) as T;
  }

  if (typeof obj === 'object' && obj.constructor === Object) {
    const newObj = {} as Record<string, unknown>;
    
    Object.keys(obj as Record<string, unknown>).forEach(key => {
      const camelKey = snakeToCamel(key);
      newObj[camelKey] = toCamelCase((obj as Record<string, unknown>)[key]);
    });
    
    return newObj as T;
  }

  return obj as T;
}

/**
 * Recursively convert all keys in an object from camelCase to snake_case
 */
export function toSnakeCase<T = unknown>(obj: unknown): T {
  if (obj === null || obj === undefined) {
    return obj as T;
  }

  if (Array.isArray(obj)) {
    return obj.map(item => toSnakeCase(item)) as T;
  }

  if (typeof obj === 'object' && obj.constructor === Object) {
    const newObj = {} as Record<string, unknown>;
    
    Object.keys(obj as Record<string, unknown>).forEach(key => {
      const snakeKey = camelToSnake(key);
      newObj[snakeKey] = toSnakeCase((obj as Record<string, unknown>)[key]);
    });
    
    return newObj as T;
  }

  return obj as T;
}

/**
 * Special field mappings that don't follow standard snake_case to camelCase conversion
 */
const SPECIAL_FIELD_MAPPINGS: Record<string, string> = {
  // Add any special cases here if needed
  // For example: 'some_special_field': 'customFieldName'
};

const REVERSE_SPECIAL_FIELD_MAPPINGS: Record<string, string> = Object.fromEntries(
  Object.entries(SPECIAL_FIELD_MAPPINGS).map(([k, v]) => [v, k])
);

/**
 * Convert SuiteCRM field names to frontend field names with special handling
 */
export function mapSuiteCRMToFrontend<T = unknown>(obj: unknown): T {
  if (obj === null || obj === undefined) {
    return obj as T;
  }

  if (Array.isArray(obj)) {
    return obj.map(item => mapSuiteCRMToFrontend(item)) as T;
  }

  if (typeof obj === 'object' && obj.constructor === Object) {
    const newObj = {} as Record<string, unknown>;
    
    Object.keys(obj as Record<string, unknown>).forEach(key => {
      const mappedKey = SPECIAL_FIELD_MAPPINGS[key] || snakeToCamel(key);
      newObj[mappedKey] = mapSuiteCRMToFrontend((obj as Record<string, unknown>)[key]);
    });
    
    return newObj as T;
  }

  return obj as T;
}

/**
 * Convert frontend field names to SuiteCRM field names with special handling
 */
export function mapFrontendToSuiteCRM<T = unknown>(obj: unknown): T {
  if (obj === null || obj === undefined) {
    return obj as T;
  }

  if (Array.isArray(obj)) {
    return obj.map(item => mapFrontendToSuiteCRM(item)) as T;
  }

  if (typeof obj === 'object' && obj.constructor === Object) {
    const newObj = {} as Record<string, unknown>;
    
    Object.keys(obj as Record<string, unknown>).forEach(key => {
      const mappedKey = REVERSE_SPECIAL_FIELD_MAPPINGS[key] || camelToSnake(key);
      newObj[mappedKey] = mapFrontendToSuiteCRM((obj as Record<string, unknown>)[key]);
    });
    
    return newObj as T;
  }

  return obj as T;
}

/**
 * Type guard to check if a value is a valid SuiteCRM record
 */
export function isSuiteCRMRecord(value: unknown): boolean {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as Record<string, unknown>).id === 'string' &&
    typeof (value as Record<string, unknown>).date_entered === 'string' &&
    typeof (value as Record<string, unknown>).date_modified === 'string'
  );
}

/**
 * Type guard to check if a value is a valid frontend record
 */
export function isFrontendRecord(value: unknown): boolean {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as Record<string, unknown>).id === 'string' &&
    typeof (value as Record<string, unknown>).dateEntered === 'string' &&
    typeof (value as Record<string, unknown>).dateModified === 'string'
  );
}