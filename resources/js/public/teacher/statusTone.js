/**
 * The three places the availability status appears — the hero, the panel and
 * the booking card — must read as one answer, so they share these tones.
 */
const tones = {
    now: {
        panel: 'border-go/30 bg-go/10',
        text: 'text-go-ink',
        dot: 'bg-go',
        muted: 'text-go-ink/70',
    },
    soon: {
        panel: 'border-amber-300/70 bg-amber-50',
        text: 'text-amber-800',
        dot: 'bg-amber-500',
        muted: 'text-amber-700/80',
    },
    later: {
        panel: 'border-line bg-ink-50',
        text: 'text-ink-700',
        dot: 'bg-brand-500',
        muted: 'text-ink-500',
    },
    none: {
        panel: 'border-line bg-ink-50',
        text: 'text-ink-500',
        dot: 'bg-ink-400',
        muted: 'text-ink-500',
    },
};

export function statusTone(state) {
    return tones[state] ?? tones.later;
}

export default statusTone;
