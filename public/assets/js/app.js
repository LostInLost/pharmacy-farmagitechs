/* global jQuery */
(function ($) {
    'use strict';

    var boot = window.FARMASI_BOOT || {};
    var i18n = boot.i18n || {};

    window.Farmasi = {
        boot: boot,
        text: function (key, fallback) {
            var value = Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : null;

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

            this.redirect(boot.loginUrl);
        }
    };
})(jQuery);
