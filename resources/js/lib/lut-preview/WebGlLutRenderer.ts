import type { WebGlRendererState } from '@/types/lut-wizard';

const VERTEX_SHADER_SOURCE = `#version 300 es
in vec2 aPosition;
in vec2 aTexCoord;
out vec2 vTexCoord;
void main() {
    vTexCoord = aTexCoord;
    gl_Position = vec4(aPosition, 0.0, 1.0);
}
`;

const FRAGMENT_SHADER_SOURCE = `#version 300 es
precision highp float;
precision highp sampler3D;
in vec2 vTexCoord;
uniform sampler2D uImage;
uniform sampler3D uLut;
uniform float uIntensity;
uniform float uLutSize;
out vec4 outColor;
void main() {
    vec4 source = texture(uImage, vTexCoord);
    vec3 coordinate = (source.rgb * (uLutSize - 1.0) + 0.5) / uLutSize;
    vec3 transformed = texture(uLut, coordinate).rgb;
    outColor = vec4(mix(source.rgb, transformed, clamp(uIntensity, 0.0, 1.0)), source.a);
}
`;

export class WebGlLutRenderer {
    private gl: WebGL2RenderingContext | null = null;
    private program: WebGLProgram | null = null;
    private vertexArray: WebGLVertexArrayObject | null = null;
    private positionBuffer: WebGLBuffer | null = null;
    private imageTexture: WebGLTexture | null = null;
    private lutTexture: WebGLTexture | null = null;
    private lutSize: 17 | 33 | 65 = 33;
    private lutData: Uint8Array | null = null;
    private image: HTMLImageElement | null = null;
    private intensity = 1;
    private resizeObserver: ResizeObserver | null = null;
    private state: WebGlRendererState = 'idle';

    public constructor(
        private readonly canvas: HTMLCanvasElement,
        private readonly container: HTMLElement,
        private readonly onStateChange: (
            state: WebGlRendererState,
            message?: string,
        ) => void,
    ) {
        this.handleContextLost = this.handleContextLost.bind(this);
        this.handleContextRestored = this.handleContextRestored.bind(this);
    }

    public initialize(lutSize: 17 | 33 | 65): boolean {
        const gl = this.canvas.getContext('webgl2', {
            alpha: false,
            antialias: false,
            preserveDrawingBuffer: false,
        });

        if (gl === null) {
            this.setState('unsupported', 'WebGL 2 is not available.');

            return false;
        }

        const maximumTextureSize = gl.getParameter(
            gl.MAX_3D_TEXTURE_SIZE,
        ) as number;

        if (maximumTextureSize < lutSize) {
            this.setState(
                'unsupported',
                'This browser cannot use the preview LUT texture.',
            );

            return false;
        }

        this.gl = gl;
        this.lutSize = lutSize;
        this.canvas.addEventListener(
            'webglcontextlost',
            this.handleContextLost,
        );
        this.canvas.addEventListener(
            'webglcontextrestored',
            this.handleContextRestored,
        );
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.container);

