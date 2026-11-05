/**
 * Applications list, rendered in the browser from GET /api/applications.
 *
 * - The filters form is never submitted: its fields are read, sent to the API and mirrored in the
 *   URL (?q=jane&status=received&page=2), so the list can be bookmarked. Typing in the search or
 *   min score waits 300 ms; the other fields apply at once. Changing a filter goes back to page 1.
 * - Pagination pushes a history entry, so the back button returns to the previous page.
 * - Invalid filters come back as 422 {errors}: they are shown, and the last results are kept.
 * - Auto-refresh re-queries the same filters and page every 10 s while the tab is visible. It is on
 *   by default and the choice is remembered in this browser.
 * - An application applied after the page was opened is marked "New" (blue badge in place of the
 *   time, blue-tinted row) for 5 minutes from its appliedAt. Reloading the page clears the marks.
 *
 * The element carries data-api-url and data-detail-url (with __id__ for the application id).
 */
const AUTO_REFRESH_KEY = 'applications.autoRefresh';
const TIMEZONE = 'Europe/Madrid';
const DEBOUNCED_TYPES = ['search', 'text', 'number'];
const NEW_FOR = 5 * 60 * 1000;

/* Same classes as templates/components/Badge.html.twig */
const BADGE = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset';
const TONES = {
    neutral: 'bg-surface-muted text-fg-muted ring-line-strong',
    info: 'bg-info-soft text-info ring-info/20',
    success: 'bg-success-soft text-success ring-success/20',
    warning: 'bg-warning-soft text-warning ring-warning/25',
    danger: 'bg-danger-soft text-danger ring-danger/20',
};

/* Same as templates/components/StatusBadge.html.twig */
const STATUSES = {
    received: { label: 'Received', tone: 'neutral' },
    contacted: { label: 'Contacted', tone: 'info' },
    hired: { label: 'Hired', tone: 'success' },
    rejected: { label: 'Rejected', tone: 'danger' },
};

const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: TIMEZONE });
const dateTimeFormat = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: TIMEZONE,
});

const readAutoRefresh = () => {
    try {
        return localStorage.getItem(AUTO_REFRESH_KEY) !== 'off';
    } catch {
        return true;
    }
};

const storeAutoRefresh = (on) => {
    try {
        localStorage.setItem(AUTO_REFRESH_KEY, on ? 'on' : 'off');
    } catch {
        // Storage blocked: the choice lasts until the page is left.
    }
};

