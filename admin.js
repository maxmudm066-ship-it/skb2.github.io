/* ЦКБ № 2 — админ-панель: меню, AJAX-статусы, подтверждения, расписание */
(function () {
    'use strict';

    var cfg = window.CKB2_ADMIN || {};

    /* ---- Мобильное меню: бургер + затемнение ---- */
    var burger = document.querySelector('.topbar__burger');
    var sidebar = document.querySelector('.admin-shell .sidebar');
    var overlay = document.querySelector('.sidebar-overlay');

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('is-open');
        }
        if (overlay) {
            overlay.hidden = true;
        }
        if (burger) {
            burger.setAttribute('aria-expanded', 'false');
        }
    }

    if (burger && sidebar && overlay) {
        burger.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            overlay.hidden = !open;
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                closeSidebar();
            }
        });
    }

    /* ---- AJAX-смена статуса заявки ---- */
    function bindStatusSelects() {
        var selects = document.querySelectorAll('.status-select[data-id]');
        selects.forEach(function (sel) {
            sel.addEventListener('change', function () {
                var prev = sel.dataset.prevValue || sel.value;
                var id = sel.getAttribute('data-id');

                sel.disabled = true;

                fetch(cfg.statusUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': cfg.csrf || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: parseInt(id, 10), status: sel.value })
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, data: data };
                        });
                    })
                    .then(function (r) {
                        if (!r.ok || !r.data.ok) {
                            throw new Error(r.data.message || 'Не удалось изменить статус.');
                        }
                        sel.dataset.prevValue = sel.value;
                    })
                    .catch(function (err) {
                        sel.value = prev;
                        sel.dataset.prevValue = prev;
                        alert(err.message || 'Не удалось изменить статус.');
                    })
                    .finally(function () {
                        sel.disabled = false;
                    });
            });

            sel.dataset.prevValue = sel.value;
        });
    }
    bindStatusSelects();

    /* ---- Подтверждение опасных действий ---- */
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });

    /* ---- Автоотправка фильтров ---- */
    document.addEventListener('change', function (e) {
        var el = e.target.closest('[data-autosubmit]');
        if (el && el.form) {
            el.form.submit();
        }
    });

    /* ---- Добавление интервала в недельном графике ---- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-add-interval]');
        if (!btn) {
            return;
        }
        var wrap = btn.closest('.sched-intervals');
        if (!wrap) {
            return;
        }

        var proto = wrap.querySelector('.sched-interval');
        if (!proto) {
            return;
        }

        var clone = proto.cloneNode(true);
        clone.querySelectorAll('input[type="time"]').forEach(function (input) {
            input.value = '';
        });
        wrap.insertBefore(clone, btn);
        var first = clone.querySelector('input[type="time"]');
        if (first) {
            first.focus();
        }
    });

    /* ---- Печать страницы ---- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-print-btn]');
        if (btn) {
            e.preventDefault();
            window.print();
        }
    });
})();