        try {
            this.createResources();
            this.setState('ready');

            return true;
        } catch {
            this.setState('failed', 'The WebGL preview could not start.');

            return false;
        }
    }

    public setImage(image: HTMLImageElement): void {
        this.image = image;

        if (this.gl === null) {
            return;
        }

        if (this.imageTexture !== null) {
            this.gl.deleteTexture(this.imageTexture);
        }

        this.imageTexture = this.createImageTexture(image);
        this.resize();
        this.render();
    }

    public setLut(data: ArrayBuffer, size: 17 | 33 | 65): void {
        this.lutData = new Uint8Array(data);
        this.lutSize = size;

        if (this.gl === null) {
            return;
        }

        if (this.lutTexture !== null) {
            this.gl.deleteTexture(this.lutTexture);
        }

        this.lutTexture = this.createLutTexture(this.lutData, size);
        this.render();
    }

    public setIntensity(value: number): void {
        this.intensity = Math.min(1, Math.max(0, value / 1000));
        this.render();
    }

    public dispose(): void {
        this.resizeObserver?.disconnect();
        this.resizeObserver = null;
        this.canvas.removeEventListener(
            'webglcontextlost',
            this.handleContextLost,
        );
        this.canvas.removeEventListener(
            'webglcontextrestored',
            this.handleContextRestored,
        );
        this.deleteResources();
        this.gl = null;
    }

    private createResources(): void {
        const gl = this.requireGl();
        const vertexShader = this.compileShader(
            gl.VERTEX_SHADER,
            VERTEX_SHADER_SOURCE,
        );
        const fragmentShader = this.compileShader(
            gl.FRAGMENT_SHADER,
            FRAGMENT_SHADER_SOURCE,
        );
        const program = gl.createProgram();

        if (program === null) {
            throw new Error('Program creation failed.');
        }

        gl.attachShader(program, vertexShader);
        gl.attachShader(program, fragmentShader);
        gl.linkProgram(program);
        gl.deleteShader(vertexShader);
        gl.deleteShader(fragmentShader);

        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            if (import.meta.env.DEV) {
                console.error(gl.getProgramInfoLog(program));
            }

            gl.deleteProgram(program);

            throw new Error('Program link failed.');
        }

        this.program = program;
        this.vertexArray = gl.createVertexArray();
        this.positionBuffer = gl.createBuffer();
        gl.bindVertexArray(this.vertexArray);
        gl.bindBuffer(gl.ARRAY_BUFFER, this.positionBuffer);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            new Float32Array([
                -1, -1, 0, 1, 1, -1, 1, 1, -1, 1, 0, 0, 1, 1, 1, 0,
            ]),
            gl.STATIC_DRAW,
        );

        const positionLocation = gl.getAttribLocation(program, 'aPosition');
        const texCoordLocation = gl.getAttribLocation(program, 'aTexCoord');
        gl.enableVertexAttribArray(positionLocation);
        gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 16, 0);
        gl.enableVertexAttribArray(texCoordLocation);
        gl.vertexAttribPointer(texCoordLocation, 2, gl.FLOAT, false, 16, 8);
        gl.bindVertexArray(null);
    }

    private createImageTexture(image: HTMLImageElement): WebGLTexture {
        const gl = this.requireGl();
        const texture = gl.createTexture();

        if (texture === null) {
            throw new Error('Image texture failed.');
        }

        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
        gl.texImage2D(
            gl.TEXTURE_2D,
            0,
            gl.RGBA,
            gl.RGBA,
            gl.UNSIGNED_BYTE,
            image,
        );
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);

        return texture;
    }

    private createLutTexture(
        data: Uint8Array,
        size: 17 | 33 | 65,
    ): WebGLTexture {
        const gl = this.requireGl();
        const texture = gl.createTexture();

        if (texture === null) {
            throw new Error('LUT texture failed.');
        }

        gl.bindTexture(gl.TEXTURE_3D, texture);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_R, gl.CLAMP_TO_EDGE);
        gl.texImage3D(
            gl.TEXTURE_3D,
            0,
            gl.RGBA8,
            size,
            size,
            size,
            0,
            gl.RGBA,
            gl.UNSIGNED_BYTE,
            data,
        );

        return texture;
    }

    private resize(): void {
        if (this.image === null || this.gl === null) {
            return;
        }

        const ratio = Math.min(2, window.devicePixelRatio || 1);
        const containerWidth = Math.max(1, this.container.clientWidth);
        const imageRatio = this.image.naturalHeight / this.image.naturalWidth;
        const cssWidth = Math.min(containerWidth, this.image.naturalWidth);
        const cssHeight = Math.min(
            Math.round(cssWidth * imageRatio),
            this.image.naturalHeight,
        );
        this.canvas.style.width = `${cssWidth}px`;
        this.canvas.style.height = `${cssHeight}px`;
        this.canvas.width = Math.max(
            1,
            Math.min(this.image.naturalWidth, Math.round(cssWidth * ratio)),
        );
        this.canvas.height = Math.max(
            1,
            Math.min(this.image.naturalHeight, Math.round(cssHeight * ratio)),
        );
        this.render();
    }

    private render(): void {
        const gl = this.gl;

        if (
            gl === null ||
            this.program === null ||
            this.vertexArray === null ||
            this.imageTexture === null ||
            this.lutTexture === null ||
            this.state !== 'ready'
        ) {
            return;
        }

        gl.viewport(0, 0, this.canvas.width, this.canvas.height);
        gl.useProgram(this.program);
        gl.bindVertexArray(this.vertexArray);
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, this.imageTexture);
        gl.uniform1i(gl.getUniformLocation(this.program, 'uImage'), 0);
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_3D, this.lutTexture);
        gl.uniform1i(gl.getUniformLocation(this.program, 'uLut'), 1);
        gl.uniform1f(
            gl.getUniformLocation(this.program, 'uIntensity'),
            this.intensity,
        );
        gl.uniform1f(
            gl.getUniformLocation(this.program, 'uLutSize'),
            this.lutSize,
        );
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        gl.bindVertexArray(null);
    }

    private compileShader(type: number, source: string): WebGLShader {
        const gl = this.requireGl();
        const shader = gl.createShader(type);

        if (shader === null) {
            throw new Error('Shader creation failed.');
        }

        gl.shaderSource(shader, source);
        gl.compileShader(shader);

        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            if (import.meta.env.DEV) {
                console.error(gl.getShaderInfoLog(shader));
            }

            gl.deleteShader(shader);

            throw new Error('Shader compile failed.');
        }

        return shader;
    }

    private handleContextLost(event: Event): void {
        event.preventDefault();
        this.setState('context-lost', 'The preview context was interrupted.');
        this.deleteResources();
    }

    private handleContextRestored(): void {
        try {
            this.createResources();

            if (this.image !== null) {
                this.imageTexture = this.createImageTexture(this.image);
            }

            if (this.lutData !== null) {
                this.lutTexture = this.createLutTexture(
                    this.lutData,
                    this.lutSize,
                );
            }

            this.setState('ready');
            this.resize();
        } catch {
            this.setState('failed', 'The preview could not recover.');
        }
    }

    private deleteResources(): void {
        const gl = this.gl;

        if (gl === null) {
            return;
        }

        if (this.imageTexture !== null) {
            gl.deleteTexture(this.imageTexture);
            this.imageTexture = null;
        }

        if (this.lutTexture !== null) {
            gl.deleteTexture(this.lutTexture);
            this.lutTexture = null;
        }

        if (this.positionBuffer !== null) {
            gl.deleteBuffer(this.positionBuffer);
            this.positionBuffer = null;
        }

        if (this.vertexArray !== null) {
            gl.deleteVertexArray(this.vertexArray);
            this.vertexArray = null;
        }

        if (this.program !== null) {
            gl.deleteProgram(this.program);
            this.program = null;
        }
    }

    private requireGl(): WebGL2RenderingContext {
        if (this.gl === null) {
            throw new Error('WebGL is not initialized.');
        }

        return this.gl;
    }

    private setState(state: WebGlRendererState, message?: string): void {
        this.state = state;
        this.onStateChange(state, message);
    }
}
