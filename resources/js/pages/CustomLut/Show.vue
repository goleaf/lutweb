<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import AutosaveStatus from '@/components/custom-lut/AutosaveStatus.vue';
import CustomLutControls from '@/components/custom-lut/CustomLutControls.vue';
import CustomLutPhotoStrip from '@/components/custom-lut/CustomLutPhotoStrip.vue';
import CustomLutPreview from '@/components/custom-lut/CustomLutPreview.vue';
import WizardStyleGrid from '@/components/custom-lut/WizardStyleGrid.vue';
import WizardVariationGrid from '@/components/custom-lut/WizardVariationGrid.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type {
    CanonicalLutParameters,
    CustomLutBuild,
    CustomLutBuildRequest,
    LutParameterDefinition,
    LutWizardConfig,
    WizardAutosaveState,
    WizardProject,
    WizardProjectPhoto,
    WizardProjectVariant,
    WizardStyle,
} from '@/types/lut-wizard';

const props = defineProps<{
    project: WizardProject;
    photos: WizardProjectPhoto[];
    variants: WizardProjectVariant[];
    build: CustomLutBuild | null;
    styles: WizardStyle[];
    schema: LutParameterDefinition[];
    config: LutWizardConfig;
}>();

const csrf =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const project = ref<WizardProject>({ ...props.project });
const photos = ref<WizardProjectPhoto[]>([...props.photos]);
const variants = ref<WizardProjectVariant[]>([...props.variants]);
const build = ref<CustomLutBuild | null>(props.build);
const parameters = ref<CanonicalLutParameters>({ ...props.project.parameters });
const previewVariant = ref<WizardProjectVariant | null>(null);
const selectedPhotoId = ref<string | null>(
    photos.value.find((photo) => photo.status === 'ready')?.id ?? null,
);
const autosaveState = ref<WizardAutosaveState>('saved');
const savedAt = ref<string | null>(project.value.updated_at);
const saveTimer = ref<number | null>(null);
const pendingHistoryStart = ref<CanonicalLutParameters | null>(null);
const undoStack = ref<CanonicalLutParameters[]>([]);
const redoStack = ref<CanonicalLutParameters[]>([]);
const saving = ref(false);
const pendingSaveAfterCurrent = ref(false);
type WizardSection = 'photos' | 'style' | 'variations' | 'fine-tune' | 'review';
const section = ref<WizardSection>('photos');
const pollTimer = ref<number | null>(null);
const buildBusy = ref(false);
const buildError = ref<string | null>(null);
const sections: WizardSection[] = [
    'photos',
    'style',
    'variations',
    'fine-tune',
    'review',
];
const sectionIcons = {
    photos: 'image',
    style: 'palette',
    variations: 'sparkles',
    'fine-tune': 'sliders',
    review: 'check-circle',
} as const;

const activeParameters = computed(
    () => previewVariant.value?.parameters ?? parameters.value,
);
const currentStyle = computed(
    () =>
        props.styles.find(
            (style) => style.id === project.value.selected_style?.id,
        ) ?? null,
);
const unsupportedVersion = computed(
    () => project.value.transform_version !== 'lut_transform_v1',
);
const buildIsStale = computed(
    () =>
        build.value !== null &&
        (build.value.status === 'superseded' ||
            build.value.project_revision !== project.value.revision ||
            build.value.parameters_hash !== project.value.parameters_hash ||
            build.value.project_name_snapshot !== project.value.name),
);

function isConflictState(state: WizardAutosaveState): boolean {
    return state === 'conflict';
}

watch(
    () => props.photos,
    (next) => {
        photos.value = [...next];
        syncPolling();
    },
);

watch(
    () => props.build,
    (next) => {
        build.value = next;
        syncPolling();
    },
);

function cloneParameters(
    value: CanonicalLutParameters,
): CanonicalLutParameters {
    return { ...value };
}

function sameParameters(
    first: CanonicalLutParameters,
    second: CanonicalLutParameters,
): boolean {
    return JSON.stringify(first) === JSON.stringify(second);
}

