import {
    transformV1,
    withoutIntensity,
} from '@/lib/lut-transform/transform-v1';
import type {
    CanonicalLutParameters,
    LutWorkerRequest,
    LutWorkerResponse,
} from '@/types/lut-wizard';

type WorkerPostMessageScope = {
    postMessage(message: LutWorkerResponse, transfer: Transferable[]): void;
};

const workerScope = self as unknown as WorkerPostMessageScope;
const cancelled = new Set<number>();

function post(response: LutWorkerResponse, transfer?: Transferable[]): void {
    workerScope.postMessage(response, transfer ?? []);
}

function generateLut(
    requestId: number,
    size: 17 | 33 | 65,
    parameters: CanonicalLutParameters,
): void {
    const output = new Uint8Array(size * size * size * 4);
    const transformParameters = withoutIntensity(parameters);
    let offset = 0;

    for (let blueIndex = 0; blueIndex < size; blueIndex++) {
        for (let greenIndex = 0; greenIndex < size; greenIndex++) {
            if (cancelled.has(requestId)) {
                cancelled.delete(requestId);

                return;
            }

            for (let redIndex = 0; redIndex < size; redIndex++) {
                const rgb = transformV1(
                    {
                        red: redIndex / (size - 1),
                        green: greenIndex / (size - 1),
                        blue: blueIndex / (size - 1),
                    },
                    transformParameters,
                );

                output[offset] = Math.round(rgb.red * 255);
                output[offset + 1] = Math.round(rgb.green * 255);
                output[offset + 2] = Math.round(rgb.blue * 255);
                output[offset + 3] = 255;
                offset += 4;
            }
        }
    }

    post(
        {
            type: 'LutGenerated',
            requestId,
            size,
            data: output.buffer,
        },
        [output.buffer],
    );
}

function transformImage(
    request: Extract<LutWorkerRequest, { type: 'TransformImageFallback' }>,
): void {
    const pixels = new Uint8ClampedArray(request.data);

    for (let index = 0; index < pixels.length; index += 4) {
        if (
            index % (request.width * 4 * 8) === 0 &&
            cancelled.has(request.requestId)
        ) {
            cancelled.delete(request.requestId);

            return;
        }

        const rgb = transformV1(
            {
                red: pixels[index] / 255,
                green: pixels[index + 1] / 255,
                blue: pixels[index + 2] / 255,
            },
            request.parameters,
        );

        pixels[index] = Math.round(rgb.red * 255);
        pixels[index + 1] = Math.round(rgb.green * 255);
        pixels[index + 2] = Math.round(rgb.blue * 255);
    }

    post(
        {
            type: 'FallbackImageTransformed',
            requestId: request.requestId,
            width: request.width,
            height: request.height,
            data: pixels.buffer,
        },
        [pixels.buffer],
    );
}

self.addEventListener('message', (event: MessageEvent<LutWorkerRequest>) => {
    const request = event.data;

    try {
        if (request.type === 'CancelGeneration') {
            cancelled.add(request.requestId);

            return;
        }

        if (request.type === 'GenerateLut') {
            generateLut(request.requestId, request.size, request.parameters);

            return;
        }

        transformImage(request);
    } catch {
        post({
            type: 'WorkerError',
            requestId: request.requestId,
            message: 'Preview processing failed in this browser.',
        });
    }
});
