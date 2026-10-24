/**
 * Money is formatted by Blade (`PlatformSettings::formatMinor`: symbol, a
 * space, then two decimals). The KPI tiles animate a number, so they re-apply
 * that same shape around the prefix Blade supplies — never a second currency
 * rule of their own.
 */
export function formatMinor(prefix, minor, decimals = 2) {
    const value = Number(minor ?? 0) / 100;
    const formatted = value.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

    return prefix ? `${prefix} ${formatted}` : formatted;
}

export default formatMinor;
