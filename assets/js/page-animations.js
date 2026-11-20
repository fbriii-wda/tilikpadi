document.addEventListener('DOMContentLoaded', function () {
    if (
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
        || !('IntersectionObserver' in window)
    ) {
        return;
    }

    const revealElements = document.querySelectorAll(
        '.page-header, .card, .table-responsive, .login-card, .error-actions-group, .footer-custom'
    );
    const observer = new IntersectionObserver(function (entries, activeObserver) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                activeObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.08,
        rootMargin: '0px 0px -24px 0px'
    });

    revealElements.forEach(function (element) {
        element.classList.add('scroll-reveal');
        observer.observe(element);
    });
});
