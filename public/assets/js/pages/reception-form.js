/* global jQuery */
(function ($) {
    'use strict';

    $(function () {
        var Farmasi = window.Farmasi;
        var esc = Farmasi.ui.escapeHtml;
        var boot = Farmasi.boot;
        var $form = $('#reception-form');
        var $feedback = $('#feedback');
        var $rows = $('#item-rows');
        var $supplier = $('#supplier_id');
        var medicines = [];

        function medicineOptions(selectedId) {
            return medicines.map(function (medicine) {
                var selected = Number(selectedId) === medicine.id ? ' selected' : '';

                return '<option value="' + medicine.id + '"' + selected + '>' +
                    esc(medicine.name) + '</option>';
            }).join('');
        }

        function addRow(item) {
            item = item || {};

            var $row = $('<tr>' +
                '<td><select class="medicine form-select">' + medicineOptions(item.medicine_id) + '</select></td>' +
                '<td><input type="text" class="batch form-control"></td>' +
                '<td><input type="date" class="expires form-control"></td>' +
                '<td><input type="number" class="qty form-control text-end" min="1" step="1"></td>' +
                '<td><button type="button" class="btn btn-sm btn-link text-danger remove p-0">' +
                esc(Farmasi.text('remove', '')) + '</button></td>' +
                '</tr>');

            $row.find('.batch').val(item.batch_no || '');
            $row.find('.expires').val(item.expires_on || '');
            $row.find('.qty').val(item.quantity || 1);

            $row.find('.remove').on('click', function () {
                $row.remove();
            });

            $rows.append($row);
        }

        function collectItems() {
            return $rows.find('tr').map(function () {
                var $row = $(this);

                return {
                    medicine_id: Number($row.find('.medicine').val()),
                    batch_no: String($row.find('.batch').val()).trim(),
                    expires_on: $row.find('.expires').val(),
                    quantity: Number($row.find('.qty').val())
                };
            }).get();
        }

        function toDatetimeLocal(value) {
            if (! value) {
                return '';
            }

            return String(value).slice(0, 16).replace(' ', 'T');
        }

        function fillHeader(reception) {
            $('#reference_no').val(reception.reference_no || '');
            $('#supplier_id').val(String(reception.supplier_id || ''));
            $('#received_at').val(toDatetimeLocal(reception.received_at));
        }

        // Nilai audit_logs.action adalah kunci i18n itu sendiri
        // (mis. Audit.receptions.action.create). Kunci tak dikenal tetap
        // tampil apa adanya agar log lama tidak hilang dari tampilan.
        function actionLabel(action) {
            return Farmasi.text(action, action);
        }

        function logSummary(log) {
            if (log.data_before === null) {
                return Farmasi.text('logCreated', '');
            }

            var before = JSON.stringify(log.data_before);
            var after = JSON.stringify(log.data_after);

            return before === after
                ? Farmasi.text('logUnchanged', '')
                : Farmasi.text('logChanged', '');
        }

        function renderLogs(logs) {
            var $section = $('#log-section');
            var $tbody = $('#log-tbody');

            if (! logs || logs.length === 0) {
                $section.hide();
                return;
            }

            var html = logs.map(function (log) {
                var changes = '-';

                if (log.data_before !== null || log.data_after !== null) {
                    changes = '<details><summary>' + esc(logSummary(log)) + '</summary>' +
                        '<pre class="bg-light p-2 rounded small mb-0">' +
                        esc(Farmasi.text('logBefore', '')) + ': ' +
                        esc(JSON.stringify(log.data_before, null, 2)) + '\n\n' +
                        esc(Farmasi.text('logAfter', '')) + ': ' +
                        esc(JSON.stringify(log.data_after, null, 2)) +
                        '</pre></details>';
                }

                return '<tr>' +
                    '<td>' + esc(log.created_at) + '</td>' +
                    '<td>' + esc(actionLabel(log.action)) + '</td>' +
                    '<td>' + esc(log.actor_name === null || log.actor_name === undefined ? '-' : log.actor_name) + '</td>' +
                    '<td>' + changes + '</td>' +
                    '</tr>';
            }).join('');

            $tbody.html(html);
            $section.show();
        }

        function disableForm() {
            $form.find('input, select, button[type="submit"], #add-row').prop('disabled', true);
        }

        function fail(message, backUrl) {
            disableForm();
            Farmasi.ui.error($feedback, '', [message]);

            if (backUrl) {
                $feedback.append('<p class="mt-2"><a href="' + esc(backUrl) + '">' +
                    esc(Farmasi.text('back', '')) + '</a></p>');
            }
        }

        function loadReferences(selectedSupplierId) {
            return $.when(
                Farmasi.api.references.suppliers(),
                Farmasi.api.references.medicines()
            ).then(function (supplierResult, medicineResult) {
                var suppliers = (supplierResult.body && supplierResult.body.data) || [];
                medicines = (medicineResult.body && medicineResult.body.data) || [];

                var options = '<option value="">' + esc(Farmasi.text('choose', '')) + '</option>' +
                    suppliers.map(function (supplier) {
                        var selected = Number(selectedSupplierId) === supplier.id ? ' selected' : '';

                        return '<option value="' + supplier.id + '"' + selected + '>' +
                            esc(supplier.name) + '</option>';
                    }).join('');

                $supplier.html(options);
            });
        }

        function loadExisting(id) {
            return Farmasi.api.receipts.show(id).then(function (result) {
                var reception = result.body.data;

                if (! reception.can_update) {
                    fail(Farmasi.text('forbidden', ''), boot.redirectUrl);
                    return $.Deferred().reject({ forbidden: true }).promise();
                }

                fillHeader(reception);
                (reception.items || []).forEach(addRow);
                renderLogs(reception.logs);

                return loadReferences(reception.supplier_id);
            });
        }

        $('#add-row').on('click', function () {
            addRow();
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            $feedback.empty();

            var payload = {
                reference_no: String($('#reference_no').val()).trim(),
                supplier_id: Number($('#supplier_id').val()),
                received_at: $('#received_at').val(),
                items: collectItems()
            };

            var saving = boot.receptionId === null
                ? Farmasi.api.receipts.create(payload)
                : Farmasi.api.receipts.update(boot.receptionId, payload);

            saving.then(
                function () {
                    Farmasi.ui.success($feedback, Farmasi.text('savedRedirect', ''));
                    window.setTimeout(function () {
                        Farmasi.redirect(boot.redirectUrl);
                    }, 800);
                },
                function (rejection) {
                    if (rejection.forbidden || rejection.status === 401) {
                        return;
                    }

                    var messages = (rejection.body && rejection.body.errors) ||
                        [(rejection.body && rejection.body.message) || Farmasi.text('saveFailed', '')];

                    Farmasi.ui.error($feedback, Farmasi.text('saveFailed', ''), messages);
                }
            );
        });

        var ready = loadReferences(null);

        if (boot.receptionId === null) {
            ready.then(function () {
                addRow();
            });
        } else {
            ready = loadExisting(boot.receptionId);
        }

        ready.then(null, function (rejection) {
            if (! rejection || (! rejection.forbidden && rejection.status !== 401)) {
                var message = (rejection && rejection.body && rejection.body.message) ||
                    Farmasi.text('loadFailed', '');

                fail(message, boot.redirectUrl);
            }
        });
    });
})(jQuery);
