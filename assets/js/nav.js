/**
 * nav.js Navigasi responsif untuk seluruh halaman.
 *
 * Otomatis menyuntikkan tombol hamburger () ke dalam <header>.
 * Di layar kecil (<= 900px, lihat myCSS2.css) nav berubah menjadi
 *   drawer geser dari kanan; tombol hamburger toggle buka/tutup.
 * Dropdown "Produk" di HP/Tablet bekerja dengan tap (accordion),
 *   di desktop tetap dengan hover (CSS).
 * Menutup drawer saat: link diklik, klik di luar, tombol Escape.
 *
 * Cara pakai: tambahkan di <head> setiap halaman (sesuaikan path):
 *   <script src="assets/js/nav.js" defer></script>               (root)
 *   <script src="../../assets/js/nav.js" defer></script>         (modules/*)
 */
(function () {
  'use strict';

  // Jalan setelah DOM siap (mendukung atribut defer maupun tidak).
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  ready(function () {
    var header = document.querySelector('header');
    var nav = document.querySelector('header nav');
    if (!header || !nav) return;

    // 1. Suntikkan tombol hamburger kalau belum ada.
    var toggle = header.querySelector('.nav-toggle');
    if (!toggle) {
      toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'nav-toggle';
      toggle.setAttribute('aria-label', 'Buka menu navigasi');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-controls', 'navigasi-utama');
      // Tiga garis dibuat dari span agar mudah dianimasikan jadi "X".
      toggle.innerHTML = '<span></span><span></span><span></span>';
      header.appendChild(toggle);
    }
    nav.id = 'navigasi-utama';

    function isMobileView() {
      // Sinkron dengan breakpoint CSS (max-width: 900px).
      return window.matchMedia('(max-width: 900px)').matches;
    }

    function setOpen(open) {
      var willOpen = typeof open === 'boolean'
        ? open
        : !nav.classList.contains('open');
      nav.classList.toggle('open', willOpen);
      toggle.classList.toggle('active', willOpen);
      toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      toggle.setAttribute(
        'aria-label',
        willOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'
      );
      // Kunci scroll halaman saat drawer terbuka di HP.
      document.body.classList.toggle('nav-open', willOpen && isMobileView());
      if (!willOpen) {
        // Tutup juga accordion dropdown yang sedang terbuka.
        nav.querySelectorAll('.nav-dropdown.open').forEach(function (dd) {
          dd.classList.remove('open');
        });
      }
    }

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen();
    });

    // 2. Dropdown jadi accordion khusus di tampilan mobile.
    nav.querySelectorAll('.nav-dropdown > .nav-dropbtn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (!isMobileView()) return; // desktop: biarkan hover CSS
        e.preventDefault();
        e.stopPropagation();
        var dd = btn.closest('.nav-dropdown');
        var wasOpen = dd.classList.contains('open');
        nav.querySelectorAll('.nav-dropdown.open').forEach(function (other) {
          other.classList.remove('open');
        });
        dd.classList.toggle('open', !wasOpen);
      });
    });

    // 3. Klik link di dalam drawer tutup drawer.
    nav.addEventListener('click', function (e) {
      var link = e.target.closest('a');
      if (link && isMobileView()) setOpen(false);
    });

    // 4. Klik di luar drawer tutup.
    document.addEventListener('click', function (e) {
      if (
        nav.classList.contains('open') &&
        !nav.contains(e.target) &&
        !toggle.contains(e.target)
      ) {
        setOpen(false);
      }
    });

    // 5. Tombol Escape tutup.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('open')) {
        setOpen(false);
        toggle.focus();
      }
    });

    // 6. Kalau jendela dibesarkan ke desktop, pastikan drawer tertutup.
    window.addEventListener('resize', function () {
      if (!isMobileView() && nav.classList.contains('open')) setOpen(false);
    });
  });
})();
