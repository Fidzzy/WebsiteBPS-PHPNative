function showHint(str) {
    var hintBox = document.getElementById("txtHint");

    if (str.length == 0) {
        hintBox.innerHTML = "";
        return;
    }

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var response = JSON.parse(this.responseText);

            // Bangun elemen DOM lewat createElement/textContent (bukan
            // innerHTML dengan string ditempel manual) supaya judul yang
            // datang dari database tidak pernah ditafsirkan sebagai HTML.
            // Sebelumnya ada dua bug: (1) judul dimasukkan mentah-mentah ke
            // innerHTML tanpa escaping sama sekali, dan (2) fungsi "escape"
            // untuk tanda kutip di onclick ternyata tidak melakukan apa-apa
            // (".replace(/'/g, \"\\'\")" di JS cuma menghasilkan kutip biasa
            // lagi, bukan versi ter-escape) keduanya membuka celah XSS
            // tersimpan lewat judul publikasi.
            hintBox.innerHTML = "";

            if (response.length > 0 && response[0].judul !== 'no suggestion') {
                response.forEach(function(item) {
                    var row = document.createElement('div');
                    row.style.cursor = 'pointer';
                    row.style.padding = '3px 0';
                    row.style.borderBottom = '1px solid #eee';
                    row.textContent = item.judul; // textContent = aman, tidak di-render sebagai HTML
                    row.addEventListener('click', function() {
                        document.getElementById('q').value = item.judul;
                        hintBox.innerHTML = '';
                    });
                    hintBox.appendChild(row);
                });
            } else {
                var noResult = document.createElement('span');
                noResult.style.color = 'red';
                noResult.textContent = 'Tidak ada saran';
                hintBox.appendChild(noResult);
            }
        }
    };
    xhttp.open("GET", "page11A_gethint.php?keyword=" + encodeURIComponent(str), true);
    xhttp.send();
}
