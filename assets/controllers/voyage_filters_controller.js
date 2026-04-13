import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        delay: { type: Number, default: 400 },
    };

    connect() {
        this.submitTimeout = null;
    }

    disconnect() {
        this.clearPendingSubmit();
    }

    queueSubmit() {
        this.clearPendingSubmit();
        this.submitTimeout = window.setTimeout(() => {
            this.submit();
        }, this.delayValue);
    }

    submitNow() {
        this.clearPendingSubmit();
        this.submit();
    }

    clearPendingSubmit() {
        if (this.submitTimeout === null) {
            return;
        }

        window.clearTimeout(this.submitTimeout);
        this.submitTimeout = null;
    }

    submit() {
        if (typeof this.element.requestSubmit === 'function') {
            this.element.requestSubmit();

            return;
        }

        this.element.submit();
    }
}