import Alpine from 'alpinejs';
import './styles/app.css';

/**
 * $token('--color-accent') resolves a design token for display:
 * - value: the computed value, e.g. "oklch(51.1% 0.262 276.966)"
 * - ref: the token it aliases, if any, e.g. "--color-indigo-600"
 */
const declaredValue = (rules, name) => {
    for (const rule of rules) {
        const value = rule.style?.getPropertyValue(name) || (rule.cssRules && declaredValue(rule.cssRules, name));
        if (value) {
            return value;
        }
    }

    return '';
};

Alpine.magic('token', () => (name) => {
    const declared = [...document.styleSheets].map((sheet) => declaredValue(sheet.cssRules, name)).find(Boolean) ?? '';

    return {
        value: getComputedStyle(document.documentElement).getPropertyValue(name).trim(),
        ref: declared.trim().match(/^var\((--[\w-]+)\)$/)?.[1] ?? null,
    };
});

window.Alpine = Alpine;
Alpine.start();
