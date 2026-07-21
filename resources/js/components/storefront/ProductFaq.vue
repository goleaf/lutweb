<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';

const questions: {
    icon: AppIconName;
    question: string;
    answer: string;
}[] = [
    {
        icon: 'credit-card',
        question: 'Can I buy this LUT now?',
        answer: 'Yes, when the product is available. Checkout handles one LUT or bundle at a time in EUR.',
    },
    {
        icon: 'image',
        question: 'Can I try it on my photo?',
        answer: 'Yes, published testable LUTs can be tried securely from your verified account.',
    },
];
</script>

<template>
    <section aria-labelledby="faq-heading">
        <SectionHeading heading-id="faq-heading" icon="sparkles" title="FAQ" />
        <div class="mt-4 grid gap-3">
            <details
                v-for="item in questions"
                :key="item.question"
                class="rounded-lg border border-stone-200 bg-white p-4"
            >
                <summary
                    class="cursor-pointer text-sm font-semibold text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <span class="inline-flex items-center gap-2">
                        <AppIcon
                            :name="item.icon"
                            class="size-4 text-teal-800"
                        />
                        {{ item.question }}
                    </span>
                </summary>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    {{ item.answer }}
                </p>
            </details>
        </div>
    </section>
</template>
