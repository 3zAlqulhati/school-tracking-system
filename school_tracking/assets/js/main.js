document.addEventListener('DOMContentLoaded', function () {

    const hamburger = document.getElementById('sidebarToggle');
    const sidebar   = document.querySelector('.sidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar && sidebar.classList.add('open');
        overlay && overlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar && sidebar.classList.remove('open');
        overlay && overlay.classList.remove('visible');
        document.body.style.overflow = '';
    }

    hamburger && hamburger.addEventListener('click', function () {
        sidebar && sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay && overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.alert-success').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.5s ease';
            alert.style.opacity  = '0';
            alert.style.maxHeight = '0';
            alert.style.padding   = '0';
            alert.style.marginBottom = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.getAttribute('data-confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function () {
            const maxBytes = 10 * 1024 * 1024;
            let warn = this.parentNode.querySelector('.file-size-warning');
            if (this.files.length && this.files[0].size > maxBytes) {
                if (!warn) {
                    warn = document.createElement('span');
                    warn.className = 'form-error file-size-warning';
                    this.parentNode.appendChild(warn);
                }
                warn.textContent = 'File exceeds 10 MB limit.';
                this.value = '';
            } else if (warn) {
                warn.remove();
            }
        });
    });

    document.querySelectorAll('form.needs-validation').forEach(form => {
        form.addEventListener('submit', function (e) {
            let valid = true;
            this.querySelectorAll('[required]').forEach(field => {
                const existing = field.parentNode.querySelector('.field-error');
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger)';
                    if (!existing) {
                        const err = document.createElement('span');
                        err.className = 'form-error field-error';
                        err.textContent = 'This field is required.';
                        field.parentNode.appendChild(err);
                    }
                    valid = false;
                } else {
                    field.style.borderColor = '';
                    existing && existing.remove();
                }
            });
            if (!valid) e.preventDefault();
        });
    });

    document.querySelectorAll('.grade-value').forEach(el => {
        const val = parseFloat(el.textContent);
        if (isNaN(val)) return;
        if      (val >= 90) { el.style.color = 'var(--success)'; el.style.fontWeight = '700'; }
        else if (val >= 70) { el.style.color = 'var(--info)'; el.style.fontWeight = '600'; }
        else if (val >= 50) { el.style.color = 'var(--warning)'; el.style.fontWeight = '600'; }
        else                { el.style.color = 'var(--danger)'; el.style.fontWeight = '700'; }
    });

    document.querySelectorAll('[data-search]').forEach(input => {
        const target = document.getElementById(input.getAttribute('data-search'));
        if (!target) return;
        input.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            target.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });

    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = this.previousElementSibling;
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            this.querySelector('i').className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    });

    const landingNav = document.querySelector('.landing-nav');
    if (landingNav) {
        window.addEventListener('scroll', () => {
            landingNav.classList.toggle('scrolled', window.scrollY > 50);
        });
    }

    function animateCounter(el) {
        const target = parseInt(el.textContent, 10);
        if (isNaN(target) || target === 0) return;
        let current = 0;
        const step  = Math.max(1, Math.floor(target / 40));
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current;
            if (current >= target) clearInterval(timer);
        }, 25);
    }

    if ('IntersectionObserver' in window) {
        const obsv = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) { animateCounter(e.target); obsv.unobserve(e.target); }
            });
        }, { threshold: 0.5 });
        document.querySelectorAll('.stat-value').forEach(el => obsv.observe(el));
    }

    document.querySelectorAll('[data-select-all]').forEach(btn => {
        btn.addEventListener('click', function () {
            const container = document.getElementById(this.getAttribute('data-select-all'));
            if (!container) return;
            const shouldCheck = this.getAttribute('data-action') === 'select';
            container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = shouldCheck;
            });
        });
    });

});
