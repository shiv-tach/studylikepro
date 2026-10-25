import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Counts down the SMS resend cooldown on the registration verify screen. The
// server also enforces it; this only keeps the button honest.
Alpine.data('otpResend', (seconds = 0) => ({
    seconds,

    init() {
        this.tick();
    },

    tick() {
        if (this.seconds <= 0) {
            return;
        }

        setTimeout(() => {
            this.seconds -= 1;
            this.tick();
        }, 1000);
    },
}));

Alpine.start();