function setParameters(next: CanonicalLutParameters): void {
    if (pendingHistoryStart.value === null) {
        pendingHistoryStart.value = cloneParameters(parameters.value);
    }

    parameters.value = cloneParameters(next);
    previewVariant.value = null;
    autosaveState.value = 'unsaved';
    scheduleSave();
}

function commitHistory(): void {
    if (
        pendingHistoryStart.value === null ||
        sameParameters(pendingHistoryStart.value, parameters.value)
    ) {
        pendingHistoryStart.value = null;

        return;
    }

    undoStack.value = [...undoStack.value, pendingHistoryStart.value].slice(
        -props.config.maximum_local_undo_entries,
    );
    redoStack.value = [];
    pendingHistoryStart.value = null;
}

function resetGroup(group: LutParameterDefinition['group']): void {
    const next = cloneParameters(parameters.value);

    for (const definition of props.schema.filter(
        (item) => item.group === group,
    )) {
        next[definition.key] = definition.default;
    }

    setParameters(next);
    commitHistory();
}

function resetAll(): void {
    const next = cloneParameters(parameters.value);

    for (const definition of props.schema) {
        next[definition.key] = definition.default;
    }

    setParameters(next);
    commitHistory();
}

function undo(): void {
    const previous = undoStack.value.at(-1);

    if (previous === undefined) {
        return;
    }

    undoStack.value = undoStack.value.slice(0, -1);
    redoStack.value = [...redoStack.value, cloneParameters(parameters.value)];
    parameters.value = cloneParameters(previous);
    autosaveState.value = 'unsaved';
    previewVariant.value = null;
    scheduleSave();
}

function redo(): void {
    const next = redoStack.value.at(-1);

    if (next === undefined) {
        return;
    }

    redoStack.value = redoStack.value.slice(0, -1);
    undoStack.value = [...undoStack.value, cloneParameters(parameters.value)];
    parameters.value = cloneParameters(next);
    autosaveState.value = 'unsaved';
    previewVariant.value = null;
    scheduleSave();
}

function scheduleSave(): void {
    if (saveTimer.value !== null) {
        window.clearTimeout(saveTimer.value);
    }

    saveTimer.value = window.setTimeout(() => {
        void saveNow();
    }, props.config.autosave_debounce_ms);
}

async function jsonRequest<T>(
    url: string,
    method: string,
    body?: Record<string, unknown> | FormData,
): Promise<T> {
    const isFormData = body instanceof FormData;
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        },
        body:
            body === undefined
                ? undefined
                : isFormData
                  ? body
                  : JSON.stringify(body),
    });

    if (!response.ok) {
        if (response.status === 409) {
            autosaveState.value = 'conflict';
        }

        throw new Error(`Request failed with status ${response.status}`);
    }

    return (await response.json()) as T;
}

async function saveNow(): Promise<void> {
    if (autosaveState.value !== 'unsaved' || saving.value) {
        if (autosaveState.value === 'unsaved' && saving.value) {
            pendingSaveAfterCurrent.value = true;
        }

        return;
    }

    if (saveTimer.value !== null) {
        window.clearTimeout(saveTimer.value);
        saveTimer.value = null;
    }

    saving.value = true;
    autosaveState.value = 'saving';
    pendingSaveAfterCurrent.value = false;
    const requestedName = project.value.name;
    const requestedParameters = cloneParameters(parameters.value);

    try {
        const payload = await jsonRequest<{
            project: WizardProject;
            build: CustomLutBuild | null;
        }>(`/custom-lut/${project.value.id}`, 'PATCH', {
            expected_revision: project.value.revision,
            mutation_id: crypto.randomUUID(),
            name: project.value.name,
            parameters: parameters.value,
        });

        const localName = project.value.name;
        const localParameters = cloneParameters(parameters.value);
        const changedDuringSave =
            pendingSaveAfterCurrent.value ||
            localName !== requestedName ||
            !sameParameters(localParameters, requestedParameters);

        project.value = payload.project;
        build.value = payload.build;
        autosaveState.value = 'saved';
        savedAt.value = payload.project.updated_at;

        if (changedDuringSave) {
            project.value.name = localName;
            parameters.value = localParameters;
            autosaveState.value = 'unsaved';
            pendingSaveAfterCurrent.value = false;
            scheduleSave();

            return;
        }

        parameters.value = cloneParameters(payload.project.parameters);
    } catch {
        if (!isConflictState(autosaveState.value)) {
            autosaveState.value = 'failed';
        }
    } finally {
        saving.value = false;
    }
}

