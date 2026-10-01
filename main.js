/* ЦКБ № 2 — публичный сайт: меню, языки, шапка */
(function () {
    'use strict';

    /* ---- Липкая шапка: тень после прокрутки ---- */
    var nav = document.querySelector('[data-mainnav]');
    if (nav) {
        var onScroll = function () {
            nav.classList.toggle('is-scrolled', window.scrollY > 4);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---- Бургер мобильного меню ---- */
    var burger = document.querySelector('[data-burger]');
    var siteNav = document.getElementById('site-nav');

    function closeMenu() {
        document.body.classList.remove('nav-open');
        if (burger) {
            burger.setAttribute('aria-expanded', 'false');
        }
    }

    if (burger && siteNav) {
        burger.addEventListener('click', function () {
            var open = document.body.classList.toggle('nav-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        siteNav.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                closeMenu();
            }
        });
    }

    /* ---- Переключатель языков ---- */
    var langSwitch = document.querySelector('[data-lang-switch]');
    if (langSwitch) {
        var langBtn = langSwitch.querySelector('.lang-switch__btn');

        langBtn.addEventListener('click', function () {
            var open = langSwitch.classList.toggle('is-open');
            langBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (langSwitch.classList.contains('is-open') && !langSwitch.contains(e.target)) {
                langSwitch.classList.remove('is-open');
                langBtn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && langSwitch.classList.contains('is-open')) {
                langSwitch.classList.remove('is-open');
                langBtn.setAttribute('aria-expanded', 'false');
                langBtn.focus();
            }
        });
    }
})();
