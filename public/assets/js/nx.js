window.NX = (() => {
    /* =========================================================
     * DEVTOOLS
     * ========================================================= */
    const dev = {
        enabled: false,
        log: (...a) => dev.enabled && console.log('[NX]', ...a),
        warn: (...a) => dev.enabled && console.warn('[NX]', ...a),
        error: (...a) => dev.enabled && console.error('[NX]', ...a),
        enable: () => { dev.enabled = true; },
        disable: () => { dev.enabled = false; }
    };
    /* =========================================================
     * LIFECYCLE
     * ========================================================= */
    const lifecycle = (() => {
        const hooks = new Map();
        return {
            on: (name, fn) => {
                if (!hooks.has(name)) hooks.set(name, new Set());
                hooks.get(name).add(fn);
                return () => hooks.get(name)?.delete(fn);
            },
            run: async (name, payload) => {
                const list = hooks.get(name);
                if (!list) return;
                for (const fn of list) await fn(payload);
            }
        };
    })();
    /* =========================================================
     * MIDDLEWARE
     * ========================================================= */
    const __middleware = [];
    const middleware = {
        use: (fn) => {
            if (typeof fn === 'function') __middleware.push(fn);
        }
    };
    const runMiddleware = async (ctx, next) => {
        let i = -1;
        const dispatch = async (index) => {
            if (index <= i) throw new Error('next() called multiple times');
            i = index;
            const fn = __middleware[index];
            if (!fn) return next();
            return fn(ctx, () => dispatch(index + 1));
        };
        return dispatch(0);
    };
    /* =========================================================
     * CORE
     * ========================================================= */
    const parseJson = async (res) => {
        const text = await res.text();

        if (!text.trim()) {
            if (!res.ok) {
                throw new Error(`Request failed with status ${res.status}.`);
            }
            return { ok: true, success: true, data: null, message: '' };
        }

        let json;

        try {
            json = JSON.parse(text);
        } catch {
            throw new Error(`Invalid JSON response: ${text.slice(0, 300)}`);
        }

        if (!res.ok) {
            throw new Error(json?.message || `Request failed with status ${res.status}.`);
        }

        return json;
    };
    let sessionRedirecting = false;
    const redirectExpiredSession = () => {
        if (sessionRedirecting || window.location.pathname === '/login') return;
        sessionRedirecting = true;
        window.location.replace('/login');
    };
    const sessionWatchdog = (() => {
        let timer = null;
        const reset = () => {
            const topbar = document.querySelector('.topbar[data-session-timeout]');
            const seconds = Number(topbar?.dataset.sessionTimeout || 0);
            if (!seconds) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => window.location.reload(), seconds * 1000);
        };
        const init = () => {
            ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach((eventName) => {
                document.addEventListener(eventName, reset, { passive: true });
            });
            reset();
        };
        return { init };
    })();
    const request = async (url, options = {}) => {
        const ctx = { url, options, response: null };

        await runMiddleware(ctx, async () => {
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
                ...(ctx.options.headers || {})
            };

            const res = await fetch(ctx.url, {
                ...ctx.options,
                headers
            });

            if (res.status === 401) redirectExpiredSession();

            ctx.response = await parseJson(res);
        });

        return ctx.response;
    };
    /* =========================================================
 * API
 * ========================================================= */
    const api = (() => {
        const prepareBody = (body, headers = {}) => {
            if (body == null) {
                return { body: null, headers };
            }

            if (
                body instanceof FormData ||
                body instanceof URLSearchParams ||
                typeof body === 'string' ||
                body instanceof Blob ||
                body instanceof ArrayBuffer
            ) {
                return { body, headers };
            }

            if (typeof body === 'object') {
                return {
                    body: JSON.stringify(body),
                    headers: {
                        'Content-Type': 'application/json; charset=UTF-8',
                        ...headers
                    }
                };
            }

            return { body, headers };
        };

        const normalize = (json) => {
            if (!json.ok && json.success !== true) {
                const msg = json.message || 'Request failed.';
                const errors = Object.values(json.errors || {})
                    .flatMap(v => Array.isArray(v) ? v : [v])
                    .filter(Boolean)
                    .join('\n');

                throw new Error(errors ? `${msg}\n${errors}` : msg);
            }

            return json;
        };

        return {
            get: async (url) => {
                const json = await request(url);
                normalize(json);
                return json.data;
            },

            post: async (url, body = null, options = {}) => {
                const prepared = prepareBody(body, options.headers || {});
                const json = await request(url, {
                    method: 'POST',
                    ...options,
                    headers: prepared.headers,
                    body: prepared.body
                });
                return normalize(json);
            },

            put: async (url, body = null, options = {}) => {
                const prepared = prepareBody(body, options.headers || {});
                const json = await request(url, {
                    method: 'PUT',
                    ...options,
                    headers: prepared.headers,
                    body: prepared.body
                });
                return normalize(json);
            },

            delete: async (url, body = null, options = {}) => {
                const prepared = prepareBody(body, options.headers || {});
                const json = await request(url, {
                    method: 'DELETE',
                    ...options,
                    headers: prepared.headers,
                    body: prepared.body
                });
                return normalize(json);
            },

            form: (url, fd, options = {}) => api.post(url, fd, options),

            urlEncoded: async (url, payload, options = {}) => {
                const json = await request(url, {
                    method: 'POST',
                    ...options,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        ...(options.headers || {})
                    },
                    body: new URLSearchParams(payload).toString()
                });

                return normalize(json);
            },

            full: (url, options = {}) => request(url, options)
        };
    })();
    /* =========================================================
     * UTIL
     * ========================================================= */
    const util = {
        escape: (v) => String(v ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;'),
        num: (v, f = 0) => Number.isFinite(Number(v)) ? Number(v) : f,
        upper: (v) => String(v ?? '').trim().toUpperCase(),
        lower: (v) => String(v ?? '').trim().toLowerCase(),
        safeArray: (v) => Array.isArray(v) ? v : [],
        safeObject: (v) => (v && typeof v === 'object' && !Array.isArray(v) ? v : {}),
        clone: (v) => {
            if (typeof structuredClone === 'function') {
                try { return structuredClone(v); } catch {}
            }
            try { return JSON.parse(JSON.stringify(v)); }
            catch { return v; }
        },
        formatDateTime: (v) => {
            if (!v) return '-';
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString();
        },
        formatNumber: (v, digits = 0) => {
            const n = Number(v);
            return Number.isFinite(n) ? n.toFixed(digits) : '-';
        },
        debounce: (fn, wait = 250) => {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...args), wait);
            };
        },
        throttle: (fn, wait = 250) => {
            let timer = null;
            let lastArgs = null;
            return (...args) => {
                lastArgs = args;
                if (timer) return;
                fn(...args);
                timer = setTimeout(() => {
                    timer = null;
                    if (lastArgs !== args) fn(...lastArgs);
                }, wait);
            };
        },
        uid: (prefix = 'nx') => `${prefix}-${Math.random().toString(36).slice(2, 10)}`,
        deepGet: (obj, path, fallback = undefined) => {
            if (!path) return obj;
            const parts = Array.isArray(path) ? path : String(path).split('.');
            let ref = obj;
            for (const p of parts) {
                ref = ref?.[p];
                if (ref === undefined) return fallback;
            }
            return ref;
        },
        deepSet: (obj, path, value) => {
            const parts = Array.isArray(path) ? path : String(path).split('.');
            let ref = obj;
            for (let i = 0; i < parts.length - 1; i++) {
                const p = parts[i];
                if (ref[p] == null || typeof ref[p] !== 'object') ref[p] = {};
                ref = ref[p];
            }
            ref[parts[parts.length - 1]] = value;
            return obj;
        }
    };
    /* =========================================================
     * STORAGE
     * ========================================================= */
    const storage = {
        get: (k, fallback = null) => {
            try { return JSON.parse(localStorage.getItem(k)) ?? fallback; }
            catch { return fallback; }
        },
        set: (k, v) => {
            try {
                localStorage.setItem(k, JSON.stringify(v));
                return true;
            } catch {
                return false;
            }
        },
        remove: (k) => {
            try {
                localStorage.removeItem(k);
                return true;
            } catch {
                return false;
            }
        }
    };
    /* =========================================================
     * DOM
     * ========================================================= */
    const dom = {
        $: (s, r = document) => r.querySelector(s),
        $$: (s, r = document) => [...r.querySelectorAll(s)],
        html: (el, v = '') => el && (el.innerHTML = v),
        text: (el, v = '') => el && (el.textContent = v),
        val: (el, v = '') => el && (el.value = v),
        show: (el) => el?.classList.remove('d-none'),
        hide: (el) => el?.classList.add('d-none'),
        toggle: (el, force) => el?.classList.toggle('d-none', force),
        attr: (el, name, value) => {
            if (!el) return;
            if (value === undefined) return el.getAttribute(name);
            el.setAttribute(name, value);
        },
        create: (tag, attrs = {}, html = '') => {
            const el = document.createElement(tag);
            Object.entries(attrs).forEach(([k, v]) => {
                if (k === 'class') {
                    el.className = v;
                } else if (k === 'dataset' && v && typeof v === 'object') {
                    Object.entries(v).forEach(([dk, dv]) => { el.dataset[dk] = dv; });
                } else if (k === 'style' && v && typeof v === 'object') {
                    Object.assign(el.style, v);
                } else if (k in el && k !== 'style') {
                    try { el[k] = v; } catch { el.setAttribute(k, v); }
                } else {
                    el.setAttribute(k, v);
                }
            });
            el.innerHTML = html;
            return el;
        },
        on: (root, evt, selector, handler) => {
            root?.addEventListener(evt, (e) => {
                const target = e.target instanceof Element ? e.target.closest(selector) : null;
                if (target) handler(e, target);
            });
        }
    };
    /* =========================================================
     * TOOLTIP
     * ========================================================= */
    const tooltip = {
        init: (root = document) => {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    bootstrap.Tooltip.getOrCreateInstance(el);
                }
            });
        },
        dispose: (root = document) => {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const t = bootstrap.Tooltip.getInstance(el);
                    t?.dispose();
                }
            });
        },
        refresh: (root = document) => {
            tooltip.dispose(root);
            tooltip.init(root);
        }
    };
    /* =========================================================
     * CLIPBOARD
     * ========================================================= */
    const clipboard = {
        copy: async (value, label = 'Copied') => {
            const text = String(value ?? '').trim();
            if (!text) {
                ui.toast('error', 'Nothing to copy');
                return false;
            }
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    Object.assign(ta.style, {
                        position: 'fixed',
                        top: '-9999px',
                        left: '-9999px',
                        opacity: '0'
                    });
                    document.body.appendChild(ta);
                    ta.select();
                    ta.setSelectionRange(0, ta.value.length);
                    const ok = document.execCommand('copy');
                    ta.remove();
                    if (!ok) throw new Error('Copy failed');
                }

                ui.toast('success', `${label} copied`);
                return true;
            } catch {
                ui.toast('error', 'Copy failed');
                return false;
            }
        }
    };
    /* =========================================================
     * UI
     * ========================================================= */
    const ui = {
        toast: (icon = 'success', title = '') => {
            if (typeof Swal === 'undefined') {
                console.log(`[${icon}] ${title}`);
                return;
            }
            return Swal.fire({
                toast: true,
                position: 'top-end',
                icon,
                title,
                showConfirmButton: false,
                timer: icon === 'error' ? 5000 : 2500
            });
        },
        swal: (opts = {}) => {
            if (typeof Swal === 'undefined') {
                console.log('Swal missing:', opts);
                return Promise.resolve({ isConfirmed: true });
            }
            return Swal.fire(opts);
        },
        confirm: (opts = {}) => ui.swal({
            icon: 'warning',
            showCancelButton: true,
            ...opts
        }),
        confirmDelete: (title = 'Delete?', text = 'This cannot be undone.') =>
            ui.confirm({ title, text, confirmButtonText: 'Delete' }),
        loading: (title = 'Processing...') => {
            if (typeof Swal === 'undefined') return;
            return Swal.fire({
                title,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        },
        closeLoading: () => {
            if (typeof Swal !== 'undefined') Swal.close();
        }
    };
    /* =========================================================
     * MODAL
     * ========================================================= */
    const modal = {
        instance: (el) => {
            if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
            return bootstrap.Modal.getOrCreateInstance(el);
        },
        open: (el) => modal.instance(el)?.show(),
        close: (el) => modal.instance(el)?.hide()
    };
    /* =========================================================
 * MAPS
 * ========================================================= */
    const maps = {
        fixLeafletDefaultIcons: () => {
            if (typeof window === 'undefined' || typeof window.L === 'undefined') return;

            delete L.Icon.Default.prototype._getIconUrl;

            L.Icon.Default.mergeOptions({
                iconRetinaUrl: '/assets/leaflet/images/marker-icon-2x.png',
                iconUrl: '/assets/leaflet/images/marker-icon.png',
                shadowUrl: '/assets/leaflet/images/marker-shadow.png'
            });
        }
    };
    /* =========================================================
     * RENDER
     * ========================================================= */
    const render = {
        badge: (status, {
            baseClass = 'nx-soft-badge',
            success = ['ASSIGNED', 'ONLINE', 'KNOWN', 'MATCHED', 'ACTIVE', 'SUCCESS'],
            danger = ['OFFLINE', 'ROGUE', 'FAILED', 'ERROR', 'INACTIVE'],
            warning = ['STALE', 'WARNING', 'PENDING', 'MAINTENANCE']
        } = {}) => {
            const safe = util.upper(status);
            let extra = '';
            if (success.includes(safe)) extra = ' nx-soft-badge-success';
            else if (danger.includes(safe)) extra = ' nx-soft-badge-danger';
            else if (warning.includes(safe)) extra = ' nx-soft-badge-warning';

            return `<span class="${baseClass}${extra}">${util.escape(safe || '-')}</span>`;
        },
        emptyState: ({
                         title = 'No data found',
                         text = '',
                         buttonLabel = '',
                         buttonId = '',
                         iconClass = 'bi bi-inbox'
                     } = {}) => `
        <div class="card border-0 shadow-sm nx-surface-card">
            <div class="nx-empty-state">
                <div class="nx-empty-icon">
                    <i class="${util.escape(iconClass)}"></i>
                </div>
                <div class="nx-empty-title">${util.escape(title)}</div>
                <div class="nx-empty-text">${util.escape(text)}</div>
                ${buttonLabel && buttonId ? `
                    <button type="button" class="btn btn-primary" id="${util.escape(buttonId)}">
                        ${util.escape(buttonLabel)}
                    </button>
                ` : ''}
            </div>
        </div>
    `,
        skeletonTable: (rows = 6, cols = 7) => {
            const head = Array.from({ length: cols })
                .map(() => '<th><div class="nx-skeleton nx-skeleton-line"></div></th>')
                .join('');
            const body = Array.from({ length: rows }).map(() => `
            <tr>
                ${Array.from({ length: cols })
                .map(() => '<td><div class="nx-skeleton nx-skeleton-line"></div></td>')
                .join('')}
            </tr>
        `).join('');
            return `
            <div class="card border-0 shadow-sm nx-surface-card">
                <div class="table-responsive nx-table-wrap nx-data-table-wrap">
                    <table class="table align-middle mb-0 nx-data-table">
                        <thead class="nx-sticky-head"><tr>${head}</tr></thead>
                        <tbody>${body}</tbody>
                    </table>
                </div>
            </div>
        `;
        }
    };
    /* =========================================================
     * LAYOUT
     * ========================================================= */
    const layout = {
        pageHeader: ({
                         title = '',
                         subtitle = '',
                         actions = ''
                     } = {}) => `
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1 fw-semibold">${util.escape(title)}</h4>
                <div class="text-muted small">${util.escape(subtitle)}</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">${actions}</div>
        </div>
    `,
        stats: (items = []) => `
        <div class="row g-3 mb-3">
            ${items.map((i) => `
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm nx-surface-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1">${util.escape(i.label || '')}</div>
                            <div class="fw-bold fs-4">${util.escape(i.value ?? '')}</div>
                            ${i.subtext ? `<div class="text-muted small mt-1">${util.escape(i.subtext)}</div>` : ''}
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `,
        section: ({
                      title = '',
                      content = '',
                      actions = ''
                  } = {}) => `
        <div class="card border-0 shadow-sm nx-surface-card mb-3">
            <div class="card-body">
                ${title || actions ? `
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h6 class="fw-semibold mb-0">${util.escape(title)}</h6>
                        <div>${actions}</div>
                    </div>
                ` : ''}
                ${content}
            </div>
        </div>
    `,
        toolbar: ({
                      left = '',
                      right = ''
                  } = {}) => `
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="d-flex gap-2 flex-wrap">${left}</div>
            <div class="d-flex gap-2 flex-wrap">${right}</div>
        </div>
    `
    };
    /* =========================================================
     * DIALOG
     * ========================================================= */
    const dialog = {
        confirm: async ({
                            title = 'Confirm',
                            text = ''
                        } = {}) => {
            const result = await ui.confirm({ title, text });
            return result.isConfirmed;
        },
        info: ({
                   title = '',
                   html = '',
                   icon = 'info'
               } = {}) => ui.swal({ title, html, icon }),

        form: ({
                   title = '',
                   html = '',
                   preConfirm = null
               } = {}) => ui.swal({
            title,
            html,
            showCancelButton: true,
            focusConfirm: false,
            preConfirm
        })
    };
    /* =========================================================
     * FORMS
     * ========================================================= */
    const forms = {
        data: (obj = {}) => {
            const fd = new FormData();
            Object.entries(obj).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    v.forEach((item) => fd.append(`${k}[]`, item ?? ''));
                } else {
                    fd.append(k, v ?? '');
                }
            });
            return fd;
        },
        toObject: (formEl) => {
            const fd = new FormData(formEl);
            const out = {};
            for (const [k, v] of fd.entries()) {
                if (Object.prototype.hasOwnProperty.call(out, k)) {
                    if (!Array.isArray(out[k])) out[k] = [out[k]];
                    out[k].push(v);
                } else {
                    out[k] = v;
                }
            }
            formEl?.querySelectorAll('input[type="checkbox"][name]').forEach((input) => {
                if (!Object.prototype.hasOwnProperty.call(out, input.name)) {
                    out[input.name] = false;
                }
            });

            return out;
        },
        populate: (formEl, data = {}) => {
            if (!formEl) return;

            Object.entries(data).forEach(([name, value]) => {
                const directInputs = formEl.querySelectorAll(`[name="${name}"]`);
                const arrayInputs = formEl.querySelectorAll(`[name="${name}[]"]`);
                const inputs = [...directInputs, ...arrayInputs];

                if (!inputs.length) return;

                inputs.forEach((input) => {
                    if (input.type === 'checkbox') {
                        if (Array.isArray(value)) {
                            input.checked = value.map(String).includes(String(input.value));
                        } else if (typeof value === 'boolean') {
                            input.checked = value;
                        } else {
                            input.checked = String(input.value) === String(value) || value === true;
                        }
                        return;
                    }

                    if (input.type === 'radio') {
                        input.checked = String(input.value) === String(value);
                        return;
                    }

                    if (input.tagName === 'SELECT' && input.multiple) {
                        const values = Array.isArray(value) ? value.map(String) : [String(value ?? '')];
                        [...input.options].forEach((opt) => {
                            opt.selected = values.includes(String(opt.value));
                        });
                        return;
                    }

                    input.value = Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
                });
            });
        },
        disable: (formEl, state = true) => {
            formEl?.querySelectorAll('input, select, textarea, button')
                .forEach((el) => { el.disabled = state; });
        },
        fill: (entries = []) => {
            entries.forEach(([el, v]) => {
                if (el) el.value = v ?? '';
            });
        },
        reset: (formEl) => formEl?.reset()
    };
    /* =========================================================
     * SELECT
     * ========================================================= */
    const select = {
        fill: (el, rows = [], {
            valueKey = 'id',
            label = (r) => r.name,
            placeholder = 'Select option',
            selected = ''
        } = {}) => {
            if (!el) return;
            el.innerHTML = `
            <option value="">${util.escape(placeholder)}</option>
            ${rows.map((r) => {
                const value = r?.[valueKey] ?? '';
                const isSelected = String(value) === String(selected) ? 'selected' : '';
                return `<option value="${util.escape(value)}" ${isSelected}>${util.escape(label(r))}</option>`;
            }).join('')}
        `;
        },
        clear: (el, placeholder = 'Select option') => {
            if (!el) return;
            el.innerHTML = `<option value="">${util.escape(placeholder)}</option>`;
        }
    };
    /* =========================================================
     * VALIDATION
     * ========================================================= */
    const validation = {
        run: (data = {}, rules = {}) => {
            const errors = {};
            Object.entries(rules).forEach(([field, fieldRules]) => {
                const value = data[field];
                fieldRules.forEach((rule) => {
                    if (rule === 'required') {
                        const empty = value === null || value === undefined || String(value).trim() === '';
                        if (empty) {
                            errors[field] = errors[field] || [];
                            errors[field].push('This field is required.');
                        }
                    }
                    if (rule === 'numeric' && String(value ?? '').trim() !== '' && isNaN(Number(value))) {
                        errors[field] = errors[field] || [];
                        errors[field].push('Must be a number.');
                    }
                    if (typeof rule === 'object' && rule.minLength !== undefined) {
                        if (String(value ?? '').length < rule.minLength) {
                            errors[field] = errors[field] || [];
                            errors[field].push(`Minimum length is ${rule.minLength}.`);
                        }
                    }
                    if (typeof rule === 'object' && rule.maxLength !== undefined) {
                        if (String(value ?? '').length > rule.maxLength) {
                            errors[field] = errors[field] || [];
                            errors[field].push(`Maximum length is ${rule.maxLength}.`);
                        }
                    }
                    if (typeof rule === 'function') {
                        const result = rule(value, data);
                        if (result !== true) {
                            errors[field] = errors[field] || [];
                            errors[field].push(result || 'Invalid value.');
                        }
                    }
                });
            });
            return {
                ok: Object.keys(errors).length === 0,
                errors
            };
        },
        clearFormErrors: (formEl) => {
            if (!formEl) return;
            formEl.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            formEl.querySelectorAll('[data-nx-error-for]').forEach((el) => el.remove());
        },
        showFormErrors: (formEl, errors = {}) => {
            if (!formEl) return;
            Object.entries(errors).forEach(([field, messages]) => {
                const input = formEl.querySelector(`[name="${field}"]`);
                if (!input) return;
                input.classList.add('is-invalid');
                let errorBox = formEl.querySelector(`[data-nx-error-for="${field}"]`);
                if (!errorBox) {
                    errorBox = document.createElement('div');
                    errorBox.className = 'invalid-feedback d-block';
                    errorBox.setAttribute('data-nx-error-for', field);
                    input.insertAdjacentElement('afterend', errorBox);
                }
                errorBox.innerHTML = messages.map((m) => util.escape(m)).join('<br>');
            });
        }
    };
    /* =========================================================
     * FORM
     * ========================================================= */
    const form = {
        create: ({
                     el,
                     submit,
                     map = (formEl) => new FormData(formEl),
                     onSuccess = null,
                     onError = null
                 } = {}) => {
            if (!el) return null;
            const handler = async (e) => {
                e.preventDefault();
                try {
                    const payload = map(el);
                    const result = await submit(payload, e);
                    if (typeof onSuccess === 'function') await onSuccess(result, e);
                } catch (err) {
                    if (typeof onError === 'function') {
                        await onError(err, e);
                    } else {
                        ui.swal({
                            icon: 'error',
                            title: 'Form Error',
                            text: err?.message || 'Failed to submit form.'
                        });
                    }
                }
            };
            el.addEventListener('submit', handler);
            return {
                destroy: () => el.removeEventListener('submit', handler)
            };
        }
    };
    /* =========================================================
     * FORM BUILDER
     * ========================================================= */
    const formBuilder = {
        create: ({
                     el,
                     fields = [],
                     rules = {},
                     submit,
                     map = null,
                     onSuccess = null,
                     onError = null,
                     resetOnSuccess = false
                 } = {}) => {
            const formEl = typeof el === 'string' ? dom.$(el) : el;
            if (!formEl) return null;
            const getData = () => {
                if (typeof map === 'function') return map(formEl);
                const data = {};
                if (fields.length) {
                    fields.forEach((f) => {
                        const input = formEl.querySelector(`[name="${f.name}"]`);
                        if (!input) return;
                        data[f.name] = input.type === 'checkbox' ? input.checked : (input.value ?? '');
                    });
                    return data;
                }
                return forms.toObject(formEl);
            };
            const handler = async (e) => {
                e.preventDefault();
                validation.clearFormErrors(formEl);
                const data = getData();
                const check = validation.run(data, rules);
                if (!check.ok) {
                    validation.showFormErrors(formEl, check.errors);

                    if (typeof onError === 'function') {
                        await onError({ type: 'validation', errors: check.errors, data }, e);
                    } else {
                        ui.toast('error', 'Please fix the highlighted fields.');
                    }
                    return;
                }
                try {
                    const result = await submit(data, e, formEl);

                    if (resetOnSuccess) formEl.reset();

                    if (typeof onSuccess === 'function') {
                        await onSuccess(result, e, formEl);
                    }
                } catch (err) {
                    if (typeof onError === 'function') {
                        await onError({ type: 'submit', error: err, data }, e);
                    } else {
                        ui.swal({
                            icon: 'error',
                            title: 'Form Error',
                            text: err?.message || 'Failed to submit form.'
                        });
                    }
                }
            };
            formEl.addEventListener('submit', handler);
            return {
                getData,
                validate: () => validation.run(getData(), rules),
                reset: () => {
                    validation.clearFormErrors(formEl);
                    formEl.reset();
                },
                destroy: () => formEl.removeEventListener('submit', handler)
            };
        }
    };
    /* =========================================================
     * TABLE
     * ========================================================= */
    const table = {
        filterRows: (rows, search = '', fields = []) => {
            const safeRows = util.safeArray(rows);
            const q = String(search ?? '').trim().toLowerCase();
            if (!q) return safeRows;
            return safeRows.filter((row) => {
                const haystack = fields.map((f) => row?.[f] ?? '').join(' ').toLowerCase();
                return haystack.includes(q);
            });
        },
        sortRows: (
            rows,
            cfg = {},
            numericOrDateKeys = ['last_seen', 'autofind_time', 'created_at', 'updated_at', 'uptime_seconds']
        ) => {
            const safeRows = util.safeArray(rows);
            if (!cfg?.key) return safeRows;
            const dir = cfg.dir === 'desc' ? -1 : 1;
            const key = cfg.key;
            return [...safeRows].sort((a, b) => {
                let av = a?.[key];
                let bv = b?.[key];

                if (numericOrDateKeys.includes(key)) {
                    av = Number.isFinite(Number(av)) ? Number(av) : (av ? new Date(av).getTime() : 0);
                    bv = Number.isFinite(Number(bv)) ? Number(bv) : (bv ? new Date(bv).getTime() : 0);
                } else {
                    av = String(av ?? '').toLowerCase();
                    bv = String(bv ?? '').toLowerCase();
                }
                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;
                return 0;
            });
        },
        paginate: (rows, pager = { currentPage: 1, rowsPerPage: 20 }) => {
            const safeRows = util.safeArray(rows);
            const currentPage = Math.max(1, Number(pager.currentPage) || 1);
            const rowsPerPage = Math.max(1, Number(pager.rowsPerPage) || 20);
            const totalRows = safeRows.length;
            const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
            const pageNum = Math.min(currentPage, totalPages);
            const start = (pageNum - 1) * rowsPerPage;
            return {
                rows: safeRows.slice(start, start + rowsPerPage),
                totalRows,
                totalPages,
                currentPage: pageNum,
                rowsPerPage
            };
        },
        sortIndicator: (current, key) => {
            if (!current || current.key !== key) {
                return '<span class="sort-indicator sort-indicator--unsorted" aria-hidden="true">↕</span>';
            }
            return current.dir === 'asc'
                ? '<span class="sort-indicator sort-indicator--ascending" aria-hidden="true">↑</span>'
                : '<span class="sort-indicator sort-indicator--descending" aria-hidden="true">↓</span>';
        },
        process: ({
                      rows = [],
                      search = '',
                      searchFields = [],
                      filter = null,
                      sort = null,
                      pager = { currentPage: 1, rowsPerPage: 20 }
                  } = {}) => {
            let out = table.filterRows(rows, search, searchFields);
            if (typeof filter === 'function') out = out.filter(filter);
            out = table.sortRows(out, sort);
            return table.paginate(out, pager);
        }
    };
    /* =========================================================
  * DATATABLE
  * ========================================================= */
    const datatable = {
        create: ({
                     el,
                     rows = [],
                     columns = [],
                     search = true,
                     paginate = true,
                     pager = { currentPage: 1, rowsPerPage: 20 },
                     sort = { key: null, dir: 'asc' }
                 } = {}) => {
            let stateRows = util.safeArray(rows);
            let stateSearch = '';
            let stateSort = { ...sort };
            let statePager = { ...pager };

            const container = typeof el === 'string' ? dom.$(el) : el;
            if (!container) return null;

            const renderTable = () => {
                const processed = table.process({
                    rows: stateRows,
                    search: stateSearch,
                    searchFields: columns.map((c) => c.key),
                    sort: stateSort,
                    pager: statePager
                });

                const pagerHtml = paginate ? `
                <div class="nx-table-footer-shell">
                    <div class="nx-table-footer">
                        <div class="nx-table-footer__summary">
                            <span class="nx-table-footer__page">Page ${processed.currentPage} of ${processed.totalPages}</span>
                            <span class="nx-table-footer__records">${processed.totalRows} record${processed.totalRows !== 1 ? 's' : ''}</span>
                        </div>
                        <div class="nx-table-footer__controls">
                            <div class="nx-table-footer__per-page">
                                <label class="nx-table-footer__label mb-0" for="">Rows per page</label>
                                <select class="form-select form-select-sm nx-dt-rows nx-table-footer__select">
                                    <option value="10" ${processed.rowsPerPage === 10 ? 'selected' : ''}>10</option>
                                    <option value="20" ${processed.rowsPerPage === 20 ? 'selected' : ''}>20</option>
                                    <option value="50" ${processed.rowsPerPage === 50 ? 'selected' : ''}>50</option>
                                    <option value="100" ${processed.rowsPerPage === 100 ? 'selected' : ''}>100</option>
                                </select>
                            </div>
                            <div class="nx-table-footer__actions">
                                <button class="btn btn-sm nx-table-footer__btn nx-dt-prev" type="button" ${processed.currentPage <= 1 ? 'disabled' : ''}>Prev</button>
                                <button class="btn btn-sm nx-table-footer__btn nx-dt-next" type="button" ${processed.currentPage >= processed.totalPages ? 'disabled' : ''}>Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            ` : '';

                dom.html(container, `
                ${search ? `<input class="form-control mb-2 nx-dt-search" placeholder="Search...">` : ''}
                <div class="table-responsive nx-table-wrap">
                    <table class="table align-middle mb-0">
                        <thead class="nx-sticky-head">
                            <tr>
                                ${columns.map((c) => `
                                    <th data-key="${c.key}" class="nx-dt-sort">
                                        ${util.escape(c.label)}
                                        ${table.sortIndicator(stateSort, c.key)}
                                    </th>
                                `).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${processed.rows.map((row) => `
                                <tr>
                                    ${columns.map((c) => `
                                        <td>${c.render ? c.render(row[c.key], row) : util.escape(row[c.key] ?? '')}</td>
                                    `).join('')}
                                </tr>
                            `).join('') || `
                                <tr>
                                    <td colspan="${columns.length}" class="text-center text-muted py-4">No records found.</td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
                ${pagerHtml}
            `);

                const s = container.querySelector('.nx-dt-search');
                if (s) {
                    s.value = stateSearch;
                    s.oninput = util.debounce((e) => {
                        stateSearch = e.target.value;
                        statePager.currentPage = 1;
                        renderTable();
                    }, 200);
                }

                container.querySelectorAll('.nx-dt-sort').forEach((th) => {
                    th.onclick = () => {
                        const key = th.dataset.key;
                        if (stateSort.key === key) {
                            stateSort.dir = stateSort.dir === 'asc' ? 'desc' : 'asc';
                        } else {
                            stateSort.key = key;
                            stateSort.dir = 'asc';
                        }
                        renderTable();
                    };
                });

                const rowsPerPage = container.querySelector('.nx-dt-rows');
                if (rowsPerPage) {
                    rowsPerPage.onchange = (e) => {
                        statePager.rowsPerPage = parseInt(e.target.value || '20', 10) || 20;
                        statePager.currentPage = 1;
                        renderTable();
                    };
                }

                const prev = container.querySelector('.nx-dt-prev');
                if (prev) {
                    prev.onclick = () => {
                        if (processed.currentPage <= 1) return;
                        statePager.currentPage--;
                        renderTable();
                    };
                }

                const next = container.querySelector('.nx-dt-next');
                if (next) {
                    next.onclick = () => {
                        if (processed.currentPage >= processed.totalPages) return;
                        statePager.currentPage++;
                        renderTable();
                    };
                }

                tooltip.refresh(container);
            };

            renderTable();

            return {
                setRows(newRows) {
                    stateRows = util.safeArray(newRows);
                    statePager.currentPage = 1;
                    renderTable();
                },
                reload() {
                    renderTable();
                },
                setSearch(value = '') {
                    stateSearch = String(value ?? '');
                    statePager.currentPage = 1;
                    renderTable();
                },
                setSort(key, dir = 'asc') {
                    stateSort = { key, dir: dir === 'desc' ? 'desc' : 'asc' };
                    renderTable();
                },
                setPage(page = 1) {
                    statePager.currentPage = Math.max(1, parseInt(page, 10) || 1);
                    renderTable();
                },
                setRowsPerPage(value = 20) {
                    statePager.rowsPerPage = Math.max(1, parseInt(value, 10) || 20);
                    statePager.currentPage = 1;
                    renderTable();
                },
                getState() {
                    return util.clone({
                        rows: stateRows,
                        search: stateSearch,
                        sort: stateSort,
                        pager: statePager
                    });
                },
                destroy() {
                    dom.html(container, '');
                }
            };
        }
    };
    /* =========================================================
     * QUERY + SYNC
     * ========================================================= */
    const __queryCache = new Map();

    const query = {
        fetch: async (key, fn) => {
            if (__queryCache.has(key)) return __queryCache.get(key);
            const data = await fn();
            __queryCache.set(key, data);
            return data;
        },
        fetchFresh: async (key, fn) => {
            const data = await fn();
            __queryCache.set(key, data);
            return data;
        },
        invalidate: (key) => __queryCache.delete(key),
        clear: () => __queryCache.clear()
    };

    const sync = {
        create: ({ key, fetcher, interval = 30000, onUpdate = null, onError = null }) => {
            let data = null;
            let timer = null;
            let running = false;

            const load = async () => {
                if (running) return data;
                running = true;

                try {
                    const fresh = await fetcher();
                    data = fresh;

                    if (key) {
                        query.invalidate(key);
                        __queryCache.set(key, fresh);
                    }

                    if (typeof onUpdate === 'function') {
                        await onUpdate(fresh);
                    }

                    return fresh;
                } catch (err) {
                    if (typeof onError === 'function') {
                        await onError(err);
                    } else {
                        console.error('[NX sync error]', err);
                    }
                    return data;
                } finally {
                    running = false;
                }
            };

            const start = () => {
                if (timer) return;
                load();
                timer = setInterval(load, interval);
            };

            const stop = () => {
                if (timer) clearInterval(timer);
                timer = null;
            };

            start();

            return {
                get: () => data,
                refresh: load,
                stop,
                start
            };
        }
    };
    /* =========================================================
     * ACTIONS
     * ========================================================= */
    const actions = {
        run: async ({
                        confirm = null,
                        loading = null,
                        task,
                        success = '',
                        onSuccess = null,
                        onError = null
                    }) => {
            if (confirm) {
                const res = await ui.confirm(confirm);
                if (!res.isConfirmed) return { ok: false, cancelled: true };
            }
            if (loading) ui.loading(loading);
            try {
                const result = await task();
                if (loading) ui.closeLoading();
                if (success) ui.toast('success', success);
                if (typeof onSuccess === 'function') await onSuccess(result);
                return { ok: true, result };
            } catch (e) {
                if (loading) ui.closeLoading();
                if (typeof onError === 'function') {
                    await onError(e);
                } else {
                    ui.swal({ icon: 'error', text: e.message });
                }
                return { ok: false, error: e };
            }
        }
    };
    /* =========================================================
     * JOBS
     * ========================================================= */
    const jobs = {
        run: async ({
                        title = 'Processing...',
                        task,
                        success = 'Completed'
                    }) => {
            ui.loading(title);

            try {
                const result = await task();
                ui.closeLoading();
                if (success) ui.toast('success', success);
                return result;
            } catch (err) {
                ui.closeLoading();
                ui.swal({
                    icon: 'error',
                    title: 'Operation Failed',
                    text: err.message
                });
                throw err;
            }
        }
    };
    /* =========================================================
     * STORE
     * ========================================================= */
    const store = {
        create: ({ key = null, state = {} } = {}) => {
            let data = util.clone(state);
            const listeners = new Set();

            if (key) {
                const saved = storage.get(key);
                if (saved) data = { ...data, ...saved };
            }

            const notify = () => {
                const snapshot = util.clone(data);

                listeners.forEach((fn) => {
                    try {
                        fn(snapshot);
                    } catch (err) {
                        console.error('[NX store listener error]', err);
                    }
                });

                if (key) storage.set(key, data);
            };

            return {
                get: (k) => k ? data[k] : util.clone(data),

                set: (k, v) => {
                    data[k] = v;
                    notify();
                },

                patch: (obj = {}) => {
                    data = { ...data, ...obj };
                    notify();
                },

                reset: (next = state) => {
                    data = util.clone(next);
                    notify();
                },
                subscribe: (fn, fireImmediately = false) => {
                    if (typeof fn !== 'function') return () => {};

                    listeners.add(fn);

                    if (fireImmediately) {
                        try {
                            fn(util.clone(data));
                        } catch (err) {
                            console.error('[NX store listener error]', err);
                        }
                    }

                    return () => listeners.delete(fn);
                }
            };
        }
    };
    /* =========================================================
     * PAGE
     * ========================================================= */
    const page = {
        create: (cfg = {}) => {
            const appStore = store.create({
                key: cfg.storageKey || null,
                state: cfg.state || {}
            });
            const ctx = {
                store: appStore,
                get state() { return appStore.get(); },
                set: appStore.set,
                patch: appStore.patch,
                reset: appStore.reset
            };
            appStore.subscribe(() => cfg.render?.(ctx));
            const start = async () => {
                try {
                    await lifecycle.run('page:beforeInit', ctx);
                    await cfg.init?.(ctx);
                    await cfg.events?.(ctx);
                    await lifecycle.run('page:beforeLoad', ctx);
                    await cfg.load?.(ctx);
                    await lifecycle.run('page:afterLoad', ctx);
                    cfg.render?.(ctx);
                    await lifecycle.run('page:afterRender', ctx);
                } catch (err) {
                    ui.swal({
                        icon: 'error',
                        title: 'Page Error',
                        text: err?.message || 'Failed to initialize page.'
                    });
                }
            };

            return { start, store: appStore };
        }
    };
    /* =========================================================
     * RESOURCE
     * ========================================================= */
    const resource = (base) => ({
        list: () => api.get(base),
        get: (id) => api.get(`${base}/${id}`),
        create: (d) => api.post(base, d),
        update: (id, d) => api.put(`${base}/${id}`, d),
        delete: (id, d = null) => api.delete(`${base}/${id}`, d)
    });
    /* =========================================================
     * POLLER
     * ========================================================= */
    const poller = {
        create: ({
                     interval = 30000,
                     task,
                     onSuccess = null,
                     onError = null,
                     autoStart = true,
                     pauseWhenHidden = true
                 }) => {
            let timer = null;
            let running = false;
            const run = async () => {
                if (running) return;
                if (pauseWhenHidden && document.hidden) return;

                running = true;
                try {
                    const result = await task();
                    if (typeof onSuccess === 'function') await onSuccess(result);
                } catch (err) {
                    if (typeof onError === 'function') onError(err);
                } finally {
                    running = false;
                }
            };
            const start = () => {
                if (timer) return;
                run();
                timer = setInterval(run, interval);
            };
            const stop = () => {
                if (timer) clearInterval(timer);
                timer = null;
            };
            const onVisibility = () => {
                if (!pauseWhenHidden) return;
                if (!document.hidden) run();
            };
            document.addEventListener('visibilitychange', onVisibility);
            if (autoStart) start();
            return {
                start,
                stop,
                destroy: () => {
                    stop();
                    document.removeEventListener('visibilitychange', onVisibility);
                }
            };
        }
    };
    /* =========================================================
     * BUS
     * ========================================================= */
    const bus = (() => {
        const events = new Map();

        return {
            on: (name, handler) => {
                if (!events.has(name)) events.set(name, new Set());
                events.get(name).add(handler);
                return () => events.get(name)?.delete(handler);
            },
            emit: (name, payload = null) => {
                events.get(name)?.forEach((fn) => fn(payload));
            },
            off: (name, handler) => {
                events.get(name)?.delete(handler);
            }
        };
    })();
    /* =========================================================
     * COMPONENTS (legacy string registry)
     * ========================================================= */
    const components = (() => {
        const registry = new Map();

        return {
            define: (name, renderer) => {
                registry.set(name, renderer);
            },
            render: (name, props = {}) => {
                const fn = registry.get(name);
                if (!fn) return '';
                return fn(props);
            }
        };
    })();
    /* =========================================================
     * BIND
     * ========================================================= */
    const bind = {
        text: (el, getter, fallback = '') => {
            if (!el || typeof getter !== 'function') return () => {};
            const update = () => dom.text(el, getter() ?? fallback);
            update();
            return update;
        },
        html: (el, getter, fallback = '') => {
            if (!el || typeof getter !== 'function') return () => {};
            const update = () => dom.html(el, getter() ?? fallback);
            update();
            return update;
        },
        value: (el, getter, fallback = '') => {
            if (!el || typeof getter !== 'function') return () => {};
            const update = () => dom.val(el, getter() ?? fallback);
            update();
            return update;
        }
    };
    /* =========================================================
 * STATE
 * ========================================================= */
    const state = (() => {
        const stores = new Map();

        const createStore = (initial = {}) => {
            let data = util.clone(initial);
            const listeners = new Set();

            const notify = () => {
                const snapshot = util.clone(data);
                listeners.forEach((fn) => {
                    try {
                        fn(snapshot);
                    } catch (err) {
                        console.error('[NX state listener error]', err);
                    }
                });
            };

            return {
                get: (key) => key ? data[key] : util.clone(data),
                set: (key, value) => {
                    data[key] = value;
                    notify();
                },
                patch: (obj = {}) => {
                    data = { ...data, ...obj };
                    notify();
                },
                replace: (next = {}) => {
                    data = util.clone(next);
                    notify();
                },
                reset: (next = initial) => {
                    data = util.clone(next);
                    notify();
                },
                subscribe: (fn, fireImmediately = false) => {
                    if (typeof fn !== 'function') return () => {};
                    listeners.add(fn);
                    if (fireImmediately) {
                        try {
                            fn(util.clone(data));
                        } catch (err) {
                            console.error('[NX state listener error]', err);
                        }
                    }
                    return () => listeners.delete(fn);
                }
            };
        };

        return {
            create: (key, initial = {}) => {
                if (!key) return createStore(initial);
                if (!stores.has(key)) {
                    stores.set(key, createStore(initial));
                }
                return stores.get(key);
            },
            get: (key) => stores.get(key) || null,
            has: (key) => stores.has(key),
            destroy: (key) => stores.delete(key),
            clear: () => stores.clear()
        };
    })();
    /* =========================================================
 * MODULE
 * ========================================================= */
    const module = (() => {
        const registry = new Map();
        const instances = new Map();

        return {
            define: (name, definition) => {
                if (!name || typeof definition !== 'function') {
                    throw new Error('NX.module.define(name, definition) requires a valid name and function.');
                }
                registry.set(name, definition);
                return definition;
            },

            has: (name) => registry.has(name),

            get: (name) => registry.get(name) || null,

            run: async (name, ctx = {}) => {
                const def = registry.get(name);
                if (!def) {
                    throw new Error(`NX module "${name}" is not defined.`);
                }

                const result = await def(ctx);
                instances.set(name, result ?? true);
                return result;
            },

            instance: (name) => instances.get(name) || null,

            destroy: async (name) => {
                const instance = instances.get(name);
                if (instance && typeof instance.destroy === 'function') {
                    await instance.destroy();
                }
                instances.delete(name);
            },

            destroyAll: async () => {
                for (const [name, instance] of instances.entries()) {
                    if (instance && typeof instance.destroy === 'function') {
                        await instance.destroy();
                    }
                    instances.delete(name);
                }
            }
        };
    })();
    /* =========================================================
 * PLUGIN
 * ========================================================= */
    const plugin = (() => {
        const installed = new Map();

        return {
            use: async (name, installer, options = {}) => {
                if (typeof name === 'function') {
                    options = installer || {};
                    installer = name;
                    name = installer.name || util.uid('plugin');
                }

                if (typeof installer !== 'function') {
                    throw new Error('NX.plugin.use() requires a plugin installer function.');
                }

                if (installed.has(name)) {
                    return installed.get(name);
                }

                try {
                    const result = await installer({
                        NX: publicAPI,
                        options
                    });

                    installed.set(name, result ?? true);
                    return result ?? true;
                } catch (err) {
                    console.error(`[NX plugin install error] ${name}`, err);
                    throw err;
                }
            },

            has: (name) => installed.has(name),

            get: (name) => installed.get(name) || null
        };
    })();
    /* =========================================================
 * COMPONENT
 * ========================================================= */
    const component = (() => {
        const registry = new Map();

        return {
            define: (name, definition) => {
                if (!name) throw new Error('NX.component.define() requires a component name.');
                if (
                    typeof definition !== 'function' &&
                    (!definition || typeof definition.render !== 'function')
                ) {
                    throw new Error('NX.component.define() requires a render function or object with render().');
                }

                registry.set(name, definition);
                return definition;
            },

            has: (name) => registry.has(name),

            get: (name) => registry.get(name) || null,

            remove: (name) => registry.delete(name),

            clear: () => registry.clear()
        };
    })();
    /* =========================================================
 * RENDER COMPONENT
 * ========================================================= */
    const renderComponent = (name, props = {}) => {
        const def = component.get(name);
        if (!def) {
            console.warn(`NX component "${name}" not found.`);
            return '';
        }

        try {
            if (typeof def === 'function') {
                return def(props) ?? '';
            }

            if (typeof def.render === 'function') {
                return def.render(props) ?? '';
            }

            return '';
        } catch (err) {
            console.error(`[NX component render error] ${name}`, err);
            return '';
        }
    };
    /* =========================================================
   * MOUNT
   * ========================================================= */
    const mount = (target, name, props = {}) => {
        const el = typeof target === 'string' ? dom.$(target) : target;
        if (!el) {
            console.warn(`NX.mount target not found for component "${name}".`);
            return null;
        }

        const def = component.get(name);
        if (!def) {
            console.warn(`NX component "${name}" not found.`);
            return null;
        }

        let mounted = false;
        let currentProps = util.clone(props);

        const ctx = {
            el,
            name,
            get props() {
                return currentProps;
            },
            setProps(next = {}) {
                currentProps = { ...currentProps, ...next };
                mountApi.render();
            },
            destroy() {
                if (mounted && def && typeof def.unmounted === 'function') {
                    try {
                        def.unmounted(ctx);
                    } catch (err) {
                        console.error(`[NX component unmounted error] ${name}`, err);
                    }
                }
                mounted = false;
            }
        };

        const mountApi = {
            el,
            ctx,
            render(nextProps = null) {
                if (nextProps && typeof nextProps === 'object') {
                    currentProps = { ...currentProps, ...nextProps };
                }

                const html = renderComponent(name, currentProps);
                dom.html(el, html);

                if (!mounted && def && typeof def.mounted === 'function') {
                    try {
                        def.mounted(ctx);
                    } catch (err) {
                        console.error(`[NX component mounted error] ${name}`, err);
                    }
                } else if (mounted && def && typeof def.updated === 'function') {
                    try {
                        def.updated(ctx);
                    } catch (err) {
                        console.error(`[NX component updated error] ${name}`, err);
                    }
                }

                mounted = true;
                tooltip.refresh(el);
                return mountApi;
            },
            update(nextProps = {}) {
                return mountApi.render(nextProps);
            },
            destroy() {
                ctx.destroy();
                return mountApi;
            }
        };

        return mountApi.render();
    };
    /* =========================================================
 * BIND MODEL
 * ========================================================= */
    const bindModel = (target, storeRef, key, {
        event = 'input',
        valueProp = 'value',
        fromDom = (el) => el?.[valueProp],
        toDom = (el, value) => {
            if (!el) return;
            if (el[valueProp] !== value) {
                el[valueProp] = value ?? '';
            }
        }
    } = {}) => {
        const el = typeof target === 'string' ? dom.$(target) : target;
        if (!el || !storeRef || typeof storeRef.get !== 'function' || typeof storeRef.set !== 'function') {
            return {
                destroy: () => {}
            };
        }

        const syncFromStore = (snapshot) => {
            toDom(el, snapshot?.[key]);
        };

        const handler = () => {
            storeRef.set(key, fromDom(el));
        };

        toDom(el, storeRef.get(key));
        el.addEventListener(event, handler);
        const unsubscribe = storeRef.subscribe(syncFromStore);

        return {
            destroy: () => {
                el.removeEventListener(event, handler);
                unsubscribe?.();
            }
        };
    };
    /* =========================================================
 * SIDEBAR COLLAPSE
 * ========================================================= */
    const sidebar = (() => {
        const storageKey = 'nexusbox.sidebar.collapsed';
        const mobileMedia = window.matchMedia('(max-width: 767.98px)');
        let tooltipEl = null;

        const isMobile = () => mobileMedia.matches;

        const updateControl = ({ collapsed = false, mobileOpen = false } = {}) => {
            const btn = document.getElementById('sidebarToggleBtn');
            const icon = btn?.querySelector('i');

            if (btn) {
                btn.setAttribute('aria-expanded', isMobile() ? String(mobileOpen) : String(!collapsed));
                btn.setAttribute(
                    'title',
                    isMobile()
                        ? (mobileOpen ? 'Close navigation' : 'Open navigation')
                        : (collapsed ? 'Expand sidebar' : 'Collapse sidebar')
                );
            }

            if (icon) {
                icon.classList.toggle('bi-x-lg', isMobile() && mobileOpen);
                icon.classList.toggle('bi-list', isMobile() ? !mobileOpen : !collapsed);
                icon.classList.toggle('bi-layout-sidebar-inset', !isMobile() && collapsed);
            }
        };

        const apply = (collapsed, initial = false) => {
            // The preload class prevents a first-paint animation. It must not
            // be reintroduced during a user toggle, otherwise collapsing the
            // sidebar skips the transition entirely.
            if (initial) {
                document.documentElement.classList.toggle('sidebar-collapsed-preload', Boolean(collapsed));
            } else {
                document.documentElement.classList.remove('sidebar-collapsed-preload');
            }
            document.body.classList.toggle('sidebar-collapsed', Boolean(collapsed));
            // Reconcile section visibility after the shell toggle. Section
            // state may have been initialized while expanded, so simply
            // changing the body class is not enough to restore icon items.
            document.querySelectorAll('.sidebar .menu-item[data-sidebar-section-item]').forEach((item) => {
                const sectionId = item.dataset.sidebarSectionItem;
                const sectionToggle = document.querySelector(`[data-sidebar-section="${sectionId}"]`);
                const sectionExpanded = sectionToggle?.getAttribute('aria-expanded') === 'true';
                item.hidden = false;
                item.classList.toggle('is-collapsed-item', !collapsed && !sectionExpanded);
            });
            updateControl({ collapsed: Boolean(collapsed) });
        };

        const setMobileOpen = (open) => {
            const next = Boolean(open) && isMobile();
            const scrim = document.getElementById('sidebarScrim');
            const primarySidebar = document.getElementById('primarySidebar');

            if (isMobile()) {
                document.documentElement.classList.remove('sidebar-collapsed-preload');
                // A closed mobile drawer is the compact icon rail. Keep the
                // same collapsed state used by desktop so section accordion
                // state cannot hide destinations from the closed rail.
                apply(!next);
            }

            document.body.classList.toggle('sidebar-mobile-open', next);
            scrim?.setAttribute('aria-hidden', String(!next));
            primarySidebar?.setAttribute('aria-hidden', String(isMobile() && !next));
            primarySidebar?.toggleAttribute('inert', isMobile() && !next);
            updateControl({
                collapsed: document.body.classList.contains('sidebar-collapsed'),
                mobileOpen: next
            });
        };

        const ensureTooltip = () => {
            tooltipEl = document.getElementById('nxSidebarTooltip');

            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.id = 'nxSidebarTooltip';
                document.body.appendChild(tooltipEl);
            }

            return tooltipEl;
        };

        const hideTooltip = () => {
            const tooltip = ensureTooltip();
            tooltip.classList.remove('show');
        };

        const showTooltip = (link) => {
            if (isMobile()) return;
            if (!document.body.classList.contains('sidebar-collapsed')) return;

            const label =
                link.dataset.label ||
                link.querySelector('span')?.textContent?.trim() ||
                link.getAttribute('title') ||
                '';

            if (!label) return;

            const tooltip = ensureTooltip();
            const rect = link.getBoundingClientRect();

            tooltip.textContent = label;
            tooltip.style.left = `${rect.right + 10}px`;
            tooltip.style.top = `${rect.top + rect.height / 2}px`;
            tooltip.classList.add('show');
        };

        const initLabels = () => {
            document.querySelectorAll('.sidebar .menu-item > a').forEach((link) => {
                if (link.dataset.label) return;

                const label =
                    link.querySelector('span')?.textContent?.trim() ||
                    link.getAttribute('title') ||
                    '';

                if (label) {
                    link.dataset.label = label;
                    link.setAttribute('aria-label', label);
                }
            });

            // Legacy module views contain a few unlabeled filter/select and
            // switch controls. Give controls a stable accessible name from
            // their associated label or nearby field text without changing
            // the visual design.
            document.querySelectorAll('#nxApplication select, #nxApplication input[type="checkbox"], #nxApplication input[role="switch"], #nxApplication [role="switch"], #nxApplication [role="combobox"]').forEach((control) => {
                if (control.getAttribute('aria-label') || control.getAttribute('aria-labelledby')) return;
                const id = control.id;
                const label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
                const nearby = label?.textContent?.trim()
                    || control.closest('.form-check, .input-group, .field, .mb-3')?.querySelector('label, .form-label')?.textContent?.trim()
                    || (control.tagName === 'SELECT' ? 'Filter' : 'Setting');
                if (nearby) control.setAttribute('aria-label', nearby.replace(/\s+/g, ' ').trim());
            });
        };

        const sectionStorageKey = 'nexusbox.sidebar.sections';

        const setSectionExpanded = (sectionId, expanded) => {
            const toggle = document.querySelector(`[data-sidebar-section="${sectionId}"]`);
            if (!toggle) return;

            toggle.setAttribute('aria-expanded', String(Boolean(expanded)));
            toggle.closest('.menu-section')?.classList.toggle('is-collapsed', !expanded);
            toggle.querySelector('i')?.classList.toggle('bi-chevron-right', !expanded);
            toggle.querySelector('i')?.classList.toggle('bi-chevron-down', Boolean(expanded));

            document.querySelectorAll(`[data-sidebar-section-item="${sectionId}"]`).forEach((item) => {
                // Section accordions control the full-width navigation only.
                // In the compact rail every authorized destination remains
                // visible as an icon, even when its section is closed.
                const compactRail = document.body.classList.contains('sidebar-collapsed')
                    || document.documentElement.classList.contains('sidebar-collapsed-preload');
                item.hidden = false;
                item.classList.toggle('is-collapsed-item', !expanded && !compactRail);
            });
        };

        const initSections = () => {
            const savedSections = storage.get(sectionStorageKey, {});
            const toggles = Array.from(document.querySelectorAll('.sidebar .menu-section-toggle'));
            const activeToggle = toggles.find((toggle) => {
                const sectionId = toggle.dataset.sidebarSection;
                return sectionId && document.querySelector(`[data-sidebar-section-item="${sectionId}"].active`);
            });
            const operationsId = 'sidebar-group-operations';
            const networkId = 'sidebar-group-network';
            const hasStoredNetworkState = Object.prototype.hasOwnProperty.call(savedSections, networkId);
            const usesDefaultSectionState = !Object.keys(savedSections).length || !hasStoredNetworkState;
            const savedOpenIds = toggles
                .filter((toggle) => savedSections[toggle.dataset.sidebarSection] === true)
                .map((toggle) => toggle.dataset.sidebarSection);
            const defaultOpenIds = [operationsId, networkId]
                .filter((sectionId) => toggles.some((toggle) => toggle.dataset.sidebarSection === sectionId));
            const openSectionIds = new Set([
                ...(usesDefaultSectionState ? defaultOpenIds : savedOpenIds),
                ...(usesDefaultSectionState ? [] : [activeToggle?.dataset.sidebarSection]),
            ].filter(Boolean).slice(0, 2));

            if (!openSectionIds.size && toggles[0]?.dataset.sidebarSection) {
                openSectionIds.add(toggles[0].dataset.sidebarSection);
            }

            toggles.forEach((toggle) => {
                const sectionId = toggle.dataset.sidebarSection;
                if (!sectionId) return;

                setSectionExpanded(sectionId, openSectionIds.has(sectionId));

                if (toggle.dataset.nxSectionBound === '1') return;
                toggle.dataset.nxSectionBound = '1';
                toggle.addEventListener('click', () => {
                    const next = toggle.getAttribute('aria-expanded') !== 'true';
                    if (next) {
                        openSectionIds.add(sectionId);
                        while (openSectionIds.size > 2) {
                            openSectionIds.delete(openSectionIds.values().next().value);
                        }
                    } else {
                        openSectionIds.delete(sectionId);
                    }

                    const nextState = {};
                    toggles.forEach((candidate) => {
                        const candidateId = candidate.dataset.sidebarSection;
                        if (!candidateId) return;
                        const isOpen = openSectionIds.has(candidateId);
                        setSectionExpanded(candidateId, isOpen);
                        nextState[candidateId] = isOpen;
                    });
                    storage.set(sectionStorageKey, nextState);
                });
            });
        };

        const bindTooltipEvents = () => {
            if (document.body.dataset.nxSidebarTooltipBound === '1') return;
            document.body.dataset.nxSidebarTooltipBound = '1';

            document.addEventListener('mouseover', (e) => {
                const link = e.target.closest('.sidebar .menu-item > a');
                if (!link) return;
                showTooltip(link);
            });

            document.addEventListener('mousemove', (e) => {
                const link = e.target.closest('.sidebar .menu-item > a');
                if (!link) return;
                showTooltip(link);
            });

            document.addEventListener('mouseout', (e) => {
                const link = e.target.closest('.sidebar .menu-item > a');
                if (!link) return;
                hideTooltip();
            });
        };

        const init = () => {
            initLabels();
            // Vue modules mount after this shared script. Re-apply accessible
            // names when their controls are added to the DOM.
            const labelObserver = new MutationObserver(() => initLabels());
            labelObserver.observe(document.body, { childList: true, subtree: true });
            initSections();
            bindTooltipEvents();

            document.querySelectorAll('form[action="/logout"]').forEach((form) => {
                if (form.dataset.nxStorageClearBound === '1') return;
                form.dataset.nxStorageClearBound = '1';
                form.addEventListener('submit', () => {
                    try {
                        localStorage.clear();
                    } catch {
                        // Storage may be unavailable in restricted browser modes.
                    }
                });
            });

            const saved = storage.get(storageKey, false);
            apply(Boolean(saved), true);
            setMobileOpen(false);

            const btn = document.getElementById('sidebarToggleBtn');
            const scrim = document.getElementById('sidebarScrim');

            if (!btn) return;

            if (btn.dataset.nxSidebarBound === '1') return;

            btn.dataset.nxSidebarBound = '1';

            btn.addEventListener('click', () => {
                if (isMobile()) {
                    setMobileOpen(!document.body.classList.contains('sidebar-mobile-open'));
                    hideTooltip();
                    return;
                }

                const next = !document.body.classList.contains('sidebar-collapsed');

                storage.set(storageKey, next);
                apply(next);
                hideTooltip();
            });

            scrim?.addEventListener('click', () => setMobileOpen(false));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && document.body.classList.contains('sidebar-mobile-open')) {
                    setMobileOpen(false);
                    btn.focus();
                }
            });

            document.querySelector('.sidebar')?.addEventListener('click', (event) => {
                if (isMobile() && event.target.closest('a[href]')) {
                    setMobileOpen(false);
                }
            });

            mobileMedia.addEventListener?.('change', () => {
                setMobileOpen(false);
                apply(Boolean(storage.get(storageKey, false)));
                if (isMobile()) setMobileOpen(false);
            });
        };

        return {
            init,
            apply,
            closeMobile: () => setMobileOpen(false),
            isCollapsed: () => document.body.classList.contains('sidebar-collapsed')
        };
    })();

    /* =========================================================
* AJAX MODULE NAVIGATION
* ========================================================= */
    const navigation = (() => {
        let busy = false;
        let currentController = null;

        const cache = new Map();
        const scrollPositions = new Map();

        const cacheMax = 20;

        const getKey = (url) => {
            const u = new URL(url, window.location.origin);
            return u.pathname + u.search;
        };

        const clearCache = () => {
            cache.clear();
        };

        const invalidate = (url) => {
            cache.delete(getKey(url));
        };

        const cancelCurrentRequest = () => {
            if (currentController) {
                currentController.abort();
                currentController = null;
            }
        };

        const isAjaxableLink = (a) => {
            if (!a || a.target || a.hasAttribute('download')) return false;

            const href = a.getAttribute('href') || '';
            if (!href || href.startsWith('#')) return false;
            if (href.startsWith('mailto:') || href.startsWith('tel:')) return false;

            const url = new URL(a.href, window.location.origin);

            if (url.origin !== window.location.origin) return false;
            if (url.pathname === '/logout') return false;
            if (url.pathname.startsWith('/api/')) return false;
            if (url.pathname.startsWith('/module-assets/')) return false;

            return true;
        };

        const startLoading = () => {
            const bar = document.getElementById('nxTopLoader');

            document.body.classList.add('nx-page-transitioning');

            if (!bar) return;

            bar.style.display = 'block';
            bar.style.opacity = '1';
            bar.style.width = '0';
            bar.style.height = '4px';
            bar.style.background = '#2563eb';
            bar.style.position = 'fixed';
            bar.style.top = '0';
            bar.style.left = '0';
            bar.style.zIndex = '2147483647';
            bar.style.transition = 'none';

            requestAnimationFrame(() => {
                bar.style.transition = 'width 350ms ease, opacity 200ms ease';
                bar.style.width = '70%';
            });
        };

        const finishLoading = () => {
            const bar = document.getElementById('nxTopLoader');

            if (bar) {
                bar.style.width = '100%';
                bar.style.opacity = '1';

                setTimeout(() => {
                    bar.style.opacity = '0';

                    setTimeout(() => {
                        bar.style.width = '0';
                        bar.style.display = 'none';
                    }, 250);
                }, 350);
            }

            setTimeout(() => {
                document.body.classList.remove('nx-page-transitioning');
            }, 350);
        };

        const setActiveSidebar = (path) => {
            document.querySelectorAll('.sidebar a[href]').forEach((link) => {
                const item = link.closest('.menu-item, li, .nav-item');
                if (!item) return;

                const linkPath = new URL(link.href, window.location.origin).pathname;

                const active =
                    path === linkPath ||
                    (linkPath !== '/' && path.startsWith(linkPath + '/'));

                item.classList.toggle('active', active);
            });
        };

        const executeScripts = async (container) => {
            const scripts = [...container.querySelectorAll('script')];

            for (const oldScript of scripts) {
                const newScript = document.createElement('script');

                [...oldScript.attributes].forEach((attr) => {
                    newScript.setAttribute(attr.name, attr.value);
                });

                if (oldScript.src) {
                    newScript.src = oldScript.src;
                } else {
                    newScript.textContent = oldScript.textContent;
                }

                oldScript.replaceWith(newScript);

                if (newScript.src) {
                    await new Promise((resolve) => {
                        newScript.onload = resolve;
                        newScript.onerror = resolve;
                    });
                }
            }
        };

        const rememberScroll = () => {
            scrollPositions.set(getKey(window.location.href), window.scrollY || 0);
        };

        const restoreScroll = (url, push) => {
            const key = getKey(url);

            if (!push && scrollPositions.has(key)) {
                window.scrollTo(0, scrollPositions.get(key));
                return;
            }

            window.scrollTo({ top: 0, behavior: 'instant' });
        };

        const saveCache = (key, data) => {
            if (cache.has(key)) cache.delete(key);

            cache.set(key, data);

            while (cache.size > cacheMax) {
                const oldestKey = cache.keys().next().value;
                cache.delete(oldestKey);
            }
        };

        const fetchPage = async (url, useCache = true) => {
            const key = getKey(url);

            if (useCache && cache.has(key)) {
                return cache.get(key);
            }

            cancelCurrentRequest();

            currentController = new AbortController();

            try {
                const res = await fetch(url, {
                    method: 'GET',
                    signal: currentController.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-NexusBox-Ajax': '1'
                    }
                });

                if (res.status === 401) redirectExpiredSession();

                const json = await res.json();

                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Page failed to load.');
                }

                saveCache(key, json);

                return json;
            } finally {
                currentController = null;
            }
        };

        const load = async (url, push = true, options = {}) => {
            const target = document.querySelector('[data-nx-main-content]');

            if (!target) {
                window.location.href = url;
                return;
            }

            if (busy) {
                cancelCurrentRequest();
            }

            busy = true;
            rememberScroll();
            startLoading();

            try {
                await module.destroyAll?.();
                await lifecycle.run?.('page:before-load', { url });

                const startedAt = Date.now();

                const json = await fetchPage(url, options.cache !== false);

                const elapsed = Date.now() - startedAt;
                const minimumVisibleMs = 450;

                if (elapsed < minimumVisibleMs) {
                    await new Promise(resolve =>
                        setTimeout(resolve, minimumVisibleMs - elapsed)
                    );
                }

                target.innerHTML = json.content || '';

                // The shell stays mounted during AJAX navigation, so refresh
                // the shared breadcrumb from the server-resolved route.
                if (json.breadcrumb) {
                    const breadcrumb = document.querySelector('.workspace-breadcrumb');
                    const group = breadcrumb?.querySelector('.workspace-breadcrumb-group');
                    const current = breadcrumb?.querySelector('.workspace-breadcrumb-current');
                    if (group && json.breadcrumb.group) group.textContent = json.breadcrumb.group;
                    if (current && json.breadcrumb.current) current.textContent = json.breadcrumb.current;
                }

                if (json.title) {
                    document.title = json.title;
                }

                if (push) {
                    history.pushState({ nxAjax: true, url }, '', url);
                }

                const path = new URL(url, window.location.origin).pathname;

                setActiveSidebar(path);

                await executeScripts(target);

                document.dispatchEvent(new CustomEvent('nx:page-load', {
                    detail: { url, content: target }
                }));

                document.dispatchEvent(new Event('DOMContentLoaded'));

                await lifecycle.run?.('page:after-load', {
                    url,
                    content: target
                });

                restoreScroll(url, push);
            } catch (err) {
                if (err.name === 'AbortError') {
                    return;
                }

                console.error('[NX navigation]', err);
                window.location.href = url;
            } finally {
                busy = false;
                finishLoading();
            }
        };

        const prefetch = async (url) => {
            const key = getKey(url);

            if (cache.has(key)) return;
            if (currentController) return;

            try {
                await fetchPage(url, true);
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.warn('[NX prefetch skipped]', err);
                }
            }
        };

        const init = () => {
            if (document.body.dataset.nxAjaxNavigationBound === '1') return;

            document.body.dataset.nxAjaxNavigationBound = '1';

            document.addEventListener('click', (e) => {
                const a = e.target.closest('a[href]');
                if (!isAjaxableLink(a)) return;

                e.preventDefault();

                load(a.href, true);
            });

            document.addEventListener('mouseover', (e) => {
                const a = e.target.closest('a[href]');
                if (!isAjaxableLink(a)) return;

                prefetch(a.href);
            });

            window.addEventListener('popstate', () => {
                load(window.location.href, false);
            });

            history.replaceState(
                { nxAjax: true, url: window.location.href },
                '',
                window.location.href
            );
        };

        return {
            init,
            load,
            prefetch,
            clearCache,
            invalidate,
            cancelCurrentRequest
        };
    })();
    const theme = (() => {
        const storageKey = 'nexusbox.theme';

        const normalize = (value) => {
            return value === 'dark' ? 'dark' : 'light';
        };

        const current = () => {
            return normalize(document.documentElement.getAttribute('data-theme') || storage.get(storageKey, 'light'));
        };

        const apply = (value, animate = true) => {
            const mode = normalize(value);

            const root = document.documentElement;
            if (animate) {
                // Keep the class on the document for the full interpolation
                // window so interactive theme changes remain smooth.
                root.classList.add('nx-theme-transition');
                window.clearTimeout(root.__nxThemeTransitionTimer);
                root.__nxThemeTransitionTimer = window.setTimeout(() => {
                    root.classList.remove('nx-theme-transition');
                }, 1200);
            } else {
                // Initial page hydration must be instantaneous; otherwise
                // borders are painted after the rest of the page on reload.
                root.classList.remove('nx-theme-transition');
                window.clearTimeout(root.__nxThemeTransitionTimer);
            }
            root.setAttribute('data-theme', mode);
            storage.set(storageKey, mode);

            const btn = document.getElementById('themeToggleBtn');
            const icon = btn?.querySelector('i');

            if (btn) {
                btn.setAttribute('aria-label', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
                btn.setAttribute('title', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            }

            if (icon) {
                icon.classList.toggle('bi-sun', mode === 'dark');
                icon.classList.toggle('bi-moon-stars', mode !== 'dark');
            }

            document.dispatchEvent(new CustomEvent('nx:theme-change', {
                detail: { theme: mode }
            }));

            return mode;
        };

        const toggle = () => {
            return apply(current() === 'dark' ? 'light' : 'dark');
        };

        const init = () => {
            apply(storage.get(storageKey, current()), false);

            const btn = document.getElementById('themeToggleBtn');

            if (!btn) return;

            if (btn.dataset.nxThemeBound === '1') return;

            btn.dataset.nxThemeBound = '1';

            btn.addEventListener('click', toggle);
        };

        return {
            init,
            apply,
            set: apply,
            toggle,
            current
        };
    })();
    /* =========================================================
     * PUBLIC API
     * ========================================================= */
    const publicAPI = {
        api,
        ui,
        tooltip,
        clipboard,
        storage,
        dom,
        forms,
        select,
        modal,
        maps,
        render,
        table,
        actions,
        util,
        store,
        state,
        page,
        resource,
        poller,
        bus,
        components,
        component,
        mount,
        renderComponent,
        bindModel,
        bind,
        form,
        datatable,
        query,
        jobs,
        validation,
        formBuilder,
        layout,
        dialog,
        sync,
        sidebar,
        dev,
        middleware,
        lifecycle,
        plugin,
        navigation,
        theme,
        module
    };
    document.addEventListener('DOMContentLoaded', () => {
        sidebar.init();
        navigation.init();
        theme.init();
        sessionWatchdog.init();

        const searchInput = document.getElementById('globalSearchInput');
        const suggestions = document.getElementById('globalSearchSuggestions');
        if (searchInput && suggestions) {
            let activeIndex = -1;
            let renderedMatches = [];
            const normalize = (value) => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
            const menuItems = Array.from(document.querySelectorAll('#primarySidebar .sidebar-menu a[href]'))
                .map((link) => {
                    let section = '';
                    let sibling = link.closest('li')?.previousElementSibling;
                    while (sibling) {
                        if (sibling.classList.contains('menu-section')) {
                            section = sibling.textContent.trim();
                            break;
                        }
                        sibling = sibling.previousElementSibling;
                    }
                    const label = link.querySelector('span')?.textContent.trim() || link.textContent.trim();
                    const path = new URL(link.href, window.location.origin).pathname;
                    return {
                        label,
                        section,
                        href: link.href,
                        path,
                        icon: link.querySelector('i')?.className || 'bi bi-arrow-right',
                        searchText: normalize(`${label} ${section} ${path.replaceAll('/', ' ')}`)
                    };
                })
                .filter((item, index, items) => item.label && items.findIndex((candidate) => candidate.href === item.href) === index);
            let searchRequestId = 0;
            const recordCache = new Map();

            const recordMatches = async (query) => {
                if (query.length < 2) return [];
                if (recordCache.has(query)) return recordCache.get(query);

                const encode = encodeURIComponent(query);
                const requests = [
                    api.get(`/api/v1/subscribers?search=${encode}`).then((rows) => (Array.isArray(rows) ? rows : []).slice(0, 6).map((row) => ({
                        label: row.full_name || `Subscriber ${row.account_number || row.id}`,
                        detail: ['Subscribers', row.account_number, row.ppp_username, row.email].filter(Boolean).join(' · '),
                        section: 'Subscribers',
                        href: '/subscribers',
                        icon: 'bi bi-people'
                    }))),
                    api.get('/api/v1/subscriber-plans').then((rows) => (Array.isArray(rows) ? rows : []).filter((row) => normalize(Object.values(row).join(' ')).includes(query)).slice(0, 4).map((row) => ({
                        label: row.plan_name || `Plan ${row.id}`,
                        detail: ['Plans', row.plan_type, row.speed_mbps ? `${row.speed_mbps} Mbps` : ''].filter(Boolean).join(' · '),
                        section: 'Plans',
                        href: '/subscriber-plans',
                        icon: 'bi bi-tags'
                    }))),
                    api.get('/api/v1/users').then((rows) => (Array.isArray(rows) ? rows : []).filter((row) => normalize(Object.values(row).join(' ')).includes(query)).slice(0, 4).map((row) => ({
                        label: row.name || row.username || `User ${row.id}`,
                        detail: ['Users', row.email, row.role].filter(Boolean).join(' · '),
                        section: 'Users',
                        href: '/users',
                        icon: 'bi bi-person'
                    }))),
                    api.get('/api/v1/olt-management/devices').then((rows) => (Array.isArray(rows) ? rows : []).filter((row) => normalize(Object.values(row).join(' ')).includes(query)).slice(0, 4).map((row) => ({
                        label: row.name || row.device_name || `OLT ${row.id}`,
                        detail: ['OLT Management', row.ip_address, row.vendor].filter(Boolean).join(' · '),
                        section: 'OLT Management',
                        href: '/olt-management',
                        icon: 'bi bi-hdd-network'
                    })))
                ];

                const results = (await Promise.allSettled(requests))
                    .filter((result) => result.status === 'fulfilled')
                    .flatMap((result) => result.value);
                recordCache.set(query, results);
                return results;
            };

            const closeSuggestions = () => {
                activeIndex = -1;
                renderedMatches = [];
                suggestions.hidden = true;
                suggestions.replaceChildren();
                searchInput.setAttribute('aria-expanded', 'false');
                searchInput.removeAttribute('aria-activedescendant');
            };

            const setActive = (nextIndex) => {
                const options = Array.from(suggestions.querySelectorAll('[role="option"]'));
                if (!options.length) return;
                activeIndex = (nextIndex + options.length) % options.length;
                options.forEach((option, index) => {
                    const selected = index === activeIndex;
                    option.classList.toggle('is-active', selected);
                    option.setAttribute('aria-selected', selected ? 'true' : 'false');
                });
                const active = options[activeIndex];
                searchInput.setAttribute('aria-activedescendant', active.id);
                active.scrollIntoView({ block: 'nearest' });
            };

            const addTextElement = (parent, tag, className, text) => {
                const node = document.createElement(tag);
                if (className) node.className = className;
                node.textContent = text;
                parent.appendChild(node);
                return node;
            };

            const renderSuggestions = async () => {
                const query = normalize(searchInput.value);
                if (!query) { closeSuggestions(); return; }
                const requestId = ++searchRequestId;
                const terms = query.split(' ');
                const navigationMatches = menuItems
                    .filter((item) => terms.every((term) => item.searchText.includes(term)))
                    .sort((left, right) => {
                        const leftLabel = normalize(left.label);
                        const rightLabel = normalize(right.label);
                        const leftScore = leftLabel === query ? 0 : leftLabel.startsWith(query) ? 1 : 2;
                        const rightScore = rightLabel === query ? 0 : rightLabel.startsWith(query) ? 1 : 2;
                        return leftScore - rightScore || left.label.localeCompare(right.label);
                    })
                    .slice(0, 8)
                    .map((item) => ({ ...item, type: 'navigation' }));
                const records = await recordMatches(query);
                if (requestId !== searchRequestId || normalize(searchInput.value) !== query) return;
                renderedMatches = [...navigationMatches, ...records.map((item) => ({ ...item, type: 'record' }))].slice(0, 12);

                suggestions.replaceChildren();
                if (renderedMatches.length) {
                    const heading = document.createElement('div');
                    heading.className = 'nx-search-section';
                    addTextElement(heading, 'span', '', records.length ? 'Matches' : 'Navigation');
                    addTextElement(heading, 'span', '', `${renderedMatches.length} result${renderedMatches.length === 1 ? '' : 's'}`);
                    suggestions.appendChild(heading);

                    renderedMatches.forEach((item, index) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.id = `globalSearchOption-${index}`;
                        option.className = 'nx-search-suggestion';
                        option.dataset.index = String(index);
                        option.setAttribute('role', 'option');
                        option.setAttribute('aria-selected', 'false');
                        const icon = document.createElement('i');
                        icon.className = item.icon;
                        icon.setAttribute('aria-hidden', 'true');
                        option.appendChild(icon);
                        const copy = document.createElement('span');
                        addTextElement(copy, 'span', 'nx-search-suggestion-label', item.label);
                        if (item.detail) addTextElement(copy, 'small', 'nx-search-suggestion-section', item.detail);
                        else if (item.section) addTextElement(copy, 'small', 'nx-search-suggestion-section', item.section);
                        option.appendChild(copy);
                        const enter = document.createElement('i');
                        enter.className = 'bi bi-arrow-return-left nx-search-enter';
                        enter.setAttribute('aria-hidden', 'true');
                        option.appendChild(enter);
                        suggestions.appendChild(option);
                    });
                } else {
                    const empty = document.createElement('div');
                    empty.className = 'nx-search-empty';
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-search';
                    icon.setAttribute('aria-hidden', 'true');
                    empty.appendChild(icon);
                    addTextElement(empty, 'span', '', 'No matching record or module found');
                    suggestions.appendChild(empty);
                }
                const footer = document.createElement('div');
                footer.className = 'nx-search-footer';
                addTextElement(footer, 'span', '', '↑ ↓ Navigate · Enter Open');
                addTextElement(footer, 'span', '', 'Esc Close');
                suggestions.appendChild(footer);
                suggestions.hidden = false;
                searchInput.setAttribute('aria-expanded', 'true');
                activeIndex = -1;
            };

            searchInput.addEventListener('input', renderSuggestions);
            searchInput.addEventListener('focus', renderSuggestions);
            suggestions.addEventListener('click', (event) => {
                const option = event.target.closest('[data-index]');
                if (!option) return;
                const item = renderedMatches[Number(option.dataset.index)];
                if (!item) return;
                closeSuggestions();
                searchInput.value = '';
                navigation.load(item.href, true);
            });
            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSuggestions();
                    searchInput.blur();
                    return;
                }
                if (suggestions.hidden || !renderedMatches.length) return;
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    setActive(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    const item = renderedMatches[activeIndex < 0 ? 0 : activeIndex];
                    if (item) {
                        closeSuggestions();
                        searchInput.value = '';
                        navigation.load(item.href, true);
                    }
                }
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('.nx-header-search')) closeSuggestions();
            });
            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
        }

        const notificationToggle = document.getElementById('globalNotificationsToggle');
        const notificationPopover = document.getElementById('globalNotifications');
        const notificationList = notificationPopover?.querySelector('.nx-notification-list');
        const notificationRefresh = notificationPopover?.querySelector('.nx-notification-refresh');
