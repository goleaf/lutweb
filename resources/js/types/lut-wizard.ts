export type LutTransformVersion = 'lut_transform_v1';

export type LutParameterKey =
    | 'intensity'
    | 'exposure'
    | 'contrast'
    | 'temperature'
    | 'tint'
    | 'saturation'
    | 'vibrance'
    | 'highlights'
    | 'shadows'
    | 'whites'
    | 'blacks'
    | 'fade'
    | 'shadow_hue'
    | 'shadow_strength'
    | 'highlight_hue'
    | 'highlight_strength';

export type CanonicalLutParameters = Record<LutParameterKey, number>;

export interface LutParameterDefinition {
    key: LutParameterKey;
    label: string;
    group: 'Basic' | 'Color' | 'Tone' | 'Split Toning';
    minimum: number;
    maximum: number;
    default: number;
    display_scale: number;
    ui_step: number;
    unit: string;
}

export interface WizardStyle {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    transform_version: LutTransformVersion;
    base_parameters: CanonicalLutParameters;
    minimum_parameters: CanonicalLutParameters;
    maximum_parameters: CanonicalLutParameters;
    variation_amounts: CanonicalLutParameters;
}

export interface WizardProject {
    id: string;
    name: string;
    status: 'draft' | 'expired';
    transform_version: LutTransformVersion | string;
    revision: number;
    parameters: CanonicalLutParameters;
    parameters_hash: string;
    selected_style: { id: string | null; name: string } | null;
    variation_generation: number;
    created_at: string | null;
    updated_at: string | null;
    expires_at: string;
    maximum_photo_count: number;
}

export interface WizardProjectPhoto {
    id: string;
    status: 'queued' | 'processing' | 'ready' | 'failed' | 'expired';
    original_name: string;
    preview_width: number | null;
    preview_height: number | null;
    sort_order: number;
    expires_at: string;
    preview_url: string | null;
    failure_message: string | null;
    delete_url: string;
}

export interface WizardProjectVariant {
    id: string;
    position: number;
    mode: 'fresh' | 'more_like_this';
    parameters: CanonicalLutParameters;
    parameters_hash: string;
    selected: boolean;
}

export type WizardAutosaveState =
    'saved' | 'unsaved' | 'saving' | 'failed' | 'conflict';

export interface LutWizardConfig {
    maximum_photo_count: number;
    preview_lut_size: 17 | 33 | 65;
    cpu_fallback_maximum_edge: number;
    autosave_debounce_ms: number;
    maximum_local_undo_entries: number;
    variation_count: number;
}

export interface GenerateLutRequest {
    type: 'GenerateLut';
    requestId: number;
    parameters: CanonicalLutParameters;
    size: 17 | 33 | 65;
}

export interface TransformImageFallbackRequest {
    type: 'TransformImageFallback';
    requestId: number;
    parameters: CanonicalLutParameters;
    width: number;
    height: number;
    data: ArrayBuffer;
}

export interface CancelGenerationRequest {
    type: 'CancelGeneration';
    requestId: number;
}

export type LutWorkerRequest =
    | GenerateLutRequest
    | TransformImageFallbackRequest
    | CancelGenerationRequest;

export interface LutGeneratedResponse {
    type: 'LutGenerated';
    requestId: number;
    size: 17 | 33 | 65;
    data: ArrayBuffer;
}

export interface FallbackImageTransformedResponse {
    type: 'FallbackImageTransformed';
    requestId: number;
    width: number;
    height: number;
    data: ArrayBuffer;
}

export interface LutWorkerErrorResponse {
    type: 'WorkerError';
    requestId: number;
    message: string;
}

export type LutWorkerResponse =
    | LutGeneratedResponse
    | FallbackImageTransformedResponse
    | LutWorkerErrorResponse;

export type WebGlRendererState =
    'idle' | 'ready' | 'unsupported' | 'context-lost' | 'failed';
