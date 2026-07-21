<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import PublicLayout from '@/layouts/PublicLayout.vue';
import { loadPayPalV6Core } from '@/lib/paypal-v6';
import type {
    PayPalPaymentCancellation,
    PayPalPaymentError,
    PayPalPaymentSession,
} from '@/lib/paypal-v6';
import { capture as captureOrder } from '@/routes/account/orders/paypal';
import type {
    CustomLutCheckoutItem,
    CustomLutPurchaseEligibilityState,
} from '@/types/commerce';

type Pricing = {
    currency: 'EUR';
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    subtotal: string | null;
    tax: string;
    total: string | null;
};

type Legal = {
    terms_url: string;
    license_url: string;
    refund_policy_url: string;
    terms_of_sale_version: string;
    license_version: string;
    refund_policy_version: string;
    digital_delivery_consent_version: string;
};

type PayPalConfig = {
    client_id: string | null;
    sdk_url: string | null;
    mode: 'sandbox' | 'live';
    currency: 'EUR';
    brand_name: string;
};

type PayPalState =
    | 'idle'
    | 'loading'
    | 'ready'
    | 'unavailable'
    | 'processing'
    | 'cancelled'
    | 'failed'
    | 'completed';

type CreateOrderResponse = {
    local_order_id: string;
    local_order_number: string;
    paypal_order_id: string;
    status: string;
};

type CaptureResponse = {
    order_url: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
};

function isTerminalPayPalState(state: PayPalState): boolean {
    return state === 'cancelled' || state === 'completed';
}

const props = defineProps<{
    state: CustomLutPurchaseEligibilityState;
    message: string | null;
    item: CustomLutCheckoutItem;
    pricing: Pricing;
    legal: Legal;
    paypal: PayPalConfig;
    account: { email: string | null };
    links: {
        editor: string;
        create_order: string;
        my_custom_luts: string;
    };
}>();

const termsAndLicenseAccepted = ref(false);
const digitalDeliveryAccepted = ref(false);
const paypalState = ref<PayPalState>('idle');
const statusMessage = ref<string | null>(null);
const paypalButton = ref<HTMLElement | null>(null);
const paypalSession = ref<PayPalPaymentSession | null>(null);
const localOrderId = ref<string | null>(null);
const checkoutIdempotencyKey = ref(crypto.randomUUID());

const canPay = computed(
    () => props.state === 'eligible' || props.state === 'resume',
);
const consentsReady = computed(
    () => termsAndLicenseAccepted.value && digitalDeliveryAccepted.value,
);
const canStartPayPal = computed(
    () =>
        canPay.value &&
        consentsReady.value &&
        paypalState.value === 'ready' &&
        paypalSession.value !== null,
);

let clickHandler: (() => void) | null = null;

onMounted(() => {
    if (canPay.value) {
        void initializePayPal();
    }
});

onUnmounted(() => {
    if (clickHandler && paypalButton.value) {
        paypalButton.value.removeEventListener('click', clickHandler);
    }
});

async function initializePayPal(): Promise<void> {
    if (!props.paypal.client_id || !props.paypal.sdk_url) {
        paypalState.value = 'unavailable';
        statusMessage.value = 'PayPal checkout is not available yet.';

        return;
    }

    if (window.isBrowserSupportedByPayPal?.() === false) {
        paypalState.value = 'unavailable';
        statusMessage.value =
            'This browser cannot start PayPal checkout. Please use a current browser.';

        return;
    }

    paypalState.value = 'loading';

    try {
        const paypal = await loadPayPalV6Core(props.paypal.sdk_url);
        const instance = await paypal.createInstance({
            clientId: props.paypal.client_id,
            components: ['paypal-payments'],
            pageType: 'checkout',
        });
        const methods = await instance.findEligibleMethods({
            currencyCode: 'EUR',
            amount: centsToDecimal(props.pricing.total_cents),
        });

        if (!methods.isEligible('paypal')) {
            paypalState.value = 'unavailable';
            statusMessage.value = 'PayPal is not available for this checkout.';

            return;
        }

        paypalSession.value = instance.createPayPalOneTimePaymentSession({
            onApprove: async ({ orderId }) => {
                await captureApprovedOrder(orderId);
            },
            onCancel: (data: PayPalPaymentCancellation) => {
                paypalState.value = 'cancelled';
                statusMessage.value = data.orderId
                    ? 'PayPal checkout was cancelled before payment completed.'
                    : 'PayPal checkout was cancelled.';
            },
            onError: (error: PayPalPaymentError) => {
                paypalState.value = 'failed';
                statusMessage.value =
                    error.code === 'INSTRUMENT_DECLINED'
                        ? 'PayPal declined that funding source. Please try another PayPal funding method.'
                        : 'PayPal checkout could not be completed. Please try again.';
            },
        });

        if (paypalSession.value.hasReturned()) {
            await paypalSession.value.resume();
        }

        clickHandler = () => {
            void startPayPal();
        };
        paypalButton.value?.addEventListener('click', clickHandler);
        paypalState.value = 'ready';
        statusMessage.value = null;
    } catch {
        paypalState.value = 'failed';
        statusMessage.value = 'PayPal checkout could not be loaded.';
    }
}

