/* global jQuery */
(function ($) {
    'use strict';

    // Dibaca live setiap kali diakses: skrip ini di-load layout SEBELUM
    // section scripts halaman mendefinisikan window.FARMASI_BOOT, jadi
    // snapshot sekali di sini akan kosong selamanya.
    function boot() {
        return window.FARMASI_BOOT || {};
    }

    function strings() {
        return boot().i18n || {};
    }

    var Farmasi = {
        text: function (key, fallback) {
            var table = strings();
            var value = Object.prototype.hasOwnProperty.call(table, key) ? table[key] : null;

            return value === null || value === undefined || value === '' ? fallback : value;
        },
        redirect: function (url) {
            window.location.href = url;
        },
        sessionExpired: function () {
            var message = this.text('sessionExpired', '');

            if (message !== '') {
                window.alert(message);
            }

            this.redirect(boot().loginUrl);
        }
    };

    Object.defineProperty(Farmasi, 'boot', {
        enumerable: true,
        get: function () {
            return boot();
        }
    });

    window.Farmasi = Farmasi;
})(jQuery);
