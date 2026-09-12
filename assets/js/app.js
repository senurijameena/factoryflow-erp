const App = (function () {
    'use strict';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function request(url, options = {}) {
        const config = {
            method: options.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {})
            }
        };

        const csrf = getCsrfToken();
        if (csrf && !config.headers['X-CSRF-TOKEN']) {
            config.headers['X-CSRF-TOKEN'] = csrf;
        }

        if (options.body) {
            if (options.body instanceof FormData) {
                config.body = options.body;
                if (csrf && !config.body.has('csrf_token')) {
                    config.body.append('csrf_token', csrf);
                }
            } else if (typeof options.body === 'object') {
                config.headers['Content-Type'] = 'application/json';
                const payload = { ...options.body };
                if (csrf && !payload.csrf_token) {
                    payload.csrf_token = csrf;
                }
                config.body = JSON.stringify(payload);
            } else {
                config.body = options.body;
            }
        }

        try {
            const response = await fetch(url, config);
            const contentType = response.headers.get('content-type') || '';

            let data;
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(text || `Server returned HTTP ${response.status}`);
            }

            if (!response.ok || (data && data.success === false)) {
                const errorMsg = (data && data.error) ? data.error : `Request failed (HTTP ${response.status})`;
                const err = new Error(errorMsg);
                err.response = data;
                err.status = response.status;
                throw err;
            }

            return data;
        } catch (err) {
            console.error('[App.request Error]:', err);
            throw err;
        }
    }

    function toast(icon, title) {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                didOpen: (toastEl) => {
                    toastEl.addEventListener('mouseenter', Swal.stopTimer);
                    toastEl.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            Toast.fire({
                icon: icon,
                title: title
            });
        } else {
            alert(title);
        }
    }

    async function confirm(title, text, confirmButtonText = 'Yes, continue', icon = 'warning') {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                confirmButtonText: confirmButtonText,
                reverseButtons: true
            });
            return result.isConfirmed;
        }
        return window.confirm(`${title}\n\n${text}`);
    }

    function bindAjaxForm(formId, callback) {
        const form = typeof formId === 'string' ? document.getElementById(formId) : formId;
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            const actionUrl = form.getAttribute('action') || window.location.href;

            const errorBox = form.querySelector('.form-error-alert');
            if (errorBox) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
            }

            const formData = new FormData(form);
            const csrf = getCsrfToken();
            if (csrf && !formData.has('csrf_token')) {
                formData.append('csrf_token', csrf);
            }

            try {
                const result = await request(actionUrl, {
                    method: form.getAttribute('method') || 'POST',
                    body: formData
                });

                if (typeof callback === 'function') {
                    callback(result);
                }
            } catch (err) {
                const message = err.message || 'An unexpected error occurred. Please try again.';
                if (errorBox) {
                    errorBox.textContent = message;
                    errorBox.classList.remove('d-none');
                } else {
                    toast('error', message);
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            }
        });
    }

    function initLayout() {
        const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
        const sidebar = document.querySelector('.ff-sidebar');
        const backdrop = document.querySelector('.ff-sidebar-backdrop');

        if (sidebarToggleBtn && sidebar) {
            sidebarToggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                sidebar.classList.toggle('show');
                if (backdrop) {
                    backdrop.classList.toggle('show');
                }
            });
        }

        if (backdrop && sidebar) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            });
        }

        document.addEventListener('click', async function (e) {
            const logoutTarget = e.target.closest('[data-action="logout"]');
            if (!logoutTarget) return;

            e.preventDefault();
            const confirmed = await confirm('Log Out', 'Are you sure you want to end your active session?', 'Yes, Log Out', 'question');
            if (!confirmed) return;

            const appUrlMeta = document.querySelector('meta[name="app-url"]');
            const appUrl = appUrlMeta ? appUrlMeta.getAttribute('content') : '';
            const logoutEndpoint = `${appUrl}/api/auth.php?action=logout`;

            try {
                const res = await request(logoutEndpoint, {
                    method: 'POST',
                    body: new FormData()
                });
                if (res && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    window.location.href = `${appUrl}/pages/auth/login.php`;
                }
            } catch (err) {
                window.location.href = `${appUrl}/pages/auth/login.php`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initLayout);

    return {
        csrfToken: getCsrfToken,
        request: request,
        toast: toast,
        confirm: confirm,
        bindAjaxForm: bindAjaxForm
    };
})();
