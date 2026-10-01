/* ЦКБ № 2 — мастер онлайн-записи (5 шагов). Конфиг: window.CKB2_BOOKING */
(function () {
    'use strict';

    var CFG = window.CKB2_BOOKING;
    var root = document.querySelector('[data-booking]');
    if (!CFG || !root) {
        return;
    }
    var T = CFG.i18n;

    var steps = {};
    root.querySelectorAll('.booking-step').forEach(function (s) {
        steps[s.getAttribute('data-step')] = s;
    });
    var progressItems = Array.prototype.slice.call(root.querySelectorAll('.booking-progress__item'));
    var stepInfo = root.querySelector('[data-step-info]');
    var deptTiles = Array.prototype.slice.call(document.querySelectorAll('.dept-tile'));
    var doctorsList = root.querySelector('[data-doctors-list]');
    var calendarBox = root.querySelector('[data-calendar]');
    var slotsPanel = root.querySelector('[data-slots-panel]');
    var miniSummary = root.querySelector('[data-mini-summary]');
    var summaryBox = root.querySelector('[data-summary]');
    var summaryRows = root.querySelector('[data-summary-rows]');
    var form = document.getElementById('booking-form');
    var formAlert = root.querySelector('[data-form-alert]');
    var doneBox = root.querySelector('[data-done]');
    var submitBtn = root.querySelector('[data-submit]');

    var state = {
        departmentId: null,
        departmentName: '',
        doctor: null,
        date: null,
        time: null,
        calMonth: null,
        availability: {}
    };
    var doctorCache = [];

    /* ---- Утилиты ---- */

    function fmt(tpl, n) {
        return String(tpl).replace('{n}', String(n));
    }

    function dateHuman(dateStr) {
        var p = dateStr.split('-');
        return parseInt(p[2], 10) + ' ' + CFG.monthsGen[parseInt(p[1], 10) - 1] + ' ' + p[0];
    }

    function monthTitle(month) {
        var p = month.split('-');
        return CFG.months[parseInt(p[1], 10) - 1] + ' ' + p[0];
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function getJson(url) {
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            });
    }

    function loaderHtml() {
        return '<div class="booking-loader"><span></span>' + escapeHtml(T.loading) + '</div>';
    }

    /* ---- Навигация по шагам ---- */

    function goToStep(n) {
        Object.keys(steps).forEach(function (key) {
            var s = steps[key];
            var active = key === String(n);
            s.hidden = !active;
            s.classList.toggle('is-active', active);
        });
        progressItems.forEach(function (item, i) {
            item.classList.toggle('is-active', i === n - 1);
            item.classList.toggle('is-done', i < n - 1);
        });
        if (stepInfo) {
            stepInfo.textContent = fmt(T.step, n);
        }
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function currentStep() {
        for (var i = 1; i <= 5; i++) {
            if (steps[String(i)] && !steps[String(i)].hidden) {
                return i;
            }
        }
        return 1;
    }

    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-back]') && currentStep() > 1) {
            goToStep(currentStep() - 1);
        }
    });

    /* ---- Шаг 1: направление ---- */

    deptTiles.forEach(function (tile) {
        tile.addEventListener('click', function () {
            selectDepartment(
                parseInt(tile.getAttribute('data-department-id'), 10),
                tile.getAttribute('data-department-name')
            );
        });
    });

    function selectDepartment(id, name) {
        state.departmentId = id;
        state.departmentName = name;
        state.doctor = null;
        state.date = null;
        state.time = null;

        deptTiles.forEach(function (t) {
            t.classList.toggle('is-selected', parseInt(t.getAttribute('data-department-id'), 10) === id);
        });

        loadDoctors(id);
    }

    /* ---- Шаг 2: врач ---- */

    function loadDoctors(departmentId) {
        doctorsList.innerHTML = loaderHtml();
        goToStep(2);

        getJson(CFG.urls.doctors + '?department_id=' + encodeURIComponent(departmentId))
            .then(function (res) {
                if (!res.ok || !res.doctors) {
                    throw new Error('bad response');
                }
                renderDoctors(res.doctors);
                if (CFG.preselect && CFG.preselect.department_id === departmentId) {
                    var found = res.doctors.filter(function (d) {
                        return d.id === CFG.preselect.doctor_id;
                    })[0];
                    if (found) {
                        CFG.preselect = null;
                        selectDoctor(found);
                    }
                }
            })
            .catch(function () {
                doctorsList.innerHTML =
                    '<div class="empty-state"><p>' + escapeHtml(T.error) + '</p>' +
                    '<button type="button" class="btn btn--outline btn--sm" data-retry-doctors>' +
                    escapeHtml(T.retry) + '</button></div>';
            });
    }

    doctorsList.addEventListener('click', function (e) {
        if (e.target.closest('[data-retry-doctors]') && state.departmentId) {
            loadDoctors(state.departmentId);
            return;
        }
        var row = e.target.closest('.doctor-row');
        if (row) {
            var id = parseInt(row.getAttribute('data-doctor-id'), 10);
            var doc = doctorCache.filter(function (d) {
                return d.id === id;
            })[0];
            if (doc) {
                selectDoctor(doc);
            }
        }
    });

    function renderDoctors(list) {
        doctorCache = list;

        if (!list.length) {
            doctorsList.innerHTML = '<div class="empty-state"><p>' + escapeHtml(T.no_doctors) + '</p></div>';
            return;
        }

        var html = '<p class="booking-count">' + escapeHtml(fmt(T.found, list.length)) + '</p>';

        html += list.map(function (doc) {
            var photo = doc.photo
                ? '<img src="' + escapeHtml(doc.photo) + '" alt="' + escapeHtml(doc.name) + '" loading="lazy" width="88" height="104">'
                : '<span class="doctor-row__initials">' + escapeHtml(doc.initials || '') + '</span>';

            var meta = '';
            if (doc.experience) {
                meta += '<span class="doctor-row__meta-item">' +
                    '<svg class="icon"><use href="#i-clock"/></svg>' + escapeHtml(doc.experience) + '</span>';
            }
            if (doc.office) {
                meta += '<span class="doctor-row__meta-item">' +
                    '<svg class="icon"><use href="#i-pin"/></svg>' + escapeHtml(doc.office) + '</span>';
            }

            return '<button type="button" class="doctor-row" data-doctor-id="' + doc.id + '">' +
                '<span class="doctor-row__photo">' + photo + '</span>' +
                '<span class="doctor-row__body">' +
                (doc.category ? '<span class="doctor-row__category">' + escapeHtml(doc.category) + '</span>' : '') +
                '<strong class="doctor-row__name">' + escapeHtml(doc.name) + '</strong>' +
                '<span class="doctor-row__position">' + escapeHtml(doc.position) + '</span>' +
                (meta ? '<span class="doctor-row__meta">' + meta + '</span>' : '') +
                '</span>' +
                '<svg class="icon doctor-row__arrow"><use href="#i-arrow"/></svg>' +
                '</button>';
        }).join('');

        doctorsList.innerHTML = html;
    }

    function selectDoctor(doc) {
        state.doctor = doc;
        state.date = null;
        state.time = null;
        state.calMonth = null;
        state.availability = {};

        doctorsList.querySelectorAll('.doctor-row').forEach(function (row) {
            row.classList.toggle('is-selected', parseInt(row.getAttribute('data-doctor-id'), 10) === doc.id);
        });

        renderMiniSummary();
        initCalendar();
        goToStep(3);
    }

    function renderMiniSummary() {
        if (!state.doctor) {
            miniSummary.hidden = true;
            return;
        }
        miniSummary.hidden = false;
        miniSummary.innerHTML =
            '<span class="booking-mini__label">' + escapeHtml(T.f_doctor) + '</span>' +
            '<strong class="booking-mini__name">' + escapeHtml(state.doctor.name) + '</strong>' +
            '<span class="booking-mini__position">' + escapeHtml(state.doctor.position) + '</span>' +
            '<button type="button" class="btn btn--ghost btn--sm" data-back>' + escapeHtml(T.change) + '</button>';
    }

    /* ---- Шаг 3: календарь и слоты ---- */

    function initCalendar() {
        var base = state.date || CFG.today;
        state.calMonth = base.slice(0, 7);
        renderCalendar(true);
    }

    function shiftMonth(delta) {
        var p = state.calMonth.split('-');
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1 + delta, 1);
        var month = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');

        var minMonth = CFG.today.slice(0, 7);
        var maxMonth = CFG.maxDate.slice(0, 7);
        if (month < minMonth || month > maxMonth) {
            return;
        }
        state.calMonth = month;
        renderCalendar(true);
    }

    /**
     * Рендер календаря.
     * @param {boolean} fetchMonth — запрашивать ли доступность месяца с сервера
     */
    function renderCalendar(fetchMonth) {
        if (!state.doctor) {
            return;
        }

        var p = state.calMonth.split('-');
        var year = parseInt(p[0], 10);
        var month = parseInt(p[1], 10);
        var firstDay = new Date(year, month - 1, 1);
        var daysInMonth = new Date(year, month, 0).getDate();
        var offset = (firstDay.getDay() + 6) % 7; // неделя начинается с понедельника

        var html =
            '<div class="cal__head">' +
            '<button type="button" class="cal__nav" data-cal-prev aria-label="&lsaquo;">' +
            '<svg class="icon icon--flip"><use href="#i-chevron"/></svg></button>' +
            '<strong class="cal__title">' + escapeHtml(monthTitle(state.calMonth)) + '</strong>' +
            '<button type="button" class="cal__nav" data-cal-next aria-label="&rsaquo;">' +
            '<svg class="icon"><use href="#i-chevron"/></svg></button>' +
            '</div>' +
            '<div class="cal__week">' + CFG.weekdays.map(function (w) {
                return '<span>' + escapeHtml(w) + '</span>';
            }).join('') + '</div>' +
            '<div class="cal__grid">';

        for (var pad = 0; pad < offset; pad++) {
            html += '<span class="cal__day cal__day--pad"></span>';
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var date = state.calMonth + '-' + String(day).padStart(2, '0');
            var classes = 'cal__day';
            var isPast = date < CFG.today;
            var isBeyond = date > CFG.maxDate;
            var info = state.availability[date];
            var clickable = false;

            if (isPast) {
                classes += ' is-past';
            } else if (isBeyond) {
                classes += ' is-off';
            } else if (info && info.working && info.free > 0) {
                clickable = true;
            } else if (info && info.working && info.free === 0) {
                classes += ' is-full';
            } else {
                classes += ' is-off';
            }

            if (date === CFG.today) {
                classes += ' is-today';
            }
            if (date === state.date) {
                classes += ' is-selected';
            }

            html += clickable
                ? '<button type="button" class="' + classes + '" data-date="' + date + '">' + day + '</button>'
                : '<span class="' + classes + '" aria-disabled="true">' + day + '</span>';
        }

        html += '</div>';
        calendarBox.innerHTML = html;

        if (fetchMonth) {
            fetchAvailability(state.calMonth);
        }
    }

    calendarBox.addEventListener('click', function (e) {
        if (e.target.closest('[data-cal-prev]')) {
            shiftMonth(-1);
            return;
        }
        if (e.target.closest('[data-cal-next]')) {
            shiftMonth(1);
            return;
        }
        var dayBtn = e.target.closest('[data-date]');
        if (dayBtn) {
            selectDate(dayBtn.getAttribute('data-date'));
        }
    });

    function fetchAvailability(month) {
        if (!state.doctor) {
            return;
        }
        calendarBox.classList.add('is-loading');

        getJson(CFG.urls.availability + '?doctor_id=' + encodeURIComponent(state.doctor.id) + '&month=' + month)
            .then(function (res) {
                if (!res.ok || !res.days) {
                    throw new Error('bad response');
                }
                Object.keys(res.days).forEach(function (d) {
                    state.availability[d] = res.days[d];
                });
                renderCalendar(false);
                calendarBox.classList.remove('is-loading');
            })
            .catch(function () {
                calendarBox.classList.remove('is-loading');
            });
    }

    function selectDate(date) {
        state.date = date;
        state.time = null;

        calendarBox.querySelectorAll('[data-date]').forEach(function (b) {
            b.classList.toggle('is-selected', b.getAttribute('data-date') === date);
        });

        loadSlots(date);
    }

    function loadSlots(date) {
        slotsPanel.innerHTML =
            '<p class="booking-slots__date">' + escapeHtml(dateHuman(date)) + '</p>' +
            '<h3 class="booking-slots__title">' + escapeHtml(T.slots_title) + '</h3>' +
            loaderHtml();

        getJson(CFG.urls.slots + '?doctor_id=' + encodeURIComponent(state.doctor.id) + '&date=' + encodeURIComponent(date))
            .then(function (res) {
                if (!res.ok || !res.slots) {
                    throw new Error('bad response');
                }
                renderSlots(res.slots);
            })
            .catch(function () {
                slotsPanel.innerHTML =
                    '<p class="booking-slots__date">' + escapeHtml(dateHuman(date)) + '</p>' +
                    '<div class="empty-state"><p>' + escapeHtml(T.error) + '</p></div>';
            });
    }

    function renderSlots(slots) {
        var head =
            '<p class="booking-slots__date">' + escapeHtml(dateHuman(state.date)) + '</p>' +
            '<h3 class="booking-slots__title">' + escapeHtml(T.slots_title) + '</h3>';

        if (!slots.length) {
            slotsPanel.innerHTML = head + '<div class="empty-state"><p>' + escapeHtml(T.no_slots) + '</p></div>';
            return;
        }

        slotsPanel.innerHTML = head +
            '<p class="booking-slots__hint">' + escapeHtml(T.select_slot) + '</p>' +
            '<div class="slot-grid">' + slots.map(function (t) {
                return '<button type="button" class="slot" data-time="' + t + '">' + t + '</button>';
            }).join('') + '</div>';
    }

    slotsPanel.addEventListener('click', function (e) {
        var slot = e.target.closest('.slot');
        if (!slot) {
            return;
        }
        state.time = slot.getAttribute('data-time');
        slotsPanel.querySelectorAll('.slot').forEach(function (s) {
            s.classList.toggle('is-selected', s === slot);
        });
        renderSummary();
        goToStep(4);
    });

    /* ---- Шаг 4: сводка и форма ---- */

    function renderSummary() {
        if (!state.doctor || !state.date || !state.time) {
            summaryBox.hidden = true;
            return;
        }
        summaryBox.hidden = false;
        var rows = [
            [T.f_doctor, state.doctor.name],
            [T.f_dept, state.departmentName],
            [T.f_date, dateHuman(state.date)],
            [T.f_time, state.time],
            [T.f_office, state.doctor.office]
        ];

        summaryRows.innerHTML = rows.map(function (r) {
            return '<div class="booking-summary__row"><dt>' + escapeHtml(r[0]) + '</dt><dd>' + escapeHtml(r[1]) + '</dd></div>';
        }).join('') +
        '<button type="button" class="btn btn--ghost btn--sm" data-back>' + escapeHtml(T.change) + '</button>';
    }

    /* Маска телефона: +998 (90) 123-45-67 */
    var phoneInput = form.querySelector('#f-phone');
    phoneInput.addEventListener('input', function () {
        var d = this.value.replace(/\D+/g, '');
        if (d.indexOf('998') === 0) {
            d = d.slice(3);
        }
        d = d.slice(0, 9);
        if (!d) {
            this.value = '';
            return;
        }
        var v = '+998';
        if (d.length <= 2) {
            v += ' (' + d;
        } else {
            v += ' (' + d.slice(0, 2) + ') ' + d.slice(2, 5);
        }
        if (d.length > 5) {
            v += '-' + d.slice(5, 7);
        }
        if (d.length > 7) {
            v += '-' + d.slice(7, 9);
        }
        this.value = v;
    });

    /* Паспорт: AA1234567 — только латиница и цифры */
    var passportInput = form.querySelector('#f-passport');
    passportInput.addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 9);
    });

    /* ПИНФЛ: только цифры */
    var pinflInput = form.querySelector('#f-pinfl');
    pinflInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D+/g, '').slice(0, 14);
    });

    /* Имя: первая буква заглавная (требование серверной валидации) */
    var nameInput = form.querySelector('#f-name');
    nameInput.addEventListener('blur', function () {
        var v = this.value.trim();
        if (v.length > 1) {
            this.value = v.charAt(0).toLocaleUpperCase() + v.slice(1);
        }
    });

    /* ---- Ошибки формы ---- */

    function setFieldError(key, message) {
        var p = root.querySelector('[data-error-for="' + key + '"]');
        if (!p) {
            return;
        }
        p.textContent = message || '';
        p.hidden = !message;
        var field = p.closest('.field');
        if (field) {
            field.classList.toggle('is-error', !!message);
        }
    }

    function clearAllErrors() {
        root.querySelectorAll('[data-error-for]').forEach(function (p) {
            p.hidden = true;
            p.textContent = '';
            var field = p.closest('.field');
            if (field) {
                field.classList.remove('is-error');
            }
        });
        hideAlert();
    }

    function showAlert(html) {
        formAlert.innerHTML = html;
        formAlert.hidden = false;
    }

    function hideAlert() {
        formAlert.hidden = true;
        formAlert.innerHTML = '';
    }

    function validateForm() {
        var errors = {};

        var name = nameInput.value.trim();
        if (name.length < 3 || name.length > 150 || !/^[\p{L}][\p{L}\s'’\-.]+$/u.test(name)) {
            errors.patient_name = T.err_name;
        }

        var digits = phoneInput.value.replace(/\D+/g, '');
        if (digits.indexOf('998') === 0) {
            digits = digits.slice(3);
        }
        if (digits.length !== 9) {
            errors.patient_phone = T.err_phone;
        }

        var birth = form.querySelector('#f-birth').value;
        if (birth !== '' && (birth > CFG.today || birth < '1900-01-01')) {
            errors.patient_birth_date = T.err_birth;
        }

        var passport = passportInput.value.trim();
        if (passport !== '' && !/^[A-Z]{2}\d{7}$/.test(passport)) {
            errors.patient_passport = T.err_passport;
        }

        var pinfl = pinflInput.value.trim();
        if (pinfl !== '' && !/^\d{14}$/.test(pinfl)) {
            errors.patient_pinfl = T.err_pinfl;
        }

        var comment = form.querySelector('#f-comment').value.trim();
        if (comment.length > 1000) {
            errors.patient_comment = T.err_comment;
        }

        if (!form.querySelector('input[name="consent"]').checked) {
            errors.consent = T.err_consent;
        }

        return errors;
    }

    /* ---- Отправка ---- */

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearAllErrors();

        if (!state.doctor || !state.date || !state.time) {
            showAlert('<svg class="icon"><use href="#i-alert"/></svg><p>' + escapeHtml(T.err_generic) + '</p>');
            goToStep(3);
            return;
        }

        var errors = validateForm();
        var keys = Object.keys(errors);
        if (keys.length) {
            keys.forEach(function (k) {
                setFieldError(k, errors[k]);
            });
            showAlert('<svg class="icon"><use href="#i-alert"/></svg><p>' + escapeHtml(T.errors) + '</p>');
            return;
        }

        sendBooking();
    });

    function sendBooking() {
        var btnText = submitBtn.querySelector('span');
        var original = btnText.textContent;
        submitBtn.disabled = true;
        btnText.textContent = T.submitting;

        var payload = {
            csrf_token: CFG.csrf,
            website: form.querySelector('#hp-website').value,
            doctor_id: state.doctor.id,
            department_id: state.departmentId,
            appointment_date: state.date,
            appointment_time: state.time,
            patient_name: nameInput.value.trim(),
            patient_phone: phoneInput.value.trim(),
            patient_birth_date: form.querySelector('#f-birth').value,
            patient_passport: passportInput.value.trim(),
            patient_pinfl: pinflInput.value.trim(),
            patient_comment: form.querySelector('#f-comment').value.trim(),
            consent: form.querySelector('input[name="consent"]').checked ? '1' : ''
        };

        fetch(CFG.urls.book, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CFG.csrf
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) {
                return r.json().catch(function () {
                    return { ok: false, errors: { form: T.err_generic } };
                });
            })
            .then(function (res) {
                if (res.ok && res.appointment) {
                    renderDone(res.appointment);
                    goToStep(5);
                    return;
                }
                showServerErrors(res.errors || {});
            })
            .catch(function () {
                showAlert('<svg class="icon"><use href="#i-alert"/></svg><p>' + escapeHtml(T.err_generic) + '</p>');
            })
            .then(function () {
                submitBtn.disabled = false;
                btnText.textContent = original;
            });
    }

    function showServerErrors(errors) {
        var alertShown = false;

        Object.keys(errors).forEach(function (key) {
            var msg = errors[key];
            if (key === 'form') {
                showAlert('<svg class="icon"><use href="#i-alert"/></svg><p>' + escapeHtml(msg) + '</p>');
                alertShown = true;
            } else if (key === 'appointment_time' || key === 'doctor_id') {
                showAlert(
                    '<svg class="icon"><use href="#i-alert"/></svg><p>' + escapeHtml(msg) + '</p>' +
                    '<button type="button" class="btn btn--outline btn--sm" data-back>' + escapeHtml(T.change) + '</button>'
                );
                alertShown = true;
            } else {
                setFieldError(key, msg);
            }
        });

        if (!alertShown && Object.keys(errors).length) {
            showAlert('<svg class="icon"><use href="#i-alert"/></svg><p>' + escapeHtml(T.errors) + '</p>');
        }
    }

    /* ---- Шаг 5: подтверждение ---- */

    function renderDone(appt) {
        var rows = [
            [T.f_doctor, appt.doctor],
            [T.f_dept, appt.department],
            [T.f_date, appt.date_human],
            [T.f_time, appt.time],
            [T.f_office, appt.office],
            [T.f_patient, appt.patient_name],
            [T.f_phone, appt.patient_phone]
        ];

        doneBox.innerHTML =
            '<div class="booking-done__head">' +
            '<span class="booking-done__icon"><svg class="icon"><use href="#i-check"/></svg></span>' +
            '<h2 class="booking-done__title">' + escapeHtml(T.done_title) + '</h2>' +
            '<p class="booking-done__lead">' + escapeHtml(T.done_lead) + '</p>' +
            '</div>' +
            '<div class="booking-ticket">' +
            '<span class="booking-ticket__label">' + escapeHtml(T.ticket) + '</span>' +
            '<strong class="booking-ticket__num">' + escapeHtml(appt.ticket) + '</strong>' +
            '</div>' +
            '<dl class="booking-done__grid">' +
            rows.map(function (r) {
                return '<div class="booking-done__cell"><dt>' + escapeHtml(r[0]) + '</dt><dd>' + escapeHtml(r[1]) + '</dd></div>';
            }).join('') +
            '</dl>' +
            '<div class="booking-instructions">' +
            '<h3>' + escapeHtml(T.instr_title) + '</h3>' +
            '<ol>' + [T.i1, T.i2, T.i3, T.i4, T.i5].map(function (i) {
                return '<li>' + escapeHtml(i) + '</li>';
            }).join('') + '</ol>' +
            '</div>' +
            '<div class="booking-done__actions">' +
            '<button type="button" class="btn btn--primary" data-done-print>' + escapeHtml(T.print) + '</button>' +
            '<button type="button" class="btn btn--outline" data-done-again>' + escapeHtml(T.again) + '</button>' +
            '<a class="btn btn--ghost" href="' + escapeHtml(CFG.urls.home) + '">' + escapeHtml(T.home) + '</a>' +
            '</div>';
    }

    doneBox.addEventListener('click', function (e) {
        if (e.target.closest('[data-done-print]')) {
            window.print();
        }
        if (e.target.closest('[data-done-again]')) {
            resetBooking();
        }
    });

    function resetBooking() {
        state.departmentId = null;
        state.departmentName = '';
        state.doctor = null;
        state.date = null;
        state.time = null;
        state.calMonth = null;
        state.availability = {};
        doctorCache = [];

        form.reset();
        clearAllErrors();
        doctorsList.innerHTML = '';
        slotsPanel.innerHTML = '';
        doneBox.innerHTML = '';
        miniSummary.hidden = true;
        summaryBox.hidden = true;
        summaryRows.innerHTML = '';
        deptTiles.forEach(function (t) {
            t.classList.remove('is-selected');
        });

        goToStep(1);
    }

    /* ---- Инициализация: предвыбор врача из zapis.php?doctor=N ---- */

    if (CFG.preselect) {
        var tile = deptTiles.filter(function (t) {
            return parseInt(t.getAttribute('data-department-id'), 10) === CFG.preselect.department_id;
        })[0];
        if (tile) {
            selectDepartment(CFG.preselect.department_id, tile.getAttribute('data-department-name'));
        }
    }
})();
