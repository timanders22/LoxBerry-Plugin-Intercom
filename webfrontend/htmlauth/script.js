// Intercom - Oberflaechenskript der beiden Galerien
//
// Die Adressen stehen NICHT fest im Text. Bei einer Zweitinstallation heisst
// der Ordner intercom_01, und dann zeigten alle Aufrufe auf die
// Vorgaengerinstallation. Ordner und Formularmerkmal kommen aus
// Datenattributen, die die aufrufende Seite setzt - das Merkmal gehoert nicht
// in eine mitgelieferte Datei.
//
// Geloescht wird per POST und mit Merkmal. Bis 2.1.13 genuegte ein GET ohne
// jede Pruefung.
(function () {
    function attr(name, vorgabe) {
        var w = document.body ? document.body.getAttribute(name) : null;
        return w ? w : vorgabe;
    }
    var basis = attr('data-ic-admin', '');
    var merkmal = attr('data-ic-merkmal', '');

    document.addEventListener('click', function (e) {
        var a = e.target;
        while (a && a !== document.body && !(a.classList && a.classList.contains('sm-del'))) {
            a = a.parentNode;
        }
        if (!a || a === document.body || !a.classList.contains('sm-del')) { return; }
        e.preventDefault();
        var datei = a.getAttribute('data-datei');
        var art = a.getAttribute('data-art');
        if (!datei || !basis || !merkmal) { return; }
        if (!window.confirm(datei + ' ?')) { return; }

        var daten = 'merkmal=' + encodeURIComponent(merkmal)
                  + '&art=' + encodeURIComponent(art)
                  + '&datei=' + encodeURIComponent(datei);
        var x = new XMLHttpRequest();
        x.open('POST', basis + '/ajax.php', true);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        x.onreadystatechange = function () {
            if (x.readyState !== 4) { return; }
            var ok = false;
            try { ok = JSON.parse(x.responseText).success === true; } catch (err) { ok = false; }
            if (ok) {
                var f = a;
                while (f && f.tagName !== 'FIGURE') { f = f.parentNode; }
                if (f && f.parentNode) { f.parentNode.removeChild(f); }
            } else {
                // Ein Fehlschlag wird GEMELDET. Bis 2.1.13 verschwand die
                // Kachel auch dann, wenn die Datei noch dalag.
                window.alert(x.responseText || 'Fehler beim Loeschen.');
            }
        };
        x.send(daten);
    });
})();