async function startPayPal(): Promise<void> {
    if (!canStartPayPal.value || !paypalSession.value) {
        statusMessage.value =
            'Accept the required legal terms before starting PayPal checkout.';

        return;
    }

    paypalState.value = 'processing';
    statusMessage.value = 'Opening PayPal checkout.';

    try {
        await paypalSession.value.start(
            { presentationMode: 'auto' },
            createLocalPayPalOrder(),
        );
    } catch {
        if (!isTerminalPayPalState(paypalState.value)) {
            paypalState.value = 'failed';
            statusMessage.value =
                'PayPal checkout could not be completed. Please try again.';
        }
    }
}

async function createLocalPayPalOrder(): Promise<string> {
    const response = await fetch(props.links.create_order, {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(consentPayload()),
    });

    if (!response.ok) {
        throw new Error('paypal_create_failed');
    }

    const payload = toCreateOrderResponse(await response.json());
    localOrderId.value = payload.local_order_id;

    return payload.paypal_order_id;
}

async function captureApprovedOrder(paypalOrderId: string): Promise<void> {
    if (!localOrderId.value) {
        throw new Error('local_order_missing');
    }

    statusMessage.value = 'Confirming payment.';

    const response = await fetch(
        captureOrder({ order: localOrderId.value }).url,
        {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonHeaders(),
            body: JSON.stringify({ paypal_order_id: paypalOrderId }),
        },
    );

    if (!response.ok) {
        throw new Error('paypal_capture_failed');
    }

    const payload = toCaptureResponse(await response.json());
    paypalState.value =
        payload.payment_status === 'completed' &&
        payload.fulfillment_status === 'ready'
            ? 'completed'
            : 'processing';
    router.visit(payload.order_url);
}

function consentPayload(): {
    checkout_idempotency_key: string;
    terms_of_sale_accepted: boolean;
    license_accepted: boolean;
    digital_delivery_consent_accepted: boolean;
} {
    return {
        checkout_idempotency_key: checkoutIdempotencyKey.value,
        terms_of_sale_accepted: termsAndLicenseAccepted.value,
        license_accepted: termsAndLicenseAccepted.value,
        digital_delivery_consent_accepted: digitalDeliveryAccepted.value,
    };
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN':
            document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '',
    };
}

function centsToDecimal(cents: number): string {
    return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
}

