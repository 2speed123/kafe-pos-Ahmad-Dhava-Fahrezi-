// Form transaksi: baris item dinamis dan kalkulasi otomatis.
// Perhitungan final tetap dilakukan ulang oleh server saat data disimpan.
(function () {
  var POS = window.POS;
  var rowsEl = document.getElementById('itemRows');
  var form = document.getElementById('formTransaksi');
  var $ = function (id) { return document.getElementById(id); };
  var rp = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };

  function buildOptions(selected) {
    var html = '<option value="">Pilih menu</option>';
    var group = null;
    POS.produk.forEach(function (p) {
      if (p.kat !== group) {
        if (group !== null) html += '</optgroup>';
        html += '<optgroup label="' + p.kat + '">';
        group = p.kat;
      }
      var habis = p.stok <= 0;
      html += '<option value="' + p.id + '"' + (habis ? ' disabled' : '') + (p.id === selected ? ' selected' : '') + '>' +
        p.nama + ' - ' + rp(p.harga) + ' (stok ' + p.stok + ')</option>';
    });
    return html + (group !== null ? '</optgroup>' : '');
  }

  function addRow(item) {
    item = item || {};
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><select name="product_id[]" class="f-prod">' + buildOptions(item.pid) + '</select></td>' +
      '<td><input type="number" name="qty[]" class="f-qty" min="1" value="' + (item.qty || 1) + '"></td>' +
      '<td><input type="text" name="catatan[]" maxlength="150" placeholder="Contoh: less sugar" value=""></td>' +
      '<td class="num f-sub">Rp 0</td>' +
      '<td><button type="button" class="btn btn-light btn-sm f-del" aria-label="Hapus baris">Hapus</button></td>';
    tr.querySelector('input[name="catatan[]"]').value = item.catatan || '';
    rowsEl.appendChild(tr);
    hitung();
  }

  function produkById(id) {
    for (var i = 0; i < POS.produk.length; i++) if (POS.produk[i].id === id) return POS.produk[i];
    return null;
  }

  function hitung() {
    var subtotal = 0;
    var terpakai = {};
    var stokBerlebih = false;
    rowsEl.querySelectorAll('tr').forEach(function (tr) {
      var p = produkById(parseInt(tr.querySelector('.f-prod').value, 10));
      var qty = Math.max(0, parseInt(tr.querySelector('.f-qty').value, 10) || 0);
      var sub = p ? p.harga * qty : 0;
      if (p) {
        terpakai[p.id] = (terpakai[p.id] || 0) + qty;
        var over = terpakai[p.id] > p.stok;
        tr.querySelector('.f-qty').style.borderColor = over ? '#b3261e' : '';
        if (over) stokBerlebih = true;
      }
      tr.querySelector('.f-sub').textContent = rp(sub);
      subtotal += sub;
    });

    var dn = Math.max(0, parseFloat($('diskonNilai').value) || 0);
    var diskon = $('diskonTipe').value === 'persen' ? Math.round(subtotal * Math.min(dn, 100) / 100) : Math.min(dn, subtotal);
    var pajak = Math.round((subtotal - diskon) * POS.pb1);
    var grand = subtotal - diskon + pajak;

    $('sumSubtotal').textContent = rp(subtotal);
    $('sumDiskon').textContent = '- ' + rp(diskon);
    $('sumPajak').textContent = rp(pajak);
    $('sumGrand').textContent = rp(grand);

    var tunai = $('metode').value === 'Tunai';
    $('boxBayar').style.display = tunai ? '' : 'none';
    var bayar = parseFloat($('bayar').value) || 0;
    var kurang = tunai && bayar < grand;
    $('sumKembali').textContent = rp(tunai && !kurang ? bayar - grand : 0);
    $('errBayar').hidden = !(tunai && $('bayar').value !== '' && kurang);
    form._state = { grand: grand, kurang: kurang, tunai: tunai, kosong: subtotal <= 0, stokBerlebih: stokBerlebih };
  }

  function aturTipe() {
    var dine = $('tipe').value === 'Dine-In';
    $('boxMeja').style.display = dine ? '' : 'none';
    if (!dine) $('meja').value = '';
  }

  $('btnTambah').addEventListener('click', function () { addRow(); });
  rowsEl.addEventListener('click', function (e) {
    if (e.target.classList.contains('f-del')) {
      e.target.closest('tr').remove();
      if (!rowsEl.children.length) addRow();
      hitung();
    }
  });
  form.addEventListener('input', hitung);
  form.addEventListener('change', hitung);
  $('tipe').addEventListener('change', aturTipe);

  form.addEventListener('submit', function (e) {
    var s = form._state;
    var msg = '';
    if (s.kosong) msg = 'Tambahkan minimal satu item pesanan.';
    else if ($('tipe').value === 'Dine-In' && !$('meja').value) msg = 'Pilih nomor meja untuk pesanan Dine-In.';
    else if (s.stokBerlebih) msg = 'Kuantiti melebihi stok yang tersedia. Periksa kolom Qty yang ditandai merah.';
    else if (s.tunai && s.kurang) msg = 'Uang yang dibayarkan kurang dari total tagihan.';
    if (msg) { e.preventDefault(); window.alert(msg); return; }
    $('btnSimpan').disabled = true;
    $('btnSimpan').textContent = 'Menyimpan...';
  });

  (POS.old.length ? POS.old : [{}]).forEach(addRow);
  aturTipe();
  hitung();
})();