async function flushSave(): Promise<void> {
    commitHistory();
    await saveNow();
}

async function selectStyle(style: WizardStyle | null): Promise<void> {
    await flushSave();

    const nextParameters =
        style?.base_parameters ??
        (Object.fromEntries(
            props.schema.map((definition) => [
                definition.key,
                definition.default,
            ]),
        ) as CanonicalLutParameters);

    if (
        !sameParameters(parameters.value, nextParameters) &&
        !window.confirm('Replace your current look with this starting style?')
    ) {
        return;
    }

    undoStack.value = [
        ...undoStack.value,
        cloneParameters(parameters.value),
    ].slice(-props.config.maximum_local_undo_entries);
    redoStack.value = [];

    const payload = await jsonRequest<{
        project: WizardProject;
        variants: WizardProjectVariant[];
        build: CustomLutBuild | null;
    }>(`/custom-lut/${project.value.id}/style`, 'POST', {
        expected_revision: project.value.revision,
        mutation_id: crypto.randomUUID(),
        style_id: style?.id ?? null,
    });

    project.value = payload.project;
    build.value = payload.build;
    parameters.value = cloneParameters(payload.project.parameters);
    variants.value = payload.variants;
    previewVariant.value = null;
    autosaveState.value = 'saved';
    savedAt.value = payload.project.updated_at;
}

async function generate(mode: 'fresh' | 'more_like_this'): Promise<void> {
    await flushSave();

    const payload = await jsonRequest<{
        project: WizardProject;
        variants: WizardProjectVariant[];
    }>(`/custom-lut/${project.value.id}/variations`, 'POST', {
        expected_revision: project.value.revision,
        mutation_id: crypto.randomUUID(),
        mode,
    });

    project.value = payload.project;
    variants.value = payload.variants;
    previewVariant.value = null;
    autosaveState.value = 'saved';
    savedAt.value = payload.project.updated_at;
}

async function useVariant(variant: WizardProjectVariant): Promise<void> {
    await flushSave();

    undoStack.value = [
        ...undoStack.value,
        cloneParameters(parameters.value),
    ].slice(-props.config.maximum_local_undo_entries);
    redoStack.value = [];

    const payload = await jsonRequest<{
        project: WizardProject;
        variants: WizardProjectVariant[];
        build: CustomLutBuild | null;
    }>(
        `/custom-lut/${project.value.id}/variations/${variant.id}/select`,
        'POST',
        {
            expected_revision: project.value.revision,
            mutation_id: crypto.randomUUID(),
        },
    );

    project.value = payload.project;
    build.value = payload.build;
    parameters.value = cloneParameters(payload.project.parameters);
    variants.value = payload.variants;
    previewVariant.value = null;
    autosaveState.value = 'saved';
    savedAt.value = payload.project.updated_at;
}

function syncPolling(): void {
    const shouldPollPhotos = photos.value.some(
        (photo) => photo.status === 'queued' || photo.status === 'processing',
    );
    const shouldPollBuild =
        build.value?.status === 'queued' ||
        build.value?.status === 'processing';
    const shouldPoll = shouldPollPhotos || shouldPollBuild;

    if (!shouldPoll && pollTimer.value !== null) {
        window.clearInterval(pollTimer.value);
        pollTimer.value = null;

        return;
    }

    if (shouldPoll && pollTimer.value === null) {
        pollTimer.value = window.setInterval(
            () => {
                router.reload({ only: ['photos', 'build'] });
            },
            shouldPollBuild ? 3_000 : 5_000,
        );
    }
}

async function prepareBuild(): Promise<void> {
    buildError.value = null;
    await flushSave();

    if (autosaveState.value !== 'saved') {
        buildError.value =
            'Save the current project before preparing a package.';

        return;
    }

    buildBusy.value = true;

    try {
        const request = {
            expected_revision: project.value.revision,
            expected_parameters_hash: project.value.parameters_hash,
            build_request_id: crypto.randomUUID(),
        } satisfies CustomLutBuildRequest;
        const payload = await jsonRequest<{ build: CustomLutBuild }>(
            project.value.links.prepare_build,
            'POST',
            request,
        );

        build.value = payload.build;
        syncPolling();
    } catch {
        buildError.value = 'We could not start package preparation.';
    } finally {
        buildBusy.value = false;
    }
}

