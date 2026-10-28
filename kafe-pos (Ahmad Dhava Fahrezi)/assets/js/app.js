// Perilaku umum: menu mobile, penutup notifikasi, konfirmasi aksi.
(function () {
  var toggle = document.getElementById('menuToggle');
  var side = document.getElementById('sidebar');
  if (toggle && side) {
    toggle.addEventListener('click', function () { side.classList.toggle('open'); });
    document.addEventListener('click', function (e) {
      if (side.classList.contains('open') && !side.contains(e.target) && e.target !== toggle) side.classList.remove('open');
    });
  }

  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('alert-close')) e.target.parentNode.remove();
    var link = e.target.closest('a[data-confirm]');
    if (link && !window.confirm(link.getAttribute('data-confirm'))) e.preventDefault();
  });

  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Notifikasi sukses menghilang otomatis.
  setTimeout(function () {
    document.querySelectorAll('.alert-success').forEach(function (a) { a.remove(); });
  }, 6000);
})();
