/* global jQuery */
(function ($) {
    'use strict';

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fill(template, args) {
        return String(template).replace(/\{(\d+)\}/g, function (match, index) {
            return args[Number(index)] === undefined ? match : String(args[Number(index)]);
        });
    }

    function showAlert($target, kind, title, messages) {
        var html = '<div class="alert alert-' + kind + '" role="alert">';

        if (title) {
            html += '<strong>' + escapeHtml(title) + '</strong>';
        }

        if (messages && messages.length > 0) {
            html += '<ul class="mb-0">' + messages.map(function (message) {
                return '<li>' + escapeHtml(message) + '</li>';
            }).join('') + '</ul>';
        }

        html += '</div>';

        $target.html(html);
        window.scrollTo(0, 0);
    }

    window.Farmasi.ui = {
        escapeHtml: escapeHtml,
        fill: fill,
        error: function ($target, title, messages) {
            showAlert($target, 'danger', title, messages);
        },
        success: function ($target, message) {
            showAlert($target, 'success', '', message ? [message] : []);
        }
    };
})(jQuery);
