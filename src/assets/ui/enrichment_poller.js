/**
 * Application detail: while enrichment is pending, polls the enrichment endpoint and reloads the
 * page once it is completed or failed, so the summary and score appear on their own.
 */
export default (enrichmentUrl, applicationId, interval = 4000) => ({
    init() {
        this.timer = setInterval(() => this.check(), interval);
    },

    destroy() {
        clearInterval(this.timer);
    },

    async check() {
        if (document.hidden) {
            return;
        }

        const response = await fetch(`${enrichmentUrl}?${new URLSearchParams([['ids[]', applicationId]])}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            return;
        }

        const [state] = await response.json();
        if (state && state.status !== 'pending') {
            clearInterval(this.timer);
            location.reload();
        }
    },
});