async function removeBuild(): Promise<void> {
    if (
        build.value === null ||
        build.value.links.delete === null ||
        !window.confirm('Remove this prepared package?')
    ) {
        return;
    }

    buildBusy.value = true;
    buildError.value = null;

    try {
        await jsonRequest<{ deleted: boolean }>(
            build.value.links.delete,
            'DELETE',
        );
        build.value = null;
    } catch {
        buildError.value = 'We could not remove this prepared package.';
    } finally {
        buildBusy.value = false;
    }
}

async function openCheckout(): Promise<void> {
    buildError.value = null;

    if (build.value?.commerce.checkout_url === null) {
        buildError.value =
            build.value.commerce.message ??
            'This package is not available for checkout yet.';

        return;
    }

    await flushSave();

    if (autosaveState.value === 'conflict') {
        buildError.value =
            'Resolve the updated project state before starting checkout.';

        return;
    }

    if (autosaveState.value !== 'saved') {
        buildError.value = 'Save the current project before starting checkout.';

        return;
    }

    if (
        build.value === null ||
        build.value.commerce.checkout_url === null ||
        (buildIsStale.value && build.value.commerce.state !== 'resume') ||
        build.value.commerce.state === 'stale_build'
    ) {
        buildError.value = 'This LUT changed and must be prepared again.';

        return;
    }

    router.visit(build.value.commerce.checkout_url);
}

function commerceActionLabel(current: CustomLutBuild): string {
    return current.commerce.state === 'resume'
        ? 'Resume Checkout'
        : 'Buy and Download';
}

function formatBytes(value: number | null): string {
    if (value === null || value <= 0) {
        return 'Not available';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function metric(value: number | null): string {
    if (value === null) {
        return 'Not measured';
    }

    return `${(value / 1_000_000).toFixed(3)} levels`;
}

function buildStatusMessage(current: CustomLutBuild | null): string {
    if (current === null) {
        return 'No package has been prepared yet.';
    }

    if (current.status === 'queued') {
        return 'Your LUT package is queued.';
    }

    if (current.status === 'processing') {
        return 'Preparing CUBE files and package documents.';
    }

    if (current.status === 'ready' && current.sale_ready) {
        return 'Your LUT package is technically ready for purchase.';
    }

    if (current.status === 'ready') {
        return 'The package passed technical generation, but final sale documents are not active yet.';
    }

    if (current.status === 'superseded') {
        return 'Your LUT changed after this package was prepared.';
    }

    if (current.status === 'expired') {
        return 'This prepared package expired.';
    }

    return current.failure_message ?? 'We could not prepare this LUT package.';
}

function handleShortcut(event: KeyboardEvent): void {
    const target = event.target;

    if (
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement
    ) {
        return;
    }

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z') {
        event.preventDefault();

        if (event.shiftKey) {
            redo();

            return;
        }

        undo();
    }
}

function handleBeforeUnload(event: BeforeUnloadEvent): void {
    if (autosaveState.value !== 'unsaved') {
        return;
    }

    event.preventDefault();
}

onMounted(() => {
    window.addEventListener('keydown', handleShortcut);
    window.addEventListener('beforeunload', handleBeforeUnload);
    syncPolling();
});

onBeforeUnmount(() => {
    if (saveTimer.value !== null) {
        window.clearTimeout(saveTimer.value);
    }

    if (pollTimer.value !== null) {
        window.clearInterval(pollTimer.value);
    }

    window.removeEventListener('keydown', handleShortcut);
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <PublicLayout>
        <Head :title="project.name">
            <meta name="robots" content="noindex,nofollow" />
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div class="min-w-0">
                    <label for="project-name" class="sr-only"
                        >Project name</label
                    >
                    <input
                        id="project-name"
                        v-model="project.name"
                        maxlength="80"
                        class="w-full min-w-0 rounded-md border border-transparent bg-transparent px-0 py-1 text-2xl font-semibold text-stone-950 focus:border-teal-700 focus:bg-white focus:px-2 focus:outline-none"
                        @input="
                            autosaveState = 'unsaved';
                            scheduleSave();
                        "
                    />
                    <p
                        class="mt-1 inline-flex items-center gap-2 text-sm text-stone-600"
                    >
                        <AppIcon name="clock" class="size-4 text-teal-800" />
                        <span>
                            Draft expires
                            {{
                                new Date(
                                    project.expires_at,
                                ).toLocaleDateString()
                            }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AutosaveStatus
                        :state="autosaveState"
                        :saved-at="savedAt"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                        :disabled="undoStack.length === 0"
                        @click="undo"
                    >
                        <AppIcon name="undo" class="size-4" />
                        Undo
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                        :disabled="redoStack.length === 0"
                        @click="redo"
                    >
                        <AppIcon name="redo" class="size-4" />
                        Redo
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="resetAll"
                    >
                        <AppIcon name="reset" class="size-4" />
                        Reset
                    </button>
                </div>
            </div>
        </section>

        <section
            class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_24rem] lg:px-8"
        >
            <div class="min-w-0 space-y-6">
                <h1 class="sr-only">Custom LUT Wizard editor</h1>
                <CustomLutPreview
                    v-model:selected-photo-id="selectedPhotoId"
                    :photos="photos"
                    :parameters="activeParameters"
                    :config="config"
                />
                <CustomLutPhotoStrip
                    :project-id="project.id"
                    :photos="photos"
                    :maximum="project.maximum_photo_count"
                    :selected-photo-id="selectedPhotoId"
                    @uploaded="
                        photos = [...photos, $event];
                        syncPolling();
                    "
                    @deleted="
                        photos = photos.filter((photo) => photo.id !== $event)
                    "
                    @select="selectedPhotoId = $event"
                    @error="autosaveState = 'failed'"
                />
            </div>

            <aside class="space-y-5">
                <nav
                    class="grid grid-cols-2 gap-2 text-sm"
                    aria-label="Wizard sections"
                >
                    <button
                        v-for="item in sections"
                        :key="item"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md border px-3 py-2 font-semibold capitalize focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        :class="
                            section === item
                                ? 'border-stone-950 bg-stone-950 text-white'
                                : 'border-stone-300 bg-white text-stone-800'
                        "
                        @click="section = item"
                    >
                        <AppIcon :name="sectionIcons[item]" class="size-4" />
                        {{ item.replace('-', ' ') }}
                    </button>
                </nav>

                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <WizardStyleGrid
                        v-if="section === 'style'"
                        :styles="styles"
                        :selected-style-id="project.selected_style?.id ?? null"
                        @select="selectStyle"
                    />

                    <WizardVariationGrid
                        v-else-if="section === 'variations'"
                        :variants="variants"
                        :previewing-hash="
                            previewVariant?.parameters_hash ?? null
                        "
                        :selected-hash="project.parameters_hash"
                        :busy="saving"
                        @generate-fresh="generate('fresh')"
                        @generate-more="generate('more_like_this')"
                        @preview="previewVariant = $event"
                        @use="useVariant"
                        @current="previewVariant = null"
                    />

                    <CustomLutControls
                        v-else-if="section === 'fine-tune'"
                        :parameters="parameters"
                        :schema="schema"
                        :minimums="currentStyle?.minimum_parameters ?? null"
                        :maximums="currentStyle?.maximum_parameters ?? null"
                        :disabled="unsupportedVersion"
                        @update="setParameters"
                        @commit="commitHistory"
                        @reset-group="resetGroup"
                        @reset-all="resetAll"
                    />

                    <div v-else-if="section === 'review'" class="space-y-4">
                        <div>
                            <h2
                                class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                            >
                                <AppIcon
                                    name="check-circle"
                                    class="size-4 text-teal-800"
                                />
                                Review
                            </h2>
                            <p class="mt-1 text-sm text-stone-600">
                                Photos expire independently from saved project
                                parameters. Package preparation uses only the
                                saved LUT parameters and project name.
                            </p>
                        </div>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-stone-500">Project</dt>
                                <dd class="font-medium text-stone-950">
                                    {{ project.name }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-stone-500">Style</dt>
                                <dd class="font-medium text-stone-950">
                                    {{
                                        project.selected_style?.name ??
                                        'Neutral'
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-stone-500">Photos</dt>
                                <dd class="font-medium text-stone-950">
                                    {{
                                        photos.filter(
                                            (photo) => photo.status === 'ready',
                                        ).length
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-stone-500">Transform</dt>
                                <dd class="font-medium text-stone-950">
                                    {{ project.transform_version }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-stone-500">Hash</dt>
                                <dd class="font-mono text-stone-950">
                                    {{ project.parameters_hash.slice(0, 12) }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            class="rounded-md border border-stone-200 bg-stone-50 p-3"
                            aria-live="polite"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        Package build
                                    </h3>
                                    <p class="mt-1 text-sm text-stone-600">
                                        {{ buildStatusMessage(build) }}
                                    </p>
                                    <p
                                        v-if="buildError"
                                        class="mt-2 text-sm font-medium text-red-700"
                                    >
                                        {{ buildError }}
                                    </p>
                                </div>
                                <span
                                    v-if="build"
                                    class="rounded-full border px-2.5 py-1 text-xs font-semibold tracking-wide uppercase"
                                    :class="
                                        build.sale_ready
                                            ? 'border-teal-200 bg-teal-50 text-teal-800'
                                            : 'border-amber-200 bg-amber-50 text-amber-800'
                                    "
                                >
                                    {{
                                        build.sale_ready
                                            ? 'Sale-ready'
                                            : 'Review build'
                                    }}
                                </span>
                            </div>

                            <dl
                                v-if="build"
                                class="mt-3 grid gap-x-5 gap-y-2 text-sm text-stone-600 sm:grid-cols-2"
                            >
                                <div>
                                    <dt
                                        class="inline font-medium text-stone-900"
                                    >
                                        Package:
                                    </dt>
                                    <dd class="inline">
                                        {{ build.package_stem }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="inline font-medium text-stone-900"
                                    >
                                        Size:
                                    </dt>
                                    <dd class="inline">
                                        {{
                                            formatBytes(
                                                build.package_size_bytes,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="inline font-medium text-stone-900"
                                    >
                                        Expires:
                                    </dt>
                                    <dd class="inline">
                                        {{
                                            build.expires_at
                                                ? new Date(
                                                      build.expires_at,
                                                  ).toLocaleDateString()
                                                : 'Not set'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="inline font-medium text-stone-900"
                                    >
                                        Mean parity:
                                    </dt>
                                    <dd class="inline">
                                        {{
                                            metric(
                                                build.parity_metrics
                                                    .mean_millionths,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="inline font-medium text-stone-900"
                                    >
                                        P99 parity:
                                    </dt>
                                    <dd class="inline">
                                        {{
                                            metric(
                                                build.parity_metrics
                                                    .p99_millionths,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="inline font-medium text-stone-900"
                                    >
                                        Max parity:
                                    </dt>
                                    <dd class="inline">
                                        {{
                                            metric(
                                                build.parity_metrics
                                                    .max_millionths,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div v-if="build?.files.length" class="mt-3">
                                <h4
                                    class="text-xs font-semibold tracking-wide text-stone-500 uppercase"
                                >
                                    Generated contents
                                </h4>
                                <ul class="mt-2 grid gap-1 text-sm">
                                    <li
                                        v-for="file in build.files"
                                        :key="`${file.kind}-${file.display_name}`"
                                        class="flex flex-wrap justify-between gap-2"
                                    >
                                        <span
                                            class="font-medium text-stone-800"
                                        >
                                            {{ file.display_name }}
                                        </span>
                                        <span class="text-stone-500">
                                            {{ formatBytes(file.size_bytes) }}
                                            <span v-if="file.short_checksum">
                                                - {{ file.short_checksum }}
                                            </span>
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <p
                                v-if="build?.contains_draft_documents"
                                class="mt-3 text-sm text-amber-800"
                            >
                                Draft package documents are included, so this
                                build is not ready for sale.
                            </p>
                            <p v-if="build" class="mt-2 text-sm text-stone-600">
                                Preview parity metrics compare the browser
                                preview path with the server transform. Small
                                RGBA8 preview differences are expected.
                            </p>
                        </div>

                        <div
                            v-if="build"
                            class="rounded-md border border-stone-200 bg-white p-3"
                            aria-live="polite"
                        >
                            <div class="space-y-2 text-sm text-stone-700">
                                <p
                                    v-if="
                                        build.commerce.price &&
                                        (build.commerce.state === 'eligible' ||
                                            build.commerce.state === 'resume')
                                    "
                                    class="font-semibold text-stone-950"
                                >
                                    Custom LUT price:
                                    {{ build.commerce.price }}
                                </p>
                                <p
                                    v-if="build.commerce.state === 'owned'"
                                    class="font-medium text-teal-800"
                                >
                                    You own this exact LUT build.
                                </p>
                                <p
                                    v-else-if="build.commerce.message"
                                    class="text-stone-600"
                                >
                                    {{ build.commerce.message }}
                                </p>
                                <p
                                    v-else-if="
                                        build.commerce.state === 'eligible' ||
                                        build.commerce.state === 'resume'
                                    "
                                    class="text-stone-600"
                                >
                                    This purchase contains the exact immutable
                                    LUT package shown here. Future edits to your
                                    project create a separate build.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-stone-950 px-3 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-300"
                                :disabled="
                                    buildBusy || autosaveState === 'conflict'
                                "
                                @click="prepareBuild"
                            >
                                <AppIcon
                                    name="package"
                                    class="size-4"
                                    :class="
                                        build?.status === 'processing'
                                            ? 'motion-safe:animate-pulse'
                                            : ''
                                    "
                                />
                                {{
                                    build === null
                                        ? 'Prepare Package'
                                        : buildIsStale ||
                                            build.status === 'failed' ||
                                            build.status === 'expired' ||
                                            build.status === 'superseded'
                                          ? 'Prepare Updated Package'
                                          : 'Regenerate Package'
                                }}
                            </button>
                            <button
                                v-if="build && build.status !== 'processing'"
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-stone-300 px-3 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                                :disabled="buildBusy"
                                @click="removeBuild"
                            >
                                <AppIcon name="trash" class="size-4" />
                                Remove Prepared Package
                            </button>
                        </div>
                        <div class="grid gap-2">
                            <button
                                v-if="
                                    build &&
                                    (build.commerce.state === 'eligible' ||
                                        build.commerce.state === 'resume')
                                "
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-teal-800 px-3 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-300"
                                :disabled="
                                    buildBusy ||
                                    autosaveState === 'conflict' ||
                                    (buildIsStale &&
                                        build.commerce.state !== 'resume')
                                "
                                @click="openCheckout"
                            >
                                <AppIcon name="download" class="size-4" />
                                {{ commerceActionLabel(build) }}
                            </button>
                            <a
                                v-else-if="
                                    build?.commerce.state === 'owned' &&
                                    build.commerce.download_url
                                "
                                :href="build.commerce.download_url"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-teal-800 px-3 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="download" class="size-4" />
                                Download Your LUT
                            </a>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-stone-300 bg-stone-100 px-3 py-2.5 text-sm font-semibold text-stone-500"
                            >
                                <AppIcon name="download" class="size-4" />
                                Buy and Download
                            </button>
                            <a
                                v-if="
                                    build?.commerce.state === 'owned' &&
                                    build.commerce.purchased_url
                                "
                                :href="build.commerce.purchased_url"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-stone-300 px-3 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="receipt" class="size-4" />
                                View Purchase
                            </a>
                        </div>
                    </div>

                    <div v-else class="space-y-3">
                        <h2
                            class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                        >
                            <AppIcon
                                name="image"
                                class="size-4 text-teal-800"
                            />
                            Photos
                        </h2>
                        <p class="text-sm leading-6 text-stone-600">
                            Use the photo slots under the preview. Switching
                            photos keeps the same LUT parameters and does not
                            autosave.
                        </p>
                    </div>
                </div>
            </aside>
        </section>
    </PublicLayout>
</template>