function packageSize(bytes: number | null): string {
    if (bytes === null || bytes <= 0) {
        return 'Package size pending';
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function toCreateOrderResponse(value: unknown): CreateOrderResponse {
    if (typeof value === 'object' && value !== null) {
        const record = value as Record<string, unknown>;

        if (
            typeof record.local_order_id === 'string' &&
            typeof record.local_order_number === 'string' &&
            typeof record.paypal_order_id === 'string' &&
            typeof record.status === 'string'
        ) {
            return {
                local_order_id: record.local_order_id,
                local_order_number: record.local_order_number,
                paypal_order_id: record.paypal_order_id,
                status: record.status,
            };
        }
    }

    throw new Error('invalid_create_order_response');
}

function toCaptureResponse(value: unknown): CaptureResponse {
    if (typeof value === 'object' && value !== null) {
        const record = value as Record<string, unknown>;

        if (
            typeof record.order_url === 'string' &&
            typeof record.status === 'string' &&
            typeof record.payment_status === 'string' &&
            typeof record.fulfillment_status === 'string'
        ) {
            return {
                order_url: record.order_url,
                status: record.status,
                payment_status: record.payment_status,
                fulfillment_status: record.fulfillment_status,
            };
        }
    }

    throw new Error('invalid_capture_response');
}
</script>

<template>
    <PublicLayout>
        <Head :title="`Custom LUT Checkout - ${item.name}`">
            <meta name="robots" content="noindex,nofollow" />
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    :href="links.editor"
                    class="rounded-sm text-sm text-stone-600 underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    Back to Custom LUT editor
                </Link>
                <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                    Custom LUT Checkout
                </h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-600">
                    This purchase contains the exact immutable LUT package shown
                    here. Future edits to your project create a separate build.
                </p>
            </div>
        </section>

        <section
            class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8"
        >
            <div class="space-y-5">
                <section
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-teal-800 uppercase"
                    >
                        Custom LUT package
                    </p>
                    <h2 class="mt-2 text-xl font-semibold text-stone-950">
                        {{ item.name }}
                    </h2>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-stone-500">Style</dt>
                            <dd class="font-medium text-stone-900">
                                {{ item.style_name || 'Neutral' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">Build</dt>
                            <dd class="font-medium text-stone-900">
                                {{ item.version_label }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">Transform</dt>
                            <dd class="font-medium text-stone-900">
                                {{ item.transform_version }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">Package</dt>
                            <dd class="font-medium text-stone-900">
                                {{ packageSize(item.package_size_bytes) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <h2 class="text-base font-semibold text-stone-950">
                        Generated package contents
                    </h2>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="content in item.contents"
                            :key="content"
                            class="rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-700"
                        >
                            {{ content }}
                        </li>
                    </ul>
                    <p class="mt-4 text-sm leading-6 text-stone-600">
                        The package was prepared as an immutable build and will
                        not change after purchase.
                    </p>
                </section>

                <section
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <h2 class="text-base font-semibold text-stone-950">
                        Legal consent
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        All sales of digital products are final except where a
                        refund or another remedy is required by applicable law.
                    </p>
                    <p class="mt-2 text-xs leading-5 text-stone-500">
                        Terms version {{ legal.terms_of_sale_version }}. License
                        version {{ legal.license_version }}. Refund Policy
                        version {{ legal.refund_policy_version }}. Digital
                        delivery consent version
                        {{ legal.digital_delivery_consent_version }}.
                    </p>

                    <label class="mt-4 flex gap-3 text-sm text-stone-700">
                        <input
                            v-model="termsAndLicenseAccepted"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-700"
                        />
                        <span>
                            I agree to the
                            <Link
                                :href="legal.terms_url"
                                class="font-medium text-teal-800 underline-offset-4 hover:underline"
                                >Terms of Sale</Link
                            >
                            and
                            <Link
                                :href="legal.license_url"
                                class="font-medium text-teal-800 underline-offset-4 hover:underline"
                                >License Agreement</Link
                            >.
                        </span>
                    </label>

                    <label class="mt-4 flex gap-3 text-sm text-stone-700">
                        <input
                            v-model="digitalDeliveryAccepted"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-700"
                        />
                        <span>
                            I request immediate access to this digital product
                            and acknowledge that, where permitted by applicable
                            law, I lose my withdrawal right once digital
                            delivery begins.
                        </span>
                    </label>

                    <p class="mt-4 text-xs leading-5 text-stone-500">
                        Refund Policy:
                        <Link
                            :href="legal.refund_policy_url"
                            class="font-medium text-teal-800 underline-offset-4 hover:underline"
                            >review policy</Link
                        >.
                    </p>
                </section>
            </div>

            <aside
                class="h-fit rounded-lg border border-stone-200 bg-white p-5"
            >
                <h2 class="text-base font-semibold text-stone-950">Order</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-600">Subtotal</dt>
                        <dd class="font-medium text-stone-950">
                            EUR {{ pricing.subtotal ?? '0.00' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-600">Tax</dt>
                        <dd class="font-medium text-stone-950">
                            EUR {{ pricing.tax }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between gap-4 border-t border-stone-200 pt-3 text-base"
                    >
                        <dt class="font-semibold text-stone-950">Total</dt>
                        <dd class="font-semibold text-stone-950">
                            EUR {{ pricing.total ?? '0.00' }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs leading-5 text-stone-500">
                    One checkout contains one immutable Custom LUT build.
                    Quantity is always 1.
                </p>

                <div class="mt-5">
                    <Link
                        v-if="state === 'owned'"
                        :href="links.my_custom_luts"
                        class="block rounded-md bg-stone-950 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Go to My Custom LUTs
                    </Link>

                    <div v-else-if="canPay" class="space-y-3">
                        <p
                            v-if="state === 'resume'"
                            class="rounded-md bg-stone-100 px-3 py-2 text-sm text-stone-700"
                        >
                            Resuming your existing pending checkout for this
                            exact build.
                        </p>
                        <p
                            v-if="!consentsReady"
                            class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            Accept the required legal terms to enable PayPal.
                        </p>
                        <paypal-button
                            ref="paypalButton"
                            type="pay"
                            class="paypal-gold block w-full"
                            :hidden="!canStartPayPal"
                        />
                        <p
                            v-if="paypalState === 'loading'"
                            class="text-sm text-stone-600"
                            role="status"
                            aria-live="polite"
                        >
                            Loading PayPal.
                        </p>
                    </div>

                    <p
                        v-else
                        class="rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-600"
                    >
                        {{
                            message ??
                            'Custom LUT purchasing is currently unavailable.'
                        }}
                    </p>
                </div>

                <p
                    v-if="statusMessage"
                    class="mt-4 rounded-md bg-stone-100 px-3 py-2 text-sm text-stone-700"
                    role="status"
                    aria-live="polite"
                >
                    {{ statusMessage }}
                </p>

                <p class="mt-4 text-xs leading-5 text-stone-500">
                    Account: {{ account.email }}
                </p>
            </aside>
        </section>
    </PublicLayout>
</template>
