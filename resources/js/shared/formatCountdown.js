/**
 * The two largest units a countdown has left, so a chip stays short:
 * "1h 04m", "44m 12s", "9s". Seconds are padded so the chip keeps its width
 * while it ticks.
 *
 * Shared by the payment hold chip and the teacher schedule's next-lesson clock.
 */
export function formatCountdown(seconds) {
    const parts = [
        [Math.floor(seconds / 86400), 'd'],
        [Math.floor((seconds % 86400) / 3600), 'h'],
        [Math.floor((seconds % 3600) / 60), 'm'],
        [seconds % 60, 's'],
    ];
    const shown = parts.filter(([value]) => value > 0).slice(0, 2);

    if (shown.length === 0) {
        return '0s';
    }

    return shown
        .map(([value, unit], index) => (index > 0 && unit === 's' ? `${String(value).padStart(2, '0')}s` : `${value}${unit}`))
        .join(' ');
}

export default formatCountdown;
