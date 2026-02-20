export function plateScanner(options = {}) {
    const strings = options.strings || {};

    return {
        supported: false,
        scanning: false,
        error: '',
        stream: null,
        detector: null,
        supportedFormats: [],
        permission: null,
        cameras: [],
        cameraLabel: '',
        selectedDeviceId: '',
        devicesChecked: false,
        secureContext: window.isSecureContext === true,
        init() {
            this.supported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            if ('BarcodeDetector' in window) {
                const requestedFormats = [
                    'code_128',
                    'code_39',
                    'qr_code',
                    'pdf417',
                    'ean_13',
                    'ean_8',
                    'upc_a',
                    'upc_e',
                    'itf',
                    'data_matrix',
                ];
                if (BarcodeDetector.getSupportedFormats) {
                    BarcodeDetector.getSupportedFormats().then((formats) => {
                        this.supportedFormats = formats || [];
                        const usable = requestedFormats.filter((format) => this.supportedFormats.includes(format));
                        this.detector = new BarcodeDetector({ formats: usable.length ? usable : requestedFormats });
                    }).catch(() => {
                        this.detector = new BarcodeDetector({ formats: requestedFormats });
                    });
                } else {
                    this.supportedFormats = requestedFormats;
                    this.detector = new BarcodeDetector({ formats: requestedFormats });
                }
            }

            this._onNavigate = () => {
                this.stop();
            };
            document.addEventListener('livewire:navigating', this._onNavigate);

            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'camera' }).then((status) => {
                    this.permission = status.state;
                    status.onchange = () => {
                        this.permission = status.state;
                        if (status.state === 'granted') {
                            this.refreshDevices();
                        }
                    };
                    if (status.state === 'granted') {
                        this.refreshDevices();
                    }
                }).catch(() => {});
            }
        },
        async refreshDevices() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.cameras = devices.filter((d) => d.kind === 'videoinput');
                if (!this.selectedDeviceId && this.cameras.length > 0) {
                    const preferred = this.cameras.find((d) => /back|rear|environment/i.test(d.label));
                    this.selectedDeviceId = (preferred || this.cameras[0]).deviceId || '';
                }
                this.devicesChecked = true;
            } catch (e) {
                this.devicesChecked = true;
            }
        },
        async start() {
            this.error = '';
            if (!this.supported || this.scanning) return;
            if (!this.secureContext) {
                this.error = strings.secureContext || 'Camera access requires HTTPS or localhost.';
                return;
            }
            if (this.detector && this.supportedFormats.length > 0 && !this.supportedFormats.includes('pdf417')) {
                this.error = strings.pdf417Unsupported || 'This browser cannot read PDF417 barcodes.';
                return;
            }

            try {
                try {
                    const baseConstraints = this.selectedDeviceId
                        ? { deviceId: { exact: this.selectedDeviceId } }
                        : { facingMode: 'environment' };
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: baseConstraints,
                        audio: false,
                    });
                } catch (innerError) {
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'environment' },
                            audio: false,
                        });
                    } catch (fallbackError) {
                        this.stream = await navigator.mediaDevices.getUserMedia({
                            video: true,
                            audio: false,
                        });
                    }
                }

                this.$refs.video.srcObject = this.stream;
                const track = this.stream.getVideoTracks()[0];
                this.cameraLabel = (track && track.label) ? track.label : '';
                if (!this.selectedDeviceId && track && track.getSettings) {
                    const settings = track.getSettings();
                    this.selectedDeviceId = settings.deviceId || this.selectedDeviceId;
                }
                this.refreshDevices();
                await this.waitForVideo();
                this.scanning = true;
                this.scanLoop();
            } catch (e) {
                if (e && e.name === 'NotAllowedError') {
                    this.error = strings.permissionBlocked || 'Camera permission was blocked.';
                } else if (e && e.name === 'NotFoundError') {
                    this.error = strings.noCamera || 'No camera found on this device.';
                } else if (e && e.name === 'NotReadableError') {
                    this.error = strings.cameraInUse || 'Camera is already in use by another app.';
                } else if (e && e.name === 'AbortError') {
                    this.error = strings.cameraInterrupted || 'Camera start was interrupted.';
                } else {
                    this.error = strings.cameraFailed || 'Camera access failed. Try again.';
                }
                if (e && e.message) {
                    this.error = this.error + ' (' + e.message + ')';
                }
                this.stop();
            }
        },
        stop() {
            this.scanning = false;
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }
            this.cameraLabel = '';
        },
        waitForVideo() {
            return new Promise((resolve, reject) => {
                const video = this.$refs.video;
                if (!video) return reject(new Error('Video element missing'));
                const timeoutId = setTimeout(() => {
                    reject(new Error('Timeout starting video source'));
                }, 5000);
                const cleanup = () => {
                    clearTimeout(timeoutId);
                    video.onloadedmetadata = null;
                    video.oncanplay = null;
                };
                video.onloadedmetadata = async () => {
                    try {
                        await video.play();
                        cleanup();
                        resolve();
                    } catch (e) {
                        cleanup();
                        reject(e);
                    }
                };
                video.oncanplay = async () => {
                    try {
                        await video.play();
                        cleanup();
                        resolve();
                    } catch (e) {
                        cleanup();
                        reject(e);
                    }
                };
            });
        },
        captureFrame() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            if (!video || !canvas || video.readyState < 2) return null;
            const width = video.videoWidth || 0;
            const height = video.videoHeight || 0;
            if (width <= 0 || height <= 0) return null;
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            if (!ctx) return null;
            ctx.drawImage(video, 0, 0, width, height);
            return canvas;
        },
        normalizePlateValue(rawValue) {
            const raw = String(rawValue || '').trim();
            if (!raw) return '';
            const flattened = raw.replace(/[\r\n]+/g, ' ').trim();
            const upper = flattened.toUpperCase()
                .replace(/ISLA\s+DEL\s+ENCANTO/gi, ' ')
                .replace(/MAYAGUEZ\s+2010/gi, ' ')
                .replace(/WWW\.GOBIERNO\.PR/gi, ' ')
                .replace(/PUERTO\s+RICO/gi, ' ')
                .trim();
            const directPlate = upper.match(/\b([A-Z]{3})\s*[-]?\s*([0-9]{3})\b/);
            if (directPlate) {
                return `${directPlate[1]}-${directPlate[2]}`;
            }
            const compact = upper.replace(/[^A-Z0-9]/g, '');
            const compactMatch = compact.match(/([A-Z]{3})([0-9]{3})/);
            if (compactMatch) {
                return `${compactMatch[1]}-${compactMatch[2]}`;
            }
            const labeledMatch = upper.match(/\b(?:PLATE|PLACA|MATRICULA)\b[:\s-]*([A-Z0-9-]{4,12})/);
            if (labeledMatch && labeledMatch[1]) {
                const value = labeledMatch[1].replace(/[^A-Z0-9-]/g, '');
                const strict = value.match(/^([A-Z]{3})-?([0-9]{3})$/);
                return strict ? `${strict[1]}-${strict[2]}` : '';
            }
            const alphaNumPair = upper.match(/\b([A-Z]{2,4})\s*[-]?\s*([0-9]{2,4})\b/);
            if (alphaNumPair) {
                const strict = `${alphaNumPair[1]}${alphaNumPair[2]}`.match(/^([A-Z]{3})([0-9]{3})$/);
                return strict ? `${strict[1]}-${strict[2]}` : '';
            }
            const tokens = upper.match(/[A-Z0-9]{2,8}(?:-[A-Z0-9]{1,4})?/g) || [];
            const scored = tokens
                .map((token) => {
                    const alpha = /[A-Z]/.test(token);
                    const digit = /[0-9]/.test(token);
                    const length = token.replace(/[^A-Z0-9]/g, '').length;
                    const score = (alpha && digit ? 3 : 0) + (length >= 5 ? 2 : 0) + length / 10;
                    return { token, score };
                })
                .sort((a, b) => b.score - a.score);
            if (scored.length > 0) {
                const value = scored[0].token.replace(/[^A-Z0-9-]/g, '');
                const strict = value.match(/^([A-Z]{3})-?([0-9]{3})$/);
                return strict ? `${strict[1]}-${strict[2]}` : '';
            }
            const fallback = upper.replace(/[^A-Z0-9-]/g, '');
            const strict = fallback.match(/^([A-Z]{3})-?([0-9]{3})$/);
            return strict ? `${strict[1]}-${strict[2]}` : '';
        },
        async scanLoop() {
            if (!this.scanning) return;
            try {
                if (this.detector) {
                    const codes = await this.detector.detect(this.$refs.video);
                    if (codes && codes.length > 0) {
                        const value = this.normalizePlateValue(codes[0].rawValue || '');
                        if (value) {
                            this.$wire.set('plateInput', value);
                            this.$wire.call('lookupPlate');
                            this.stop();
                            return;
                        }
                    }
                } else if (window.jsQR) {
                    const canvas = this.captureFrame();
                    if (canvas) {
                        const width = canvas.width;
                        const height = canvas.height;
                        const ctx = canvas.getContext('2d');
                        if (ctx) {
                            const imageData = ctx.getImageData(0, 0, width, height);
                            const code = window.jsQR(imageData.data, width, height);
                            if (code && code.data) {
                                const value = this.normalizePlateValue(code.data || '');
                                if (value) {
                                    this.$wire.set('plateInput', value);
                                    this.$wire.call('lookupPlate');
                                    this.stop();
                                    return;
                                }
                            }
                        }
                    }
                }
            } catch (e) {
                this.error = strings.scanFailed || 'Unable to read a plate code. Try again.';
            }
            requestAnimationFrame(() => this.scanLoop());
        },
    };
}
