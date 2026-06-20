document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('auditPage');
    if (!app || !window.NX) return;

    const { api, dom, util, render, datatable } = window.NX;
    const { $, html, text } = dom;
    const { escape, safeArray, formatDateTime, upper } = util;

    let table = null;
    let rows = [];

    const el = {
        content: $('#auditContentArea'),
        refresh: $('#refreshAuditBtn'),
        export: $('#exportAuditBtn'),

        search: $('#auditUserFilter'),
        module: $('#auditModuleFilter'),
        action: $('#auditActionFilter'),
        from: $('#auditDateFrom'),
        to: $('#auditDateTo'),
        apply: $('#applyAuditFilterBtn'),

        total: $('#auditTotalLogs'),
        today: $('#auditTodayLogs'),
        week: $('#auditWeekLogs'),
        month: $('#auditMonthLogs')
    };

    init();

    function init() {
        bindEvents();
        loadAuditLogs();
    }

    function bindEvents() {
        el.refresh?.addEventListener('click', loadAuditLogs);
        el.apply?.addEventListener('click', renderTable);
        el.export?.addEventListener('click', exportCsv);

        el.search?.addEventListener('input', util.debounce(renderTable, 200));

        [el.module, el.action, el.from, el.to].forEach(input => {
            input?.addEventListener('change', renderTable);
        });
    }

    async function loadAuditLogs() {
        html(el.content, render.skeletonTable(6, 7));

        try {
            const response = await api.get('/api/v1/audit');

            rows = sortLatestFirst(extractRows(response));

            populateFilters(rows);
            renderSummary(rows);
            renderTable();
        } catch (err) {
            console.error('Audit load failed:', err);
            rows = [];

            html(el.content, `
                <div class="text-center py-5 text-danger">
                    Failed to load audit logs.
                </div>
            `);
        }
    }

    function extractRows(response) {
        if (Array.isArray(response)) return safeArray(response);
        if (Array.isArray(response?.data)) return safeArray(response.data);
        if (Array.isArray(response?.rows)) return safeArray(response.rows);
        if (Array.isArray(response?.items)) return safeArray(response.items);
        if (response?.data && Array.isArray(response.data?.data)) return safeArray(response.data.data);

        return [];
    }

    function sortLatestFirst(data) {
        return safeArray(data).slice().sort((a, b) => {
            const dateA = toDate(a.created_at).getTime();
            const dateB = toDate(b.created_at).getTime();

            if (dateA !== dateB) {
                return dateB - dateA;
            }

            return Number(b.id || 0) - Number(a.id || 0);
        });
    }

    function populateFilters(data) {
        const currentModule = el.module?.value || '';
        const currentAction = el.action?.value || '';

        const modules = [...new Set(data.map(r => r.module).filter(Boolean))].sort();
        const actions = [...new Set(data.map(r => r.action).filter(Boolean))].sort();

        html(el.module, `<option value="">All Modules</option>` + modules.map(m => `
            <option value="${escape(m)}" ${String(m) === String(currentModule) ? 'selected' : ''}>${escape(m)}</option>
        `).join(''));

        html(el.action, `<option value="">All Actions</option>` + actions.map(a => `
            <option value="${escape(a)}" ${String(a) === String(currentAction) ? 'selected' : ''}>${escape(a)}</option>
        `).join(''));
    }

    function renderSummary(data) {
        const now = new Date();
        const todayKey = now.toISOString().slice(0, 10);

        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 7);

        const monthAgo = new Date(now);
        monthAgo.setMonth(now.getMonth() - 1);

        const todayCount = data.filter(r => String(r.created_at || '').slice(0, 10) === todayKey).length;
        const weekCount = data.filter(r => toDate(r.created_at) >= weekAgo).length;
        const monthCount = data.filter(r => toDate(r.created_at) >= monthAgo).length;

        text(el.total, String(data.length));
        text(el.today, String(todayCount));
        text(el.week, String(weekCount));
        text(el.month, String(monthCount));
    }

    function getFilteredRows() {
        const search = String(el.search?.value || '').toLowerCase().trim();
        const module = String(el.module?.value || '').trim();
        const action = String(el.action?.value || '').trim();
        const from = String(el.from?.value || '').trim();
        const to = String(el.to?.value || '').trim();

        const filtered = rows.filter(row => {
            const rowModule = String(row.module || '');
            const rowAction = String(row.action || '');
            const rowDate = String(row.created_at || '').slice(0, 10);

            const searchable = [
                row.username,
                row.module,
                row.action,
                row.description,
                row.ip_address,
                row.created_at
            ].join(' ').toLowerCase();

            if (search && !searchable.includes(search)) return false;
            if (module && rowModule !== module) return false;
            if (action && rowAction !== action) return false;
            if (from && rowDate < from) return false;
            if (to && rowDate > to) return false;

            return true;
        });

        return sortLatestFirst(filtered);
    }

    function renderTable() {
        const filtered = getFilteredRows();

        renderSummary(filtered);

        if (!filtered.length) {
            table?.destroy();
            table = null;

            html(el.content, `
                <div class="audit-empty-state">
                    <div class="audit-empty-icon">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div class="audit-empty-title">No audit logs found</div>
                    <div class="audit-empty-text">No records match the current filters.</div>
                </div>
            `);
            return;
        }

        html(el.content, `<div id="auditTable"></div>`);

        table?.destroy();

        table = datatable.create({
            el: '#auditTable',
            rows: filtered,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'created_at', dir: 'desc' },
            columns: [
                {
                    key: 'created_at',
                    label: 'Time',
                    render: v => escape(formatDateTime(v || '-'))
                },
                {
                    key: 'username',
                    label: 'User',
                    render: v => `<span class="fw-semibold">${escape(v || 'SYSTEM')}</span>`
                },
                {
                    key: 'module',
                    label: 'Module',
                    render: v => moduleBadge(v)
                },
                {
                    key: 'action',
                    label: 'Action',
                    render: v => actionBadge(v)
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: v => escape(v || '-')
                },
                {
                    key: 'ip_address',
                    label: 'IP Address',
                    render: v => escape(v || '-')
                }
            ]
        });
    }

    function moduleBadge(value) {
        return `<span class="badge rounded-pill bg-primary-subtle text-primary">${escape(value || '-')}</span>`;
    }

    function actionBadge(value) {
        const v = upper(value || '');

        if (['CREATE', 'STORE', 'LOGIN', 'CREATE_PLAN'].includes(v)) {
            return `<span class="badge rounded-pill bg-success-subtle text-success">${escape(v || '-')}</span>`;
        }

        if (['DELETE', 'DISABLE', 'RESET_PASSWORD', 'DELETE_PLAN'].includes(v)) {
            return `<span class="badge rounded-pill bg-danger-subtle text-danger">${escape(v || '-')}</span>`;
        }

        if (['UPDATE', 'EDIT', 'UPDATE_PLAN'].includes(v)) {
            return `<span class="badge rounded-pill bg-warning-subtle text-warning">${escape(v || '-')}</span>`;
        }

        return `<span class="badge rounded-pill bg-secondary-subtle text-secondary">${escape(v || '-')}</span>`;
    }

    function toDate(value) {
        const d = new Date(String(value || '').replace(' ', 'T'));
        return Number.isNaN(d.getTime()) ? new Date(0) : d;
    }

    function exportCsv() {
        const data = getFilteredRows();

        const header = ['Time', 'User', 'Module', 'Action', 'Description', 'IP Address'];
        const body = data.map(row => [
            row.created_at || '',
            row.username || '',
            row.module || '',
            row.action || '',
            row.description || '',
            row.ip_address || ''
        ]);

        const csv = [header, ...body]
            .map(r => r.map(csvEscape).join(','))
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = 'audit_logs.csv';
        a.click();

        URL.revokeObjectURL(url);
    }

    function csvEscape(value) {
        const str = String(value ?? '');
        return `"${str.replace(/"/g, '""')}"`;
    }
});