/**
 * Application detail: saves the hiring status as soon as a value is picked, as JSON, and shows
 * "Status updated". Without JavaScript the form's Save button submits it normally.
 */
export default () => ({
    state: 'idle', // idle | saving | saved | error
    timer: null,

    async save() {
        clearTimeout(this.timer);
        this.state = 'saving';

        try {
            const form = this.$refs.form;
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
            });
            this.state = response.ok ? 'saved' : 'error';
        } catch {
            this.state = 'error';
        }

        if (this.state === 'saved') {
            this.timer = setTimeout(() => (this.state = 'idle'), 2000);
        }
    },
});
