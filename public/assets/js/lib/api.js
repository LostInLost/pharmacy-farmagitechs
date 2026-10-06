/* global jQuery */
(function ($) {
    'use strict';

    function isCsrfFailure(jqXHR) {
        if (jqXHR.status !== 403) {
            return false;
        }

        var body = jqXHR.responseJSON;

        return !! body && body.error === 'csrf';
    }

    function request(method, url, payload, isRetry) {
        var Farmasi = window.Farmasi;

        return $.ajax({
            url: url,
            method: method,
            contentType: 'application/json',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': Farmasi.csrf.token()
            },
            data: payload === undefined ? undefined : JSON.stringify(payload)
        }).then(
            function (body, textStatus, jqXHR) {
                Farmasi.csrf.storeFresh(jqXHR);

                return { status: jqXHR.status, body: body };
            },
            function (jqXHR) {
                var fresh = Farmasi.csrf.storeFresh(jqXHR);

                if (isCsrfFailure(jqXHR) && ! isRetry && fresh !== '') {
                    return request(method, url, payload, true);
                }

                if (jqXHR.status === 401 && Farmasi.boot.loginUrl) {
                    Farmasi.sessionExpired();
                }

                var body = jqXHR.responseJSON || {};

                return $.Deferred().reject({ status: jqXHR.status, body: body }).promise();
            }
        );
    }

    function endpoints() {
        return window.Farmasi.boot.endpoints || {};
    }

    window.Farmasi.api = {
        request: function (method, url, payload) {
            return request(method, url, payload, false);
        },
        login: function (payload) {
            return request('POST', endpoints().login, payload, false);
        },
        receipts: {
            list: function () {
                return request('GET', endpoints().receipts, undefined, false);
            },
            show: function (id) {
                return request('GET', endpoints().receipts + '/' + id, undefined, false);
            },
            create: function (payload) {
                return request('POST', endpoints().receipts, payload, false);
            },
            update: function (id, payload) {
                return request('PUT', endpoints().receipts + '/' + id, payload, false);
            }
        },
        stocks: {
            report: function (onDate) {
                var url = endpoints().stocks;

                if (onDate) {
                    url += '?on_date=' + encodeURIComponent(onDate);
                }

                return request('GET', url, undefined, false);
            }
        },
        references: {
            suppliers: function () {
                return request('GET', endpoints().suppliers, undefined, false);
            },
            medicines: function () {
                return request('GET', endpoints().medicines, undefined, false);
            }
        }
    };
})(jQuery);
