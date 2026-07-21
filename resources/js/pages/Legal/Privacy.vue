<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import LegalArticlePage from '@/components/legal/LegalArticlePage.vue';
import type { LegalArticleSection } from '@/components/legal/LegalArticlePage.vue';
import { home, register, terms } from '@/routes';

type PrivacySection = LegalArticleSection;

const effectiveDate = 'July 21, 2026';

const sections: readonly PrivacySection[] = [
    {
        title: '1. What this policy covers',
        paragraphs: [
            'This Privacy Policy explains how LUT Web collects, uses, shares, protects, and retains personal information when you visit the website, create an account, test LUTs on photos, create Custom LUT projects, purchase or claim digital products, contact support, or receive transactional emails.',
            'This policy does not cover PayPal pages, payment frames, or other third-party services that operate under their own privacy notices.',
        ],
    },
    {
        title: '2. Information we collect',
        paragraphs: [
            'We collect account information such as your name, email address, country code, password authentication data, email verification status, legal-acceptance timestamps, and account status.',
            'We collect commerce information needed to process digital orders, including order records, product snapshots, payment status, PayPal order or capture references, entitlement records, download history, and legal-consent versions. We do not store your full card details.',
            'When you use LUT testing or Custom LUT tools, we process uploaded photos, generated previews, project settings, selected style parameters, generated package records, and related technical metadata. These files are used to provide the feature and are not made public by default.',
            'We also collect technical and security information such as request IDs, IP address, user agent, timestamps, logs, rate-limit signals, failed-job or webhook status, and audit events for sensitive administrator actions.',
        ],
    },
    {
        title: '3. How we use information',
        paragraphs: [
            'We use personal information to create and secure accounts, verify email addresses, provide storefront and account pages, process payments, grant and revoke entitlements, generate and deliver digital LUT packages, run image previews, prevent abuse, troubleshoot issues, keep audit records, and provide support.',
            'We use operational metadata to monitor reliability, protect private files, investigate security events, avoid duplicate transactional emails, and confirm that downloads and paid access are handled correctly.',
            'We do not sell customer photos, account data, order data, or Custom LUT project data. We do not use customer photos for public storefront examples unless a separate rights-confirmation workflow explicitly allows public use.',
        ],
    },
    {
        title: '4. Legal bases and consent',
        paragraphs: [
            'Depending on where you live, we may rely on several lawful bases to process personal information: performing a contract with you, taking steps you request before a purchase, complying with legal obligations, protecting legitimate security and operational interests, and using consent where consent is required.',
            'You can choose not to provide certain information, but some features may not work without it. For example, we need an email address for account access and transactional notices, and we need uploaded photos to generate photo-based previews or Custom LUT outputs.',
        ],
    },
    {
        title: '5. Sharing information',
        paragraphs: [
            'We share information only when needed to operate LUT Web, comply with law, protect the service, or complete a transaction. This may include hosting providers, storage providers, email providers, payment providers, logging and security infrastructure, professional advisers, and authorities when legally required.',
            'PayPal receives payment-related information when you start or complete a PayPal checkout. PayPal controls its own payment interface and may process information under its own terms and privacy notice.',
        ],
    },
    {
        title: '6. Cookies and local storage',
        paragraphs: [
            'LUT Web uses essential cookies and browser storage for login sessions, CSRF protection, security, checkout flow, interface state, and features that you request.',
            'The service does not implement advertising cookies, marketing pixels, analytics tracking scripts, or behavioral advertising SDKs. If this changes in the future, the policy and consent flow must be updated before those tools are enabled.',
        ],
    },
    {
        title: '7. Retention',
        paragraphs: [
            'We keep account, order, entitlement, payment, legal-consent, audit, and security records for as long as needed to provide the service, meet legal or tax obligations, resolve disputes, prevent abuse, and maintain accurate purchase history.',
            'Temporary customer uploads, preview files, expired work directories, webhook payloads, and failed partial outputs are designed to be cleaned up according to configured retention and pruning rules. Some records may remain in backups until the backup retention period expires.',
        ],
    },
    {
        title: '8. Your choices and rights',
        paragraphs: [
            'You may request access, correction, deletion, export, restriction, or objection where those rights are available under applicable law. Some data cannot be deleted immediately when it is needed for security, fraud prevention, tax, accounting, legal compliance, or active transaction records.',
            'You can update account details through your account where available. Transactional emails, such as verification, password reset, payment confirmation, download readiness, and account security notices, are not marketing messages.',
        ],
    },
    {
        title: '9. Security',
        paragraphs: [
            'We use technical and organizational safeguards intended to protect personal information, private product files, customer uploads, payment state, audit records, and generated packages. These include private storage, signed or authenticated download flows, request IDs, security headers, rate limits, and limited access to sensitive administrator actions.',
            'No internet service can guarantee absolute security. If you believe your account or data has been compromised, contact LUT Web through the published support address as soon as possible.',
        ],
    },
    {
        title: '10. International use, children, and updates',
        paragraphs: [
            'LUT Web may be accessed from different countries, and service providers may process information in jurisdictions other than your own. Where required, transfers should use appropriate safeguards.',
            'LUT Web is not intended for children. Do not create an account or upload photos if you are not old enough to use online services or digital commerce features under the laws that apply to you.',
            'We may update this Privacy Policy as the service, providers, legal requirements, or operational practices change. The effective date above shows when this version was last updated.',
        ],
    },
];
</script>

<template>
    <LegalArticlePage
        title="Privacy Policy"
        meta-description="Privacy information for LUT Web accounts, checkout, LUT testing, Custom LUT projects, downloads, and operational security."
        :effective-date="effectiveDate"
        intro="This policy is written to be clear about what LUT Web needs in order to run accounts, paid downloads, LUT previews, Custom LUT projects, security checks, and support."
        :sections="sections"
    >
        <template #related-links>
            <Link
                :href="terms()"
                class="inline-flex items-center gap-2 rounded-md bg-stone-950 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="receipt" class="size-4" />
                Terms of Use
            </Link>
            <Link
                :href="register()"
                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="register" class="size-4" />
                Registration
            </Link>
            <Link
                :href="home()"
                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="home" class="size-4" />
                Home
            </Link>
        </template>
    </LegalArticlePage>
</template>
