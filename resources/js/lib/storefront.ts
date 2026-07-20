import type { ResourceCollection, StorefrontFilters } from '@/types/storefront';
import type { QueryParams } from '@/wayfinder';

export function collectionItems<T>(collection: ResourceCollection<T>): T[] {
    return Array.isArray(collection) ? collection : collection.data;
}

export function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next')
        .replace('&laquo;', 'Previous')
        .replace('&raquo;', 'Next')
        .replace('&amp;', '&')
        .trim();
}

export function storefrontQuery(
    filters: StorefrontFilters,
    includeCategory: boolean,
): QueryParams {
    return {
        q: filters.q?.trim() || undefined,
        category:
            includeCategory && filters.category ? filters.category : undefined,
        tag: filters.tag || undefined,
        software: filters.software || undefined,
        type: filters.type === 'all' ? undefined : filters.type,
        pricing: filters.pricing === 'all' ? undefined : filters.pricing,
        sort: filters.sort === 'featured' ? undefined : filters.sort,
    };
}
