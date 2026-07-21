<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import LegalArticlePage from '@/components/legal/LegalArticlePage.vue';
import type { LegalArticleSection } from '@/components/legal/LegalArticlePage.vue';
import { home, privacy, register, termsOfSale } from '@/routes';

type LegalSection = LegalArticleSection;

const effectiveDate = 'July 21, 2026';

const sections: readonly LegalSection[] = [
    {
        title: '1. Using LUT Web',
        paragraphs: [
            'LUT Web provides a website, customer account area, photo preview tools, Custom LUT tools, and downloadable digital LUT products for photographers, filmmakers, editors, and other creators.',
            'By creating an account, using the website, uploading a photo for testing, starting a Custom LUT project, or purchasing or claiming a digital product, you agree to these Terms of Use and to the other policies linked during registration or checkout.',
        ],
    },
    {
        title: '2. Accounts and eligibility',
        paragraphs: [
            'You are responsible for the accuracy of the information you provide, for keeping your login details secure, and for all activity that occurs under your account.',
            'You must use a valid email address. Some features, including downloads and account access, may require email verification. We may suspend or restrict an account when we reasonably believe it is being misused, compromised, or used in a way that harms LUT Web, customers, or the security of the service.',
        ],
    },
    {
        title: '3. Acceptable use',
        paragraphs: [
            'Do not use LUT Web to upload unlawful, harmful, infringing, abusive, or malicious content. Do not attempt to bypass access controls, scrape private areas, attack the service, reverse engineer protected download flows, or interfere with payment, queue, image-processing, or security systems.',
            'You may not upload photos unless you have the rights and permissions needed to process them through the service. This includes permission from any recognizable people when that permission is required by law or contract.',
        ],
    },
    {
        title: '4. Digital products and downloads',
        paragraphs: [
            'Catalog LUTs and Custom LUT packages are digital products. Purchases, free claims, payment capture, download access, and digital-delivery consent are governed by the Terms of Sale, License Agreement, and Refund Policy shown during checkout.',
            'Download links are provided through your authenticated account. Do not share private account downloads, package files, CUBE files, signed links, or other protected assets with people who are not entitled to access them.',
        ],
    },
    {
        title: '5. Your content',
        paragraphs: [
            'You keep ownership of photos and other content you upload. You give LUT Web permission to store and process that content only as needed to provide the requested feature, such as previewing a LUT, generating a Custom LUT, preventing abuse, maintaining security, or resolving support issues.',
            'Customer uploads are not used as public storefront examples unless a separate administrator-approved rights workflow confirms that the store may use the image publicly.',
        ],
    },
    {
        title: '6. LUT Web content and intellectual property',
        paragraphs: [
            'The website design, product descriptions, product media, generated storefront previews, software, workflows, documentation, LUT packages, and other service materials are owned by LUT Web or its licensors, except for content supplied by users or third parties.',
            'Buying or claiming a LUT gives you the license described in the License Agreement. It does not transfer ownership of LUT Web intellectual property.',
        ],
    },
    {
        title: '7. Service availability and changes',
        paragraphs: [
            'We work to keep LUT Web available and reliable, but the service may be interrupted for maintenance, deployment, security response, third-party provider issues, or events outside our reasonable control.',
            'We may update, improve, restrict, or discontinue features when needed for security, legal compliance, product quality, or operational reasons. Material changes that affect paid access will be handled in a commercially reasonable way.',
        ],
    },
    {
        title: '8. Third-party services',
        paragraphs: [
            'LUT Web uses third-party providers for services such as payment processing, hosting, email delivery, and operational infrastructure. PayPal payment pages, buttons, frames, and related browser requests are provided by PayPal and are subject to PayPal terms and privacy notices.',
            'We are not responsible for third-party websites or services that we do not control, even when they are linked from LUT Web.',
        ],
    },
    {
        title: '9. Disclaimers and liability',
        paragraphs: [
            'LUTs, previews, Custom LUT outputs, and image-processing results are creative tools. They may vary depending on camera, color profile, editing software, display settings, source image, and workflow.',
            'To the maximum extent allowed by applicable law, LUT Web is provided without warranties that it will be uninterrupted, error-free, or suitable for every creative or commercial purpose. Nothing in these Terms limits rights that cannot legally be limited, including mandatory consumer rights where they apply.',
        ],
    },
    {
        title: '10. Contact and updates',
        paragraphs: [
            'If you have questions about these Terms, contact LUT Web through the support address published in account pages, checkout communications, or transactional emails.',
            'We may update these Terms from time to time. The effective date above shows when this version was last updated. If a change materially affects your rights or obligations, we will provide notice in a reasonable way.',
        ],
    },
];
</script>

<template>
    <LegalArticlePage
        title="Terms of Use"
        meta-description="Terms for using LUT Web accounts, LUT previews, Custom LUT tools, and digital-product access."
        :effective-date="effectiveDate"
        intro="These Terms explain the rules for using LUT Web. Separate checkout terms and license terms apply when you purchase, claim, download, or use digital LUT products."
        :sections="sections"
    >
        <template #related-links>
            <Link
                :href="termsOfSale()"
                class="inline-flex items-center gap-2 rounded-md bg-stone-950 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="receipt" class="size-4" />
                Terms of Sale
            </Link>
            <Link
                :href="privacy()"
                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="shield" class="size-4" />
                Privacy Policy
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
