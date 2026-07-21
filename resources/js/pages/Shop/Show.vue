<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import BeforeAfterComparison from '@/components/storefront/BeforeAfterComparison.vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import ProductGallery from '@/components/storefront/ProductGallery.vue';
import ResponsivePicture from '@/components/storefront/ResponsivePicture.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { collectionItems } from '@/lib/storefront';
import { home } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
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
const jsonLd = computed(() =>
    props.product.seo.json_ld
        ? JSON.stringify(props.product.seo.json_ld)
        : null,
);
const ogImage = computed(
    () => props.product.seo.og_image ?? props.product.seo.image ?? undefined,
);
</script>

<template>
    <PublicLayout>
        <Head :title="product.seo.title">
            <meta
                head-key="description"
                name="description"
                :content="product.seo.description"
            />
            <meta
                v-if="product.seo.robots"
                head-key="robots"
                name="robots"
                :content="product.seo.robots"
            />
            <link
                head-key="canonical"
                rel="canonical"
                :href="product.seo.canonical_url"
            />
            <meta
                head-key="og:title"
                property="og:title"
                :content="product.seo.og_title ?? product.seo.title"
            />
            <meta
                head-key="og:description"
                property="og:description"
                :content="product.seo.og_description ?? product.seo.description"
            />
            <meta
                head-key="og:type"
                property="og:type"
                :content="product.seo.og_type ?? 'product'"
            />
            <meta
                v-if="ogImage"
                head-key="og:image"
                property="og:image"
                :content="ogImage"
            />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                :content="
                    product.seo.twitter_card ??
                    (product.seo.image ? 'summary_large_image' : 'summary')
                "
            />
            <script v-if="jsonLd" type="application/ld+json">
                {{ jsonLd }}
            </script>
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <nav aria-label="Breadcrumbs" class="text-sm text-stone-600">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li>
                            <Link
                                :href="home()"
                                class="rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                            >
                                Home
                            </Link>
                        </li>
                        <li aria-hidden="true">/</li>
                        <li>
                            <Link
                                :href="shopIndex()"
                                class="rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                            >
                                Shop
                            </Link>
                        </li>
                        <li aria-hidden="true">/</li>
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
                        <div>
                            <h2
                                id="examples-heading"
                                class="text-2xl font-semibold text-stone-950"
                            >
                                Before and after
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                Active examples are prepared by administrators
                                and shown without modifying the source images.
                            </p>
                        </div>
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
                        <h2
                            id="description-heading"
                            class="text-2xl font-semibold text-stone-950"
                        >
                            Description
                        </h2>
                        <p
                            class="mt-3 max-w-3xl text-sm leading-7 whitespace-pre-line text-stone-700"
                        >
                            {{ product.description }}
                        </p>
                    </section>

                    <section aria-labelledby="package-heading">
                        <h2
                            id="package-heading"
                            class="text-2xl font-semibold text-stone-950"
                        >
                            Package contents
                        </h2>
                        <ul
                            v-if="product.package_contents.length > 0"
                            class="mt-4 grid gap-2 sm:grid-cols-2"
                        >
                            <li
                                v-for="item in product.package_contents"
                                :key="item"
                                class="rounded-md border border-stone-200 bg-white px-3 py-2 text-sm font-medium text-stone-700"
                            >
                                {{ item }}
                            </li>
                        </ul>
                        <p
                            v-else
                            class="mt-3 rounded-md bg-stone-100 p-3 text-sm text-stone-600"
                        >
                            {{
                                product.availability_message ??
                                'Package details are being prepared.'
                            }}
                        </p>
                    </section>

                    <section
                        v-if="product.type === 'bundle'"
                        aria-labelledby="bundle-heading"
                    >
                        <h2
                            id="bundle-heading"
                            class="text-2xl font-semibold text-stone-950"
                        >
                            Bundle contents
                        </h2>
                        <ul
                            v-if="product.bundle_items.length > 0"
                            class="mt-4 grid gap-3 sm:grid-cols-2"
                        >
                            <li
                                v-for="item in product.bundle_items"
                                :key="item.id"
                                class="flex items-center gap-3 rounded-lg border border-stone-200 bg-white p-3"
                            >
                                <ResponsivePicture
                                    v-if="item.cover?.image"
                                    :image="item.cover.image"
                                    sizes="64px"
                                    class="size-16 rounded-md object-cover"
                                    loading="lazy"
                                />
                                <img
                                    v-else-if="item.cover?.url"
                                    :src="item.cover.url"
                                    :alt="item.cover.alt_text"
                                    :width="item.cover.width ?? undefined"
                                    :height="item.cover.height ?? undefined"
                                    class="size-16 rounded-md object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <span
                                    v-else
                                    class="size-16 rounded-md bg-[linear-gradient(135deg,#292524,#0f766e)]"
                                    aria-hidden="true"
                                />
                                <Link
                                    v-if="item.url"
                                    :href="item.url"
                                    class="rounded-sm font-semibold text-stone-950 underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                                >
                                    {{ item.name }}
                                </Link>
                                <span
                                    v-else
                                    class="font-semibold text-stone-950"
                                >
                                    {{ item.name }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-stone-600">
                            Bundle components are being prepared.
                        </p>
                    </section>

                    <section aria-labelledby="license-heading">
                        <h2
                            id="license-heading"
                            class="text-2xl font-semibold text-stone-950"
                        >
                            License summary
                        </h2>
                        <p
                            class="mt-3 max-w-3xl text-sm leading-7 text-stone-700"
                        >
                            Customers receive a usage license for this digital
                            product. Intellectual-property rights remain with
                            the store owner.
                        </p>
                    </section>

                    <section aria-labelledby="faq-heading">
                        <h2
                            id="faq-heading"
                            class="text-2xl font-semibold text-stone-950"
                        >
                            FAQ
                        </h2>
                        <div class="mt-4 grid gap-3">
                            <details
                                class="rounded-lg border border-stone-200 bg-white p-4"
                            >
                                <summary
                                    class="cursor-pointer text-sm font-semibold text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                                >
                                    Can I buy this LUT now?
                                </summary>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-600"
                                >
                                    Yes, when the product is available. Checkout
                                    handles one LUT or bundle at a time in EUR.
                                </p>
                            </details>
                            <details
                                class="rounded-lg border border-stone-200 bg-white p-4"
                            >
                                <summary
                                    class="cursor-pointer text-sm font-semibold text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                                >
                                    Can I try it on my photo?
                                </summary>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-600"
                                >
                                    Yes, published testable LUTs can be tried
                                    securely from your verified account.
                                </p>
                            </details>
                        </div>
                    </section>
                </div>

                <aside class="grid h-fit gap-5 lg:sticky lg:top-6">
                    <section
                        class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700"
                            >
                                {{ product.type_label }}
                            </span>
                            <span
                                v-if="product.is_featured"
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"
                            >
                                Featured
                            </span>
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold text-stone-950">
                            {{ product.name }}
                        </h1>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{ product.short_description }}
                        </p>
                        <p class="mt-5 text-2xl font-semibold text-stone-950">
                            {{ product.formatted_price }}
                        </p>
                        <div class="mt-5 grid gap-2">
                            <Link
                                v-if="purchaseHref"
                                :href="purchaseHref"
                                class="rounded-md bg-teal-700 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                {{ purchaseLabel }}
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="rounded-md border border-stone-300 bg-stone-100 px-4 py-2.5 text-sm font-semibold text-stone-500"
                            >
                                Unavailable
                            </button>
                            <p class="text-sm leading-6 text-stone-600">
                                {{ purchaseMessage }}
                            </p>
                            <Link
                                v-if="product.try_url"
                                :href="product.try_url"
                                class="rounded-md border border-stone-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-stone-800 hover:border-teal-700 hover:text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                Try on Your Photo
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="rounded-md border border-stone-300 bg-stone-100 px-4 py-2.5 text-sm font-semibold text-stone-500"
                            >
                                Try on Your Photo
                            </button>
                        </div>
                    </section>

                    <section
                        class="rounded-lg border border-stone-200 bg-white p-5"
                    >
                        <h2 class="text-base font-semibold text-stone-950">
                            Compatible software
                        </h2>
                        <ul
                            v-if="product.compatible_software.length > 0"
                            class="mt-3 grid gap-2 text-sm text-stone-700"
                        >
                            <li
                                v-for="software in product.compatible_software"
                                :key="software.id"
                            >
                                {{ software.name }}
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-stone-600">
                            Compatibility details are being prepared.
                        </p>
                    </section>

                    <section
                        class="rounded-lg border border-stone-200 bg-white p-5"
                    >
                        <h2 class="text-base font-semibold text-stone-950">
                            Categories and tags
                        </h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link
                                v-for="category in product.categories"
                                :key="category.id"
                                :href="categoryShow(category.slug)"
                                class="rounded-full border border-stone-200 px-2.5 py-1 text-xs font-medium text-stone-700 hover:border-teal-700 hover:text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                {{ category.name }}
                            </Link>
                            <span
                                v-for="tag in product.tags"
                                :key="tag.id"
                                class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700"
                            >
                                {{ tag.name }}
                            </span>
                        </div>
                    </section>
                </aside>
            </div>
        </article>

        <section class="border-t border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-semibold text-stone-950">
                    Related LUTs
                </h2>
                <div
                    v-if="relatedProducts.length > 0"
                    class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <ProductCard
                        v-for="productCard in relatedProducts"
                        :key="productCard.id"
                        :product="productCard"
                    />
                </div>
                <p v-else class="mt-3 text-sm text-stone-600">
                    Related published LUTs will appear here when available.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
