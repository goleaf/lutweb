export type PublicMediaKind = 'cover' | 'gallery';

export type PublicMedia = {
    id: number;
    kind: PublicMediaKind;
    url: string;
    alt_text: string;
    width: number | null;
    height: number | null;
};

export type PublicCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    url: string;
    products_count: number;
};

export type PublicTag = {
    id: number;
    name: string;
    slug: string;
    products_count: number;
};

export type PublicCompatibleSoftware = {
    id: number;
    name: string;
    slug: string;
    website_url: string | null;
    products_count: number;
};

export type PublicProductExample = {
    id: number;
    title: string | null;
    before: {
        url: string;
        alt_text: string;
    };
    after: {
        url: string;
        alt_text: string;
    };
};

export type PublicProductCard = {
    id: number;
    type: 'single_lut' | 'bundle' | 'free_lut';
    type_label: string;
    name: string;
    slug: string;
    url: string;
    short_description: string;
    formatted_price: string;
    is_free: boolean;
    currency: 'EUR';
    is_featured: boolean;
    cover: PublicMedia | null;
    categories: PublicCategory[];
};

export type PublicBundleItem = {
    id: number;
    name: string;
    url: string | null;
    cover: PublicMedia | null;
};

export type PublicProductDetail = PublicProductCard & {
    description: string | null;
    can_test_on_photo: boolean;
    test_url: string | null;
    published_at: string | null;
    media: PublicMedia[];
    examples: PublicProductExample[];
    package_contents: string[];
    availability_message: string | null;
    tags: PublicTag[];
    compatible_software: PublicCompatibleSoftware[];
    bundle_items: PublicBundleItem[];
    seo: StorefrontSeo;
};

export type PublicTesterProduct = {
    name: string;
    slug: string;
    url: string;
    try_url: string;
    short_description: string;
    formatted_price: string;
    is_free: boolean;
    cover: {
        url: string;
        alt_text: string;
        width: number | null;
        height: number | null;
    } | null;
};

export type PublicLutTestUpload = {
    id: string;
    status: 'queued' | 'processing' | 'ready' | 'failed' | 'expired';
    original_name: string;
    preview_width: number | null;
    preview_height: number | null;
    created_at: string | null;
    expires_at: string;
    failure_message: string | null;
    before_url: string | null;
    after_url: string | null;
    delete_url: string;
    can_delete: boolean;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Pagination = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

export type PaginatedResource<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: Pagination;
};

export type ResourceCollection<T> =
    | {
          data: T[];
      }
    | T[];

export type StorefrontFilters = {
    q: string | null;
    category: string | null;
    tag: string | null;
    software: string | null;
    type: 'all' | 'single_lut' | 'bundle' | 'free_lut';
    pricing: 'all' | 'free' | 'paid';
    sort: 'featured' | 'newest' | 'price_asc' | 'price_desc' | 'name_asc';
};

export type StorefrontFilterOptions = {
    categories: ResourceCollection<PublicCategory>;
    tags: PublicTag[];
    software: PublicCompatibleSoftware[];
};

export type StorefrontSeo = {
    title: string;
    description: string;
    canonical_url: string;
    image?: string | null;
};
