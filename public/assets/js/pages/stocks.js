/* global jQuery */
(function ($) {
    'use strict';

    $(function () {
        var Farmasi = window.Farmasi;
        var esc = Farmasi.ui.escapeHtml;
        var $form = $('#stocks-filter');
        var $tbody = $('#stocks-tbody');
        var $feedback = $('#feedback');
        var $status = $('#status_filter');

        function isExpired(batch) {
            // Server mengirim boolean; fallback aman untuk respons lama.
            return batch.is_expired === true;
        }

        function batchRows(batches) {
            return batches.map(function (batch) {
                var expired = isExpired(batch);
                var badgeClass = expired ? 'text-bg-danger' : 'text-bg-success';
                var badgeText = expired
                    ? Farmasi.text('statusExpired', '')
                    : Farmasi.text('statusAvailable', '');

                return '<tr>' +
                    '<td>' + esc(batch.batch_no) + '</td>' +
                    '<td>' + esc(batch.expires_on === null || batch.expires_on === undefined ? '-' : batch.expires_on) + '</td>' +
                    '<td class="text-end">' + Number(batch.quantity) + '</td>' +
                    '<td><span class="badge ' + badgeClass + '">' + esc(badgeText) + '</span></td>' +
                    '</tr>';
            }).join('');
        }

        function batchesFor(medicine, statusFilter) {
            var available = medicine.available_batches || [];
            var expired = medicine.expired_batches || [];

            if (statusFilter === 'available') {
                return available;
            }

            if (statusFilter === 'expired') {
                return expired;
            }

            return available.concat(expired);
        }

        function batchCell(medicine, statusFilter) {
            var batches = batchesFor(medicine, statusFilter);

            if (batches.length === 0) {
                var empty = statusFilter === 'all'
                    ? Farmasi.text('noBatch', '')
                    : Farmasi.text('filterEmpty', '');

                return '<span class="text-muted">' + esc(empty) + '</span>';
            }

            var count = Farmasi.ui.fill(Farmasi.text('batchCount', ''), [batches.length]);

            return '<details><summary>' + esc(count) + '</summary>' +
                '<table class="table table-sm table-bordered mb-0 mt-2">' +
                '<thead class="table-light"><tr>' +
                '<th>' + esc(Farmasi.text('batchNo', '')) + '</th>' +
                '<th>' + esc(Farmasi.text('expiresOn', '')) + '</th>' +
                '<th class="text-end">' + esc(Farmasi.text('quantity', '')) + '</th>' +
                '<th>' + esc(Farmasi.text('status', '')) + '</th>' +
                '</tr></thead><tbody>' +
                batchRows(batches) +
                '</tbody></table></details>';
        }

        function matchesStatus(medicine, statusFilter) {
            if (statusFilter === 'all') {
                return true;
            }

            return batchesFor(medicine, statusFilter).length > 0;
        }

        function render(report) {
            var medicines = report.medicines || [];
            var statusFilter = $status.val() || 'all';

            $('#on_date').val(report.on_date || '');

            var rows = medicines.filter(function (medicine) {
                return matchesStatus(medicine, statusFilter);
            });

            if (rows.length === 0) {
                var empty = statusFilter === 'all'
                    ? Farmasi.text('noBatch', '')
                    : Farmasi.text('filterEmpty', '');

                $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">' +
                    esc(empty) + '</td></tr>');
                return;
            }

            var html = rows.map(function (medicine) {
                return '<tr>' +
                    '<td>' + esc(medicine.code) + '</td>' +
                    '<td>' + esc(medicine.name) + '</td>' +
                    '<td>' + esc(medicine.unit) + '</td>' +
                    '<td class="text-end">' + Number(medicine.physical_quantity) + '</td>' +
                    '<td class="text-end">' + Number(medicine.available_quantity) + '</td>' +
                    '<td class="text-end">' + Number(medicine.expired_quantity) + '</td>' +
                    '<td>' + batchCell(medicine, statusFilter) + '</td>' +
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

        // Filter status hanya menyaring tampilan; angka tetap dari server.
        $status.on('change', function () {
            load($('#on_date').val());
        });

        load($('#on_date').val() || undefined);
    });
})(jQuery);
