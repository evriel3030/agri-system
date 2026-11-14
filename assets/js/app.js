// assets/js/app.js - small helpers used on every page

document.addEventListener('DOMContentLoaded', function () {

    // ---- Delete confirmation dialog ----
    var modalEl = document.getElementById('deleteModal');
    if (modalEl && window.bootstrap) {
        var modal = new bootstrap.Modal(modalEl);
        document.querySelectorAll('.js-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('deleteForm').action = btn.dataset.action;
                document.getElementById('deleteName').textContent = btn.dataset.name || 'this record';
                modal.show();
            });
        });
    }

    // ---- Planting form: suggest the expected harvest date ----
    // = planting date + the crop's growing period (user can still change it)
    var crop = document.getElementById('crop_id');
    var planted = document.getElementById('planting_date');
    var expected = document.getElementById('expected_harvest_date');
    if (crop && planted && expected) {
        var touched = expected.value !== '';
        expected.addEventListener('input', function () { touched = true; });
        var suggest = function () {
            var opt = crop.options[crop.selectedIndex];
            var days = opt ? parseInt(opt.getAttribute('data-days'), 10) : NaN;
            if (touched || !planted.value || isNaN(days)) { return; }
            var d = new Date(planted.value + 'T00:00:00');
            d.setDate(d.getDate() + days);
            var m = ('0' + (d.getMonth() + 1)).slice(-2);
            var day = ('0' + d.getDate()).slice(-2);
            expected.value = d.getFullYear() + '-' + m + '-' + day;
        };
        crop.addEventListener('change', suggest);
        planted.addEventListener('change', suggest);
    }

    // ---- Harvest form: live estimated value ----
    var qty = document.getElementById('quantity_kg');
    var price = document.getElementById('selling_price_per_kg');
    var out = document.getElementById('estimated_value');
    if (qty && price && out) {
        var calc = function () {
            var v = parseFloat(qty.value) * parseFloat(price.value);
            out.textContent = isNaN(v) ? '—' : '₱' + v.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        };
        qty.addEventListener('input', calc);
        price.addEventListener('input', calc);
        calc();
    }
});
