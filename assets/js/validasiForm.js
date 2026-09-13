document.addEventListener("DOMContentLoaded", function () {
  // Catatan: field #nomor sudah dihapus dari form tambah publikasi
  // (page09C.php) karena nomor urut sekarang otomatis dari AUTO_INCREMENT
  // database, jadi listener pembatas input angka untuk field itu juga
  // tidak diperlukan lagi di sini.

  var inputJudul = document.getElementById("judul");
  if (inputJudul) {
    var specialChars = /[<>{}[\]|!@#$%^&*()+=?:"';`~\\]/;

    inputJudul.addEventListener("keydown", function (e) {
      var navigationKeys = [
        "Backspace",
        "Delete",
        "Tab",
        "ArrowLeft",
        "ArrowRight",
        "ArrowUp",
        "ArrowDown",
        "Home",
        "End",
        "Enter",
      ];
      if (navigationKeys.indexOf(e.key) !== -1) return;
      if (e.ctrlKey || e.metaKey) return;

      if (specialChars.test(e.key)) {
        e.preventDefault();
      }
    });

    inputJudul.addEventListener("input", function () {
      this.value = this.value.replace(/[<>{}[\]|!@#$%^&*()+=?:"';`~\\]/g, "");
    });
  }

  var navLinks = document.querySelectorAll("nav a");
  for (var i = 0; i < navLinks.length; i++) {
    var link = navLinks[i];
    if (link.getAttribute("href") === "#") {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        alert("Halaman ini belum tersedia.\nWeb masih dalam pengembangan.");
      });
    }
  }
});

function validate06C() {
  var judul = document.formTambahPublikasi.judul.value.trim();
  var tanggal = document.formTambahPublikasi.tanggal.value.trim();
  var sampul = document.formTambahPublikasi.sampul;

  var pesanError = document.getElementById("pesanError");
  var pesan = "";

  if (judul === "") {
    pesan += "Judul tidak boleh kosong.<br>";
  } else if (/[<>{}[\]|!@#$%^&*()+=?:"';`~\\]/.test(judul)) {
    pesan += "Terdapat karakter yang tidak valid pada judul.<br>";
  }

  if (tanggal === "") {
    pesan += "Tanggal rilis tidak boleh kosong.<br>";
  }

  // Sampul bersifat opsional: boleh kosong (server menyimpan tanpa sampul).
  // (Hidden field sampul_otomatis peninggalan fitur lama "Tarik Data BPS"
  //  dicek kalau masih ada, supaya validasi tetap kompatibel.)
  var sampulOtomatisEl = document.getElementById("sampul_otomatis");
  var adaSampulOtomatis = sampulOtomatisEl && sampulOtomatisEl.value !== "";

  if (sampul && sampul.files.length > 0) {
    var filePath = sampul.value;
    var allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif|\.webp)$/i;

    if (!allowedExtensions.exec(filePath)) {
      pesan +=
        "Format file sampul tidak didukung. Harap unggah file gambar (.jpg, .jpeg, .png, .gif, .webp).<br>";
    }
  } else if (!adaSampulOtomatis) {
    // Tidak upload manual & tidak ada hasil tarik data BPS - tetap boleh
    // lanjut (sampul opsional), tidak perlu pesan error di sini.
  }

  if (pesan !== "") {
    var pesanSuksesEl = document.getElementById("pesanSukses");
    if (pesanSuksesEl) {
      clearTimeout(pesanSuksesEl._hideTimer);
      pesanSuksesEl.style.display = "none";
      pesanSuksesEl.style.opacity = "1";
      pesanSuksesEl.style.transition = "";
    }

    pesanError.innerHTML = pesan;
    pesanError.style.display = "block";
    pesanError.style.opacity = "1";
    clearTimeout(pesanError._hideTimer);
    pesanError._hideTimer = setTimeout(function () {
      pesanError.style.transition = "opacity 0.6s";
      pesanError.style.opacity = "0";
      setTimeout(function () {
        pesanError.style.display = "none";
        pesanError.style.transition = "";
        pesanError.style.opacity = "1";
      }, 600);
    }, 4000);
    return false;
  } else {
    pesanError.innerHTML = "";
    pesanError.style.display = "none";

    var pesanSukses = document.getElementById("pesanSukses");
    if (pesanSukses) {
      pesanSukses.textContent = "";
      var teksAwal = document.createTextNode("Publikasi \u201C");
      var elStrong = document.createElement("strong");
      elStrong.textContent = judul;
      var teksSufiks = document.createTextNode("\u201D berhasil ditambahkan!");
      pesanSukses.appendChild(teksAwal);
      pesanSukses.appendChild(elStrong);
      pesanSukses.appendChild(teksSufiks);
      pesanSukses.style.display = "block";
      pesanSukses.style.opacity = "1";
      clearTimeout(pesanSukses._hideTimer);
      pesanSukses._hideTimer = setTimeout(function () {
        pesanSukses.style.transition = "opacity 0.6s";
        pesanSukses.style.opacity = "0";
        setTimeout(function () {
          pesanSukses.style.display = "none";
          pesanSukses.style.transition = "";
          pesanSukses.style.opacity = "1";
        }, 600);
      }, 4000);
    }

    return true;
  }
}

// Validasi Form Edit Publikasi (page09E.php)

function validate09E() {
  var judul   = document.formEditPublikasi.judul.value.trim();
  var tanggal = document.formEditPublikasi.tanggal.value.trim();
  var sampulBaru = document.formEditPublikasi.sampul_baru;

  var pesanError = document.getElementById("pesanError");
  var pesan = "";

  if (judul === "") {
    pesan += "Judul tidak boleh kosong.<br>";
  } else if (/[<>{}[\]|!@#$%^&*()+=?:"';`~\\]/.test(judul)) {
    pesan += "Terdapat karakter yang tidak valid pada judul.<br>";
  }

  if (tanggal === "") {
    pesan += "Tanggal rilis tidak boleh kosong.<br>";
  }

  // Validasi ekstensi jika ada file baru yang dipilih
  if (sampulBaru && sampulBaru.files.length > 0) {
    var filePath = sampulBaru.value;
    var allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif|\.webp)$/i;
    if (!allowedExtensions.exec(filePath)) {
      pesan += "Format file sampul tidak didukung. Harap unggah file gambar (.jpg, .jpeg, .png, .gif, .webp).<br>";
    }
  }

  if (pesan !== "") {
    pesanError.innerHTML = pesan;
    pesanError.style.display = "block";
    pesanError.style.opacity = "1";
    clearTimeout(pesanError._hideTimer);
    pesanError._hideTimer = setTimeout(function () {
      pesanError.style.transition = "opacity 0.6s";
      pesanError.style.opacity = "0";
      setTimeout(function () {
        pesanError.style.display = "none";
        pesanError.style.transition = "";
        pesanError.style.opacity = "1";
      }, 600);
    }, 4000);
    return false;
  }

  return true;
}