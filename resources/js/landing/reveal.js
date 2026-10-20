/**
 * Fades and lifts sections into view on first scroll. Anything animated is opt
 * in: without JavaScript, or with reduced motion, the content is simply visible.
 */
export function createRevealDirective() {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    return {
        mounted(element, binding) {
            if (prefersReducedMotion) {
                return;
            }

            element.classList.add('lp-reveal');

            if (binding.value) {
                element.style.transitionDelay = `${binding.value}ms`;
            }

            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            element.classList.add('lp-reveal--visible');
                            observer.disconnect();
                        }
                    });
                },
                { rootMargin: '0px 0px -10% 0px', threshold: 0.05 },
            );

            observer.observe(element);
            element.lpRevealObserver = observer;
        },
        unmounted(element) {
            element.lpRevealObserver?.disconnect();
        },
    };
}
