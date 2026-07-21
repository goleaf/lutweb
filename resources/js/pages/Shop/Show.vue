<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import BeforeAfterComparison from '@/components/storefront/BeforeAfterComparison.vue';
import PackageContentsList from '@/components/storefront/PackageContentsList.vue';
import ProductBundleContents from '@/components/storefront/ProductBundleContents.vue';
import ProductFaq from '@/components/storefront/ProductFaq.vue';
import ProductGallery from '@/components/storefront/ProductGallery.vue';
import ProductMetaPanels from '@/components/storefront/ProductMetaPanels.vue';
import ProductPurchasePanel from '@/components/storefront/ProductPurchasePanel.vue';
import RelatedProductsSection from '@/components/storefront/RelatedProductsSection.vue';
import StorefrontSeoHead from '@/components/storefront/StorefrontSeoHead.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { collectionItems } from '@/lib/storefront';
import { home } from '@/routes';
import { index as shopIndex } from '@/routes/shop';
import type {
    PublicProductCard,
    PublicProductDetail,
    ResourceCollection,
} from '@/types/storefront';

const props = defineProps<{
    product: PublicProductDetail;
    relatedProducts: ResourceCollection<PublicProductCard>;
}>();

const relatedProducts = computed(() => collectionItems(props.relatedProducts));
const purchaseLabel = computed(() => {
    if (props.product.purchase.action === 'owned') {
        return 'Go to My LUTs';
    }

    if (props.product.purchase.action === 'claim') {
        return 'Get Free LUT';
    }

    return props.product.is_free ? 'Get Free LUT' : 'Buy Now';
});
const purchaseHref = computed(() =>
    props.product.purchase.action === 'owned'
        ? props.product.purchase.owned_url
        : props.product.purchase.checkout_url,
);
const purchaseMessage = computed(() => {
    if (props.product.purchase.action === 'owned') {
        return 'You already own this product. Downloads remain available from your account while your entitlement is active.';
    }

    if (props.product.purchase.action === 'buy') {
        return 'A verified account is required. Checkout reviews one product only and uses secure PayPal payment.';
    }

    if (props.product.purchase.action === 'claim') {
        return 'A verified account is required. Free LUTs are claimed without PayPal after legal consent.';
    }

    return (
        props.product.purchase.purchase_unavailable_message ??
        'Checkout is not available for this product right now.'
    );
});
</script>

<template>
    <PublicLayout>
        <StorefrontSeoHead :seo="product.seo" default-og-type="product" />

        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <nav aria-label="Breadcrumbs" class="text-sm text-stone-600">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li>
                            <Link
                                :href="home()"
                                class="inline-flex items-center gap-1.5 rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="home" class="size-3.5" />
                                Home
                            </Link>
                        </li>
                        <li aria-hidden="true">
                            <AppIcon
                                name="chevron-right"
                                class="size-3.5 text-stone-400"
                            />
                        </li>
                        <li>
                            <Link
                                :href="shopIndex()"
                                class="inline-flex items-center gap-1.5 rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="shop" class="size-3.5" />
                                Shop
                            </Link>
                        </li>
                        <li aria-hidden="true">
                            <AppIcon
                                name="chevron-right"
                                class="size-3.5 text-stone-400"
                            />
                        </li>
                        <li aria-current="page">{{ product.name }}</li>
                    </ol>
                </nav>
            </div>
        </section>

        <article class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="grid gap-8">
                    <ProductGallery
                        :media="product.media"
                        :product-name="product.name"
                    />

                    <section
                        v-if="product.examples.length > 0"
                        class="grid gap-4"
                        aria-labelledby="examples-heading"
                    >
                        <SectionHeading
                            heading-id="examples-heading"
                            icon="image"
                            title="Before and after"
                            description="Active examples are prepared by administrators and shown without modifying the source images."
                        />
                        <BeforeAfterComparison
                            v-for="example in product.examples"
                            :key="example.id"
                            :example="example"
                        />
                    </section>

                    <section
                        v-if="product.description"
                        aria-labelledby="description-heading"
                    >
                        <SectionHeading
                            heading-id="description-heading"
                            icon="edit"
                            title="Description"
                        />
                        <p
                            class="mt-3 max-w-3xl text-sm leading-7 whitespace-pre-line text-stone-700"
                        >
                            {{ product.description }}
                        </p>
                    </section>

                    <section aria-labelledby="package-heading">
                        <SectionHeading
                            heading-id="package-heading"
                            icon="package"
                            title="Package contents"
                        />
                        <PackageContentsList
                            class="mt-4"
                            :items="product.package_contents"
                            columns
                            :empty-message="
                                product.availability_message ??
                                'Package details are being prepared.'
                            "
                        />
                    </section>

                    <section
                        v-if="product.type === 'bundle'"
                        aria-labelledby="bundle-heading"
                    >
                        <SectionHeading
                            heading-id="bundle-heading"
                            icon="layers"
                            title="Bundle contents"
                        />
                        <ProductBundleContents :items="product.bundle_items" />
                    </section>

                    <section aria-labelledby="license-heading">
                        <SectionHeading
                            heading-id="license-heading"
                            icon="shield"
                            title="License summary"
                        />
                        <p
                            class="mt-3 max-w-3xl text-sm leading-7 text-stone-700"
                        >
                            Customers receive a usage license for this digital
                            product. Intellectual-property rights remain with
                            the store owner.
                        </p>
                    </section>

                    <ProductFaq />
                </div>

                <aside class="grid h-fit gap-5 lg:sticky lg:top-6">
                    <ProductPurchasePanel
                        :product="product"
                        :purchase-href="purchaseHref"
                        :purchase-label="purchaseLabel"
                        :purchase-message="purchaseMessage"
                    />
                    <ProductMetaPanels :product="product" />
                </aside>
            </div>
        </article>

        <RelatedProductsSection :products="relatedProducts" />
    </PublicLayout>
</template>
