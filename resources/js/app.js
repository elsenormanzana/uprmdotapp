import jsQR from 'jsqr';
import { plateScanner } from './plateScanner';

window.jsQR = jsQR;
window.plateScanner = plateScanner;

const registerPlateScanner = () => {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('plateScanner', (options = {}) => plateScanner(options));
    }
};

registerPlateScanner();
document.addEventListener('alpine:init', registerPlateScanner);