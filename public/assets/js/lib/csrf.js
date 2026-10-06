/* global jQuery */
(function ($) {
    'use strict';

    var HEADER = 'X-CSRF-TOKEN';

    function meta() {
        return $('meta[name="csrf-token"]');
    }

    function storeFresh(jqXHR) {
        var fresh = '';

        try {
            fresh = jqXHR.getResponseHeader(HEADER) || '';
        } catch (ignored) {
            fresh = '';
        }

        if (fresh !== '') {
            meta().attr('content', fresh);
        }

        return fresh;
    }

    window.Farmasi.csrf = {
        token: function () {
            return meta().attr('content') || '';
        },
        storeFresh: storeFresh
    };
})(jQuery);
