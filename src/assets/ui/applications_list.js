/**
 * Applications list: live filtering and enrichment polling.
 *
 * - Any change in the filters form (debounced) re-fetches the list's "results" block as an
 *   XMLHttpRequest and swaps #results, without reloading the page. The URL follows the filters.
 * - While a row on screen is pending enrichment, polls the enrichment endpoint and refreshes the
 *   results once one of them is done.
 *
 * Without JavaScript the form still works: it is a normal GET form.
 */
export default (enrichmentUrl, interval = 4000) => ({
    request: null,

    init() {
        this.timer = setInterval(() => this.checkEnrichment(), interval);
    },

    destroy() {
        clearInterval(this.timer);
    },

    /** The filters as a query string, without empty values. Changing a filter goes back to page 1. */
    filters() {
        const params = new URLSearchParams(new FormData(this.$refs.filters));
        for (const [name, value] of [...params]) {
            if (value === '') {
                params.delete(name);
            }
        }

        return params;
    },

    async refresh(params = this.filters()) {
        this.request?.abort();
        this.request = new AbortController();
        const url = location.pathname + (params.size ? `?${params}` : '');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.request.signal,
            });
            if (!response.ok) {
                return;
            }
            document.getElementById('results').outerHTML = await response.text();
            history.replaceState(null, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                throw error;
            }
        }
    },

    async checkEnrichment() {
        const ids = [...document.querySelectorAll('#results [data-enrichment="pending"]')].map((row) => row.dataset.application);
        if (ids.length === 0 || document.hidden) {
            return;
        }

        const query = new URLSearchParams(ids.map((id) => ['ids[]', id]));
        const response = await fetch(`${enrichmentUrl}?${query}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }

        const states = await response.json();
        if (states.some((state) => state.status !== 'pending')) {
            // Same filters and page as now
            await this.refresh(new URLSearchParams(location.search));
        }
    },
});
