(function () {
    const data = window.RECEPTION_DATA;
    const rows = document.getElementById('item-rows');
    const feedback = document.getElementById('feedback');
    const form = document.getElementById('reception-form');
    const i18n = data.i18n || {};

    function medicineOptions(selectedId) {
        return data.medicines.map(function (medicine) {
            const selected = Number(selectedId) === medicine.id ? ' selected' : '';
            return '<option value="' + medicine.id + '"' + selected + '>' + escapeHtml(medicine.name) + '</option>';
        }).join('');
    }

    function addRow(item) {
        const row = document.createElement('tr');
        item = item || {};

        const medicineCell = document.createElement('td');
        medicineCell.innerHTML = '<select class="medicine form-select">' + medicineOptions(item.medicine_id) + '</select>';

        const batchCell = document.createElement('td');
        const batchInput = document.createElement('input');
        batchInput.type = 'text';
        batchInput.className = 'batch form-control';
        batchInput.value = item.batch_no || '';
        batchCell.appendChild(batchInput);

        const expiresCell = document.createElement('td');
        const expiresInput = document.createElement('input');
        expiresInput.type = 'date';
        expiresInput.className = 'expires form-control';
        expiresInput.value = item.expires_on || '';
        expiresCell.appendChild(expiresInput);

        const qtyCell = document.createElement('td');
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'qty form-control text-end';
        qtyInput.min = '1';
        qtyInput.step = '1';
        qtyInput.value = item.quantity || 1;
        qtyCell.appendChild(qtyInput);

        const actionCell = document.createElement('td');
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-sm btn-link text-danger remove p-0';
        removeButton.textContent = i18n.remove || 'remove';
        actionCell.appendChild(removeButton);

        row.append(medicineCell, batchCell, expiresCell, qtyCell, actionCell);

        removeButton.addEventListener('click', function () {
            row.remove();
        });

        rows.appendChild(row);
    }

    function collectItems() {
        return Array.from(rows.querySelectorAll('tr')).map(function (row) {
            return {
                medicine_id: Number(row.querySelector('.medicine').value),
                batch_no: row.querySelector('.batch').value.trim(),
                expires_on: row.querySelector('.expires').value,
                quantity: Number(row.querySelector('.qty').value)
            };
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showErrors(messages) {
        feedback.innerHTML = '<div class="alert alert-danger"><strong>' +
            escapeHtml(i18n.saveFailed || '') + '</strong><ul class="mb-0">' +
            messages.map(function (message) { return '<li>' + escapeHtml(message) + '</li>'; }).join('') +
            '</ul></div>';
        window.scrollTo(0, 0);
    }

    function showSuccess(message) {
        feedback.innerHTML = '<div class="alert alert-success">' + escapeHtml(message) + '</div>';
        window.scrollTo(0, 0);
    }

    const CSRF_HEADER = 'X-CSRF-TOKEN';

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function storeFreshToken(response) {
        const fresh = response.headers.get(CSRF_HEADER);
        const meta = document.querySelector('meta[name="csrf-token"]');

        if (fresh && meta) {
            meta.content = fresh;
        }

        return fresh;
    }

    function send(url, method, payload, isRetry) {
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            const fresh = storeFreshToken(response);

            return response.json().then(function (body) {
                if (response.status === 403 && body.error === 'csrf' && !isRetry && fresh) {
                    return send(url, method, payload, true);
                }

                return { status: response.status, body: body };
            });
        });
    }

    document.getElementById('add-row').addEventListener('click', function () { addRow(); });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const payload = {
            reference_no: document.getElementById('reference_no').value.trim(),
            supplier_id: Number(document.getElementById('supplier_id').value),
            received_at: document.getElementById('received_at').value,
            items: collectItems()
        };

        const url = data.id === null ? data.endpoints.create : data.endpoints.update + data.id;
        const method = data.id === null ? 'POST' : 'PUT';

        send(url, method, payload, false).then(function (result) {
            if (result.status >= 200 && result.status < 300) {
                showSuccess(i18n.savedRedirect || '');
                window.setTimeout(function () { window.location.href = data.redirectUrl; }, 800);
                return;
            }
            showErrors(result.body.errors || [result.body.message || (i18n.saveFailed || '')]);
        }).catch(function () {
            showErrors([i18n.contactFailed || '']);
        });
    });

    if (data.items.length > 0) {
        data.items.forEach(addRow);
    } else {
        addRow();
    }
})();
