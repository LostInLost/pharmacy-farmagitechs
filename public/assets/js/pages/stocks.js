/* global jQuery */
(function ($) {
    'use strict';

    $(function () {
        var Farmasi = window.Farmasi;
        var esc = Farmasi.ui.escapeHtml;
        var $form = $('#stocks-filter');
        var $tbody = $('#stocks-tbody');
        var $feedback = $('#feedback');

        function batchRows(batches, badgeClass, badgeText) {
            return batches.map(function (batch) {
                return '<tr>' +
                    '<td>' + esc(batch.batch_no) + '</td>' +
                    '<td>' + esc(batch.expires_on === null || batch.expires_on === undefined ? '-' : batch.expires_on) + '</td>' +
                    '<td class="text-end">' + Number(batch.quantity) + '</td>' +
                    '<td><span class="badge ' + badgeClass + '">' + esc(badgeText) + '</span></td>' +
                    '</tr>';
            }).join('');
        }

        function batchCell(medicine) {
            var available = medicine.available_batches || [];
            var expired = medicine.expired_batches || [];

            if (available.length === 0 && expired.length === 0) {
                return '<span class="text-muted">' + esc(Farmasi.text('noBatch', '')) + '</span>';
            }

            var count = Farmasi.ui.fill(Farmasi.text('batchCount', ''), [available.length + expired.length]);

            return '<details><summary>' + esc(count) + '</summary>' +
                '<table class="table table-sm table-bordered mb-0 mt-2">' +
                '<thead class="table-light"><tr>' +
                '<th>' + esc(Farmasi.text('batchNo', '')) + '</th>' +
                '<th>' + esc(Farmasi.text('expiresOn', '')) + '</th>' +
                '<th class="text-end">' + esc(Farmasi.text('quantity', '')) + '</th>' +
                '<th>' + esc(Farmasi.text('status', '')) + '</th>' +
                '</tr></thead><tbody>' +
                batchRows(available, 'text-bg-success', Farmasi.text('statusAvailable', '')) +
                batchRows(expired, 'text-bg-danger', Farmasi.text('statusExpired', '')) +
                '</tbody></table></details>';
        }

        function render(report) {
            var medicines = report.medicines || [];

            $('#on_date').val(report.on_date || '');

            if (medicines.length === 0) {
                $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">' +
                    esc(Farmasi.text('noBatch', '')) + '</td></tr>');
                return;
            }

            var html = medicines.map(function (medicine) {
                return '<tr>' +
                    '<td>' + esc(medicine.code) + '</td>' +
                    '<td>' + esc(medicine.name) + '</td>' +
                    '<td>' + esc(medicine.unit) + '</td>' +
                    '<td class="text-end">' + Number(medicine.physical_quantity) + '</td>' +
                    '<td class="text-end">' + Number(medicine.available_quantity) + '</td>' +
                    '<td class="text-end">' + Number(medicine.expired_quantity) + '</td>' +
                    '<td>' + batchCell(medicine) + '</td>' +
                    '</tr>';
            }).join('');

            $tbody.html(html);
        }

        function load(onDate) {
            $feedback.empty();
            $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">' +
                esc(Farmasi.text('loading', '')) + '</td></tr>');

            Farmasi.api.stocks.report(onDate).then(
                function (result) {
                    render(result.body);
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
        }

        $form.on('submit', function (event) {
            event.preventDefault();
            load($('#on_date').val());
        });

        load($('#on_date').val() || undefined);
    });
})(jQuery);