<<<<<<< HEAD
        const notificationCount = document.getElementById('globalNotificationsCount');
        const notificationModal = document.getElementById('globalNotificationModal');
        const notificationModalClose = notificationModal?.querySelector('.nx-notification-modal-close');
        const notificationContext = notificationPopover?.dataset.notificationContext || 'security';
        const notificationItems = new Map();
        let notificationModalPreviousFocus = null;
        if (notificationToggle && notificationPopover && notificationList && notificationToggle.dataset.nxNotificationsBound !== '1') {
            notificationToggle.dataset.nxNotificationsBound = '1';
            let notificationsLoading = false;
            let unreadNotificationCount = 0;

            const updateNotificationCount = (count) => {
                if (!notificationCount) return;
                const total = Number(count) || 0;
                unreadNotificationCount = total;
                notificationCount.hidden = total < 1;
                notificationCount.textContent = total > 99 ? '99+' : String(total);
                notificationToggle.setAttribute('aria-label', total ? `View notifications (${total} alerts)` : 'View notifications');
            };

            const renderNotifications = (items) => {
                notificationItems.clear();
=======
        if (notificationToggle && notificationPopover && notificationList && notificationToggle.dataset.nxNotificationsBound !== '1') {
            notificationToggle.dataset.nxNotificationsBound = '1';
            let notificationsLoading = false;

            const renderNotifications = (items) => {
>>>>>>> origin/main
                notificationList.replaceChildren();
                if (!items.length) {
                    const empty = document.createElement('div');
                    empty.className = 'nx-notification-empty';
<<<<<<< HEAD
                    empty.textContent = notificationContext === 'maintenance' ? 'No maintenance updates.' : 'No security notifications.';
=======
                    empty.textContent = 'No security notifications.';
>>>>>>> origin/main
                    notificationList.appendChild(empty);
                    return;
                }

                items.forEach((item) => {
<<<<<<< HEAD
                    notificationItems.set(String(item.id), item);
                    const entry = document.createElement('div');
                    const isMaintenance = item.notification_type !== 'SECURITY';
                    entry.className = `nx-notification-item ${isMaintenance ? 'nx-notification-maintenance' : 'nx-notification-critical'}${item.is_read ? ' is-read' : ''}`;
                    entry.dataset.notificationId = String(item.id);
                    entry.tabIndex = 0;
                    entry.setAttribute('role', 'button');
                    entry.setAttribute('aria-label', `Open ${isMaintenance ? 'maintenance' : 'security'} notification`);
                    const icon = document.createElement('i');
                    icon.className = `bi ${isMaintenance ? 'bi-calendar2-event' : 'bi-shield-exclamation'}`;
                    icon.setAttribute('aria-hidden', 'true');
                    const copy = document.createElement('div');
                    const title = document.createElement('strong');
                    title.textContent = item.title || (isMaintenance ? 'Maintenance notice' : 'Critical security event');
                    const description = document.createElement('span');
                    description.textContent = item.description || (isMaintenance ? 'The system has a scheduled maintenance update.' : 'Suspicious login activity was rejected.');
                    const meta = document.createElement('small');
                    meta.textContent = isMaintenance
                        ? [item.starts_at, item.ends_at].filter(Boolean).join(' · ')
                        : [item.username, item.ip_address, item.created_at].filter(Boolean).join(' · ');
=======
                    const entry = document.createElement('div');
                    entry.className = 'nx-notification-item nx-notification-critical';
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-shield-exclamation';
                    icon.setAttribute('aria-hidden', 'true');
                    const copy = document.createElement('div');
                    const title = document.createElement('strong');
                    title.textContent = 'Critical security event';
                    const description = document.createElement('span');
                    description.textContent = item.description || 'Suspicious login activity was rejected.';
                    const meta = document.createElement('small');
                    meta.textContent = [item.username, item.ip_address, item.created_at].filter(Boolean).join(' · ');
>>>>>>> origin/main
                    copy.append(title, description, meta);
                    entry.append(icon, copy);
                    notificationList.appendChild(entry);
                });
            };

<<<<<<< HEAD
            const closeNotificationModal = () => {
                if (!notificationModal) return;
                notificationModal.hidden = true;
                document.body.classList.remove('notification-modal-open');
                notificationModalPreviousFocus?.focus?.();
                notificationModalPreviousFocus = null;
            };

            const openNotificationModal = async (item, entry) => {
                if (!notificationModal) return;
                const setText = (selector, value) => { const node = notificationModal.querySelector(selector); if (node) node.textContent = value || '—'; };
                const isMaintenance = item.notification_type !== 'SECURITY';
                setText('#globalNotificationModalContext', isMaintenance ? 'Maintenance update' : 'Security alert');
                setText('#globalNotificationModalTitle', item.title || (isMaintenance ? 'Maintenance notice' : 'Notification details'));
                setText('#globalNotificationModalDescription', item.description || (isMaintenance ? 'The system has a scheduled maintenance update.' : 'Suspicious login activity was rejected.'));
                setText('#globalNotificationModalUser', isMaintenance ? 'All subscribers' : item.username);
                setText('#globalNotificationModalIp', isMaintenance ? [item.starts_at, item.ends_at].filter(Boolean).join(' · ') : item.ip_address);
                setText('#globalNotificationModalTime', item.created_at);
                const setLabel = (selector, value) => { const node = notificationModal.querySelector(selector); if (node) node.textContent = value; };
                setLabel('#globalNotificationModalUserLabel', isMaintenance ? 'Audience' : 'User');
                setLabel('#globalNotificationModalIpLabel', isMaintenance ? 'Maintenance window' : 'IP address');
                setLabel('#globalNotificationModalTimeLabel', isMaintenance ? 'Published' : 'Time');
                notificationModalPreviousFocus = document.activeElement;
                document.body.classList.add('notification-modal-open');
                notificationModal.hidden = false;
                notificationModalClose?.focus();
                if (item.is_read || item.reading) return;
                item.reading = true;
                item.is_read = true;
                entry?.classList.add('is-read');
                updateNotificationCount(Math.max(0, unreadNotificationCount - 1));
                try {
                    await api.post(`/api/v1/notifications/${encodeURIComponent(item.id)}/read`);
                } catch (error) {
                    item.is_read = false;
                    entry?.classList.remove('is-read');
                    updateNotificationCount(unreadNotificationCount + 1);
                    dev.warn('Unable to mark notification as read', error);
                } finally {
                    item.reading = false;
                }
            };

=======
>>>>>>> origin/main
            const loadNotifications = async () => {
                if (notificationsLoading) return;
                notificationsLoading = true;
                notificationList.replaceChildren();
                const loading = document.createElement('div');
                loading.className = 'nx-notification-empty';
                loading.textContent = 'Loading notifications…';
                notificationList.appendChild(loading);
                try {
<<<<<<< HEAD
                    const response = await api.get('/api/v1/notifications');
                    const rows = Array.isArray(response) ? response : (Array.isArray(response?.items) ? response.items : []);
                    updateNotificationCount(Array.isArray(response) ? rows.filter(item => !item.is_read).length : response?.unread_count);
                    renderNotifications(rows);
=======
                    const items = await api.get('/api/v1/notifications');
                    renderNotifications(Array.isArray(items) ? items : []);
>>>>>>> origin/main
                } catch (error) {
                    notificationList.replaceChildren();
                    const failed = document.createElement('div');
                    failed.className = 'nx-notification-empty nx-notification-error';
                    failed.textContent = error.message || 'Unable to load notifications.';
                    notificationList.appendChild(failed);
                } finally {
                    notificationsLoading = false;
                }
            };

            const closeNotifications = () => {
                notificationPopover.hidden = true;
                notificationToggle.setAttribute('aria-expanded', 'false');
            };

            notificationToggle.addEventListener('click', () => {
                const opening = notificationPopover.hidden;
                notificationPopover.hidden = !opening;
                notificationToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
                if (opening) loadNotifications();
            });
<<<<<<< HEAD
            notificationList.addEventListener('click', (event) => {
                const entry = event.target.closest('.nx-notification-item');
                const item = entry ? notificationItems.get(entry.dataset.notificationId) : null;
                if (entry && item) void openNotificationModal(item, entry);
            });
            notificationList.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                const entry = event.target.closest('.nx-notification-item');
                const item = entry ? notificationItems.get(entry.dataset.notificationId) : null;
                if (entry && item) { event.preventDefault(); void openNotificationModal(item, entry); }
            });
            notificationRefresh?.addEventListener('click', loadNotifications);
            notificationModalClose?.addEventListener('click', closeNotificationModal);
            notificationModal?.addEventListener('click', (event) => { if (event.target === notificationModal) closeNotificationModal(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeNotificationModal(); });
            loadNotifications();
=======
            notificationRefresh?.addEventListener('click', loadNotifications);
>>>>>>> origin/main
            document.addEventListener('click', (event) => {
                if (!event.target.closest('#globalNotifications, #globalNotificationsToggle')) closeNotifications();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeNotifications();
            });
        }
    });
    maps.fixLeafletDefaultIcons();
    return publicAPI;
})();