export default (interval = 10000) => ({
    /** null until the first answer */
    items: null,
    pagination: null,
    /** The query string of the results on screen */
    query: '',
    page: '1',
    loading: false,
    failed: false,
    /** parameter => message, from a 422 */
    errors: {},
    refreshedAt: null,
    now: Date.now(),
    autoRefresh: readAutoRefresh(),
    interval,
    /** When the component was created: applications applied after it are "new" */
    createdAt: Date.now(),

    init() {
        this.apiUrl = this.$el.dataset.apiUrl;
        this.detailUrl = this.$el.dataset.detailUrl;
        this.readUrl();
        this.load();

        this.clock = setInterval(() => { this.now = Date.now(); }, 1000);
        this.poller = setInterval(() => this.tick(), interval);
        this.onPopState = () => {
            this.readUrl();
            this.load();
        };
        this.onVisibility = () => {
            if (!document.hidden && Date.now() - (this.refreshedAt ?? 0) >= interval) {
                this.tick();
            }
        };
        window.addEventListener('popstate', this.onPopState);
        document.addEventListener('visibilitychange', this.onVisibility);
    },

    destroy() {
        clearInterval(this.clock);
        clearInterval(this.poller);
        clearTimeout(this.debounce);
        this.request?.abort();
        window.removeEventListener('popstate', this.onPopState);
        document.removeEventListener('visibilitychange', this.onVisibility);
    },

    /** Fills the form and the page number from the URL. */
    readUrl() {
        const params = new URLSearchParams(location.search);
        for (const field of this.$refs.filters.elements) {
            if (field.name) {
                field.value = params.get(field.name) ?? '';
            }
        }
        this.page = params.get('page') ?? '1';
    },

    /** The form's filters plus the page, without empty values. */
    params(page = this.page) {
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(this.$refs.filters)) {
            if (value !== '') {
                params.set(name, value);
            }
        }
        if (page !== '1') {
            params.set('page', page);
        }

        return params;
    },

    onInput(event) {
        clearTimeout(this.debounce);
        if (DEBOUNCED_TYPES.includes(event.target.type)) {
            this.debounce = setTimeout(() => this.applyFilters(), 300);
        } else {
            this.applyFilters();
        }
    },

    applyFilters() {
        clearTimeout(this.debounce);
        this.page = '1';
        this.load({ history: 'replace' });
    },

    clearFilters() {
        this.$refs.filters.reset();
        this.page = '1';
        this.load({ history: 'push' });
    },

    goTo(page) {
        this.page = String(page);
        this.load({ history: 'push' });
        this.$refs.results.scrollIntoView({ block: 'start', behavior: 'smooth' });
    },

    toggleAutoRefresh() {
        this.autoRefresh = !this.autoRefresh;
        storeAutoRefresh(this.autoRefresh);
        if (this.autoRefresh) {
            this.load({ query: this.query });
        }
    },

    /** Auto-refresh: the results on screen again, ignoring anything typed but not applied yet. */
    tick() {
        if (this.autoRefresh && !document.hidden && !this.loading && this.items !== null) {
            this.load({ query: this.query });
        }
    },

    /**
     * Queries the API. history: null (leave the URL), 'replace' or 'push'.
     * query: a query string to send instead of the form's.
     */
    async load({ history: mode = null, query = null } = {}) {
        this.request?.abort();
        const request = new AbortController();
        this.request = request;
        const params = query === null ? this.params() : new URLSearchParams(query);
        this.loading = true;

        try {
            const response = await fetch(this.apiUrl + (params.size ? `?${params}` : ''), {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });

            if (response.status === 422) {
                this.errors = (await response.json()).errors ?? {};
                this.failed = false;
                this.items ??= [];
            } else if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            } else {
                const data = await response.json();
                this.items = data.items;
                this.pagination = data.pagination;
                this.errors = {};
                this.failed = false;
                this.refreshedAt = Date.now();
                // Past the last page the API answers the last one
                this.page = String(data.pagination.page);
                if (this.page === '1') {
                    params.delete('page');
                } else {
                    params.set('page', this.page);
                }
            }

            this.query = params.toString();
            this.markInvalidFields();
            if (mode) {
                const url = location.pathname + (this.query ? `?${this.query}` : '');
                window.history[mode === 'push' ? 'pushState' : 'replaceState'](null, '', url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.failed = true;
            }
        } finally {
            if (this.request === request) {
                this.loading = false;
            }
        }
    },

    markInvalidFields() {
        for (const field of this.$refs.filters.elements) {
            if (field.name && field.name in this.errors) {
                field.setAttribute('aria-invalid', 'true');
            } else {
                field.removeAttribute('aria-invalid');
            }
        }
    },

    /** [label, message] pairs for the 422 alert. */
    get errorList() {
        return Object.entries(this.errors).map(([name, message]) => {
            const field = this.$refs.filters.elements.namedItem(name);
            const label = field?.labels?.[0]?.textContent.trim() ?? (name === 'page' ? 'Page' : name);

            return [label, message];
        });
    },

    get hasFilters() {
        return [...new URLSearchParams(this.query).keys()].some((name) => name !== 'page');
    },

    // Refresh button

    get refreshLabel() {
        if (this.loading) {
            return this.refreshedAt === null ? 'Loading…' : 'Refreshing…';
        }
        if (this.failed) {
            return 'Couldn’t refresh';
        }
        if (this.refreshedAt === null) {
            return 'Not loaded yet';
        }

        const seconds = Math.max(0, Math.floor((this.now - this.refreshedAt) / 1000));
        if (seconds < 1) {
            return 'Refreshed just now';
        }
        if (seconds < 60) {
            return `Refreshed ${seconds} ${seconds === 1 ? 'second' : 'seconds'} ago`;
        }

        return `Refreshed ${Math.floor(seconds / 60)} min ago`;
    },

    get refreshHint() {
        return this.autoRefresh
            ? `Auto-refresh every ${interval / 1000} s · click to pause`
            : 'Auto-refresh paused · click to resume';
    },

    // Rows

    detailHref(item) {
        return this.detailUrl.replace('__id__', item.id) + (this.query ? `?${this.query}` : '');
    },

    fullName(item) {
        return `${item.candidate.name} ${item.candidate.surname}`;
    },

    /** Applied after the page was opened, less than 5 minutes ago. Re-evaluated every second via now. */
    isNew(item) {
        const appliedAt = Date.parse(item.appliedAt);

        return appliedAt >= this.createdAt && this.now - appliedAt < NEW_FOR;
    },

    newBadge: `${BADGE} ${TONES.info}`,

    statusBadge(status) {
        const { label, tone } = STATUSES[status] ?? { label: status, tone: 'neutral' };

        return { label, class: `${BADGE} ${TONES[tone]}` };
    },

    /** Bands as templates/components/ScoreBadge.html.twig: unfit 0–49, medium 50–74, high 75–100 */
    scoreBadge(score) {
        const band = score >= 75 ? ['High', 'success'] : score >= 50 ? ['Medium', 'warning'] : ['Unfit', 'danger'];

        return { title: `${band[0]} fit`, class: `${BADGE} ${TONES[band[1]]}` };
    },

    /** As the time_ago Twig filter: "just now", "5 min ago", "3 h ago", "2 d ago", "1 w ago", then the date. */
    timeAgo(iso) {
        const seconds = Math.floor((this.now - Date.parse(iso)) / 1000);
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} h ago`;
        if (seconds < 7 * 86400) return `${Math.floor(seconds / 86400)} d ago`;
        if (seconds < 28 * 86400) return `${Math.floor(seconds / (7 * 86400))} w ago`;

        return dateFormat.format(new Date(iso));
    },

    exactTime(iso) {
        return `${dateTimeFormat.format(new Date(iso))} (${TIMEZONE})`;
    },

    // Pagination, as templates/components/Pagination.html.twig

    pageHref(page) {
        const params = new URLSearchParams(this.query);
        params.set('page', String(page));

        return `?${params}`;
    },

    /** First page, the current one and its neighbours, last page, with a gap marker before jumps. */
    get shownPages() {
        const { page, pages } = this.pagination;
        const shown = [...new Set([1, page - 1, page, page + 1, pages])].filter((p) => p >= 1 && p <= pages).sort((a, b) => a - b);

        return shown.map((p, i) => ({ number: p, gapBefore: i > 0 && p - shown[i - 1] > 1 }));
    },

    get showingFrom() {
        return (this.pagination.page - 1) * this.pagination.perPage + 1;
    },

    get showingTo() {
        return Math.min(this.pagination.page * this.pagination.perPage, this.pagination.total);
    },
});
