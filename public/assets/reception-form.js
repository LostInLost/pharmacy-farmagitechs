(function () {
    const data = window.RECEPTION_DATA;
    const rows = document.getElementById('item-rows');
    const feedback = document.getElementById('feedback');
    const form = document.getElementById('reception-form');

    function medicineOptions(selectedId) {
        return data.medicines.map(function (medicine) {
            const selected = Number(selectedId) === medicine.id ? ' selected' : '';
            return '<option value="' + medicine.id + '"' + selected + '>' + medicine.name + '</option>';
        }).join('');
    }

    function addRow(item) {
        const row = document.createElement('tr');
        item = item || {};
        row.innerHTML =
            '<td><select class="medicine">' + medicineOptions(item.medicine_id) + '</select></td>' +
            '<td><input type="text" class="batch" value="' + (item.batch_no || '') + '"></td>' +
            '<td><input type="date" class="expires" value="' + (item.expires_on || '') + '"></td>' +
            '<td class="num"><input type="number" class="qty" min="1" step="1" value="' + (item.quantity || 1) + '"></td>' +
            '<td><button type="button" class="link remove">hapus</button></td>';

        row.querySelector('.remove').addEventListener('click', function () {
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

    function showErrors(messages) {
        feedback.innerHTML = '<div class="alert error"><strong>Gagal menyimpan.</strong><ul>' +
            messages.map(function (message) { return '<li>' + message + '</li>'; }).join('') +
            '</ul></div>';
        window.scrollTo(0, 0);
    }

    function showSuccess(message) {
        feedback.innerHTML = '<div class="alert ok">' + message + '</div>';
        window.scrollTo(0, 0);
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

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (body) {
                return { status: response.status, body: body };
            });
        }).then(function (result) {
            if (result.status >= 200 && result.status < 300) {
                showSuccess('Tersimpan. Mengalihkan ke daftar penerimaan...');
                window.setTimeout(function () { window.location.href = '<?= site_url('receptions') ?>'; }, 800);
                return;
            }
            showErrors(result.body.errors || [result.body.message || 'Terjadi kesalahan.']);
        }).catch(function () {
            showErrors(['Tidak dapat menghubungi server.']);
        });
    });

    if (data.items.length > 0) {
        data.items.forEach(addRow);
    } else {
        addRow();
    }
})();
