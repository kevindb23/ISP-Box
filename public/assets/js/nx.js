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
                <div class="table-responsive nx-table-wrap">
                    <table class="table align-middle mb-0">
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
            if (!current || current.key !== key) return '<span class="sort-indicator">↕</span>';
            return current.dir === 'asc'
                ? '<span class="sort-indicator">↑</span>'
                : '<span class="sort-indicator">↓</span>';
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
                <div class="card-body pt-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">
                            Page ${processed.currentPage} of ${processed.totalPages} (${processed.totalRows} record${processed.totalRows !== 1 ? 's' : ''})
                        </small>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label class="small text-muted mb-0" for="">Rows per page</label>
                            <select class="form-select form-select-sm nx-dt-rows" style="width:auto;">
                                <option value="10" ${processed.rowsPerPage === 10 ? 'selected' : ''}>10</option>
                                <option value="20" ${processed.rowsPerPage === 20 ? 'selected' : ''}>20</option>
                                <option value="50" ${processed.rowsPerPage === 50 ? 'selected' : ''}>50</option>
                                <option value="100" ${processed.rowsPerPage === 100 ? 'selected' : ''}>100</option>
                            </select>
                            <button class="btn btn-sm btn-light border nx-dt-prev" type="button" ${processed.currentPage <= 1 ? 'disabled' : ''}>Prev</button>
                            <button class="btn btn-sm btn-light border nx-dt-next" type="button" ${processed.currentPage >= processed.totalPages ? 'disabled' : ''}>Next</button>
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
        dev,
        middleware,
        lifecycle,
        plugin,
        module
    };
    maps.fixLeafletDefaultIcons();
    return publicAPI;
})();