export type DigitalAssetKind = 'catalog_product' | 'custom_lut_build';

export type CustomLutCommerceSettings = {
    enabled: boolean;
    price_cents: number;
    formatted_price: string | null;
    currency: 'EUR';
    version: number;
};

export type CustomLutPurchaseEligibilityState =
    'eligible' | 'owned' | 'resume' | 'stale_build' | 'unavailable';

export type CustomLutPurchaseEligibility = {
    state: CustomLutPurchaseEligibilityState;
    message: string | null;
};

export type CatalogCheckoutItem = {
    kind: 'catalog_product';
    name: string;
    slug: string;
    version: string | null;
};

export type CustomLutCheckoutItem = {
    kind: 'custom_lut_build';
    project_id: string;
    build_id: string;
    name: string;
    style_name: string;
    transform_version: string;
    generator_version: string;
    package_schema_version: string;
    version_label: string;
    prepared_at: string | null;
    package_size_bytes: number | null;
    contents: string[];
};

export type DigitalCheckoutItem = CatalogCheckoutItem | CustomLutCheckoutItem;

export type CustomLutPurchasedItem = {
    id: string;
    name: string;
    style_name: string;
    order_number: string | null;
    order_url: string | null;
    show_url: string;
    download_url: string | null;
    status: string;
    status_label: string;
    purchased_at: string | null;
    version_label: string | null;
    parameter_hash: string;
    transform_version: string | null;
    package_size_bytes: number | null;
    project_url: string | null;
};

export type DigitalOrderItem = {
    kind: DigitalAssetKind;
    kind_label: string;
    name: string;
    version: string | null;
};

export type DigitalEntitlement = {
    id: string;
    kind: DigitalAssetKind;
    status: string;
    status_label: string;
    download_url: string | null;
};

export type DownloadableAssetSummary = {
    kind: DigitalAssetKind;
    name: string;
    version_label: string | null;
    order_number: string | null;
    package_size_bytes: number | null;
};
