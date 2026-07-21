export type PayPalPaymentApproval = {
    orderId: string;
    payerId: string;
    paymentId?: string;
    billingToken?: string;
};

export type PayPalPaymentCancellation = {
    orderId?: string;
};

export type PayPalPaymentError = {
    code?: string;
    message?: string;
};

export type PayPalPaymentSession = {
    start: (
        options: { presentationMode: 'auto' | 'popup' | 'modal' },
        orderPromise: Promise<string>,
    ) => Promise<void>;
    hasReturned: () => boolean;
    resume: () => Promise<void>;
};

export type PayPalSdkInstance = {
    findEligibleMethods: (options: {
        currencyCode: 'EUR';
        amount?: string;
    }) => Promise<{
        isEligible: (method: 'paypal') => boolean;
    }>;
    createPayPalOneTimePaymentSession: (options: {
        onApprove: (data: PayPalPaymentApproval) => Promise<void>;
        onCancel: (data: PayPalPaymentCancellation) => void;
        onError: (error: PayPalPaymentError) => void;
    }) => PayPalPaymentSession;
};

type PayPalNamespace = {
    createInstance: (options: {
        clientId: string;
        components: ['paypal-payments'];
        pageType: 'checkout';
    }) => Promise<PayPalSdkInstance>;
};

declare global {
    interface Window {
        paypal?: PayPalNamespace;
        isBrowserSupportedByPayPal?: () => boolean;
    }

    interface HTMLElementTagNameMap {
        'paypal-button': HTMLElement;
    }
}

let loadingPromise: Promise<PayPalNamespace> | null = null;

export function loadPayPalV6Core(sdkUrl: string): Promise<PayPalNamespace> {
    if (window.paypal) {
        return Promise.resolve(window.paypal);
    }

    if (loadingPromise) {
        return loadingPromise;
    }

    loadingPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            'script[data-paypal-v6-core="true"]',
        );

        if (existing) {
            existing.addEventListener('load', () => resolveLoaded(resolve));
            existing.addEventListener('error', () =>
                reject(new Error('paypal_sdk_load_failed')),
            );

            return;
        }

        const script = document.createElement('script');
        script.src = sdkUrl;
        script.async = true;
        script.dataset.paypalV6Core = 'true';
        script.addEventListener('load', () => resolveLoaded(resolve));
        script.addEventListener('error', () =>
            reject(new Error('paypal_sdk_load_failed')),
        );
        document.head.appendChild(script);
    });

    return loadingPromise;
}

function resolveLoaded(resolve: (paypal: PayPalNamespace) => void): void {
    if (!window.paypal) {
        throw new Error('paypal_sdk_missing');
    }

    resolve(window.paypal);
}
