/* global jQuery */
(function ($) {
    'use strict';

    $(function () {
        var Farmasi = window.Farmasi;
        var esc = Farmasi.ui.escapeHtml;
        var $tbody = $('#receptions-tbody');
        var $feedback = $('#feedback');

        function personCell(name, at, neverUpdated) {
            var html = esc(name === null || name === undefined ? '-' : name);

            if (at === null || at === undefined) {
                html += '<div class="text-muted small">' + esc(neverUpdated) + '</div>';
            } else {
                html += '<div class="text-muted small">' + esc(at) + '</div>';
            }

            return html;
        }

        function actionCell(row, i18n) {
            var editUrl = Farmasi.boot.endpoints.editBase + row.id;

            if (row.can_update) {
                return '<a class="btn btn-sm btn-outline-primary" href="' + esc(editUrl) + '">' +
                    esc(i18n.edit) + '</a>';
            }

            return '<span class="badge text-bg-secondary">' + esc(i18n.notAllowed) + '</span>';
        }

        function render(rows, i18n) {
            if (rows.length === 0) {
                $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">' +
                    esc(i18n.empty) + '</td></tr>');
                return;
            }

            var html = rows.map(function (row) {
                var updatedCell = row.updated_by === null || row.updated_by === undefined
                    ? '<span class="text-muted">' + esc(i18n.neverUpdated) + '</span>'
                    : personCell(row.updated_by_name, row.updated_at, i18n.neverUpdated);

                return '<tr>' +
                    '<td><a href="' + esc(Farmasi.boot.endpoints.editBase + row.id) + '">' +
                    esc(row.reference_no) + '</a></td>' +
                    '<td>' + esc(row.supplier_name === null || row.supplier_name === undefined ? '-' : row.supplier_name) + '</td>' +
                    '<td>' + esc(row.received_at) + '</td>' +
                    '<td>' + personCell(row.created_by_name, row.created_at, i18n.neverUpdated) + '</td>' +
                    '<td>' + updatedCell + '</td>' +
                    '<td class="text-end">' + actionCell(row, i18n) + '</td>' +
                    '</tr>';
            }).join('');

            $tbody.html(html);
        }

        $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">' +
            esc(Farmasi.text('loading', '')) + '</td></tr>');

        Farmasi.api.receipts.list().then(
            function (result) {
                render(result.body.data || [], {
                    edit: Farmasi.text('edit', ''),
                    empty: Farmasi.text('empty', ''),
                    notAllowed: Farmasi.text('notAllowed', ''),
                    neverUpdated: Farmasi.text('neverUpdated', '')
                });
            },
            function (rejection) {
                if (rejection.status === 401) {
                    return;
                }

                $tbody.empty();

                var message = rejection.body && rejection.body.message
                    ? rejection.body.message
                    : Farmasi.text('loadFailed', '');

                Farmasi.ui.error($feedback, '', [message]);
            }
        );
    });
})(jQuery);
