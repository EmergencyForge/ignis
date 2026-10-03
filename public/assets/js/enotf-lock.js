// Sperrt ein freigegebenes eNOTF-Protokoll in der Ansicht: Textfelder
// werden schreibgeschützt, Auswahllisten, Ankreuzfelder und Radios
// deaktiviert. Die Templates binden das Skript nur bei freigegebenen
// Protokollen ein, und zwar nach den Feldern, damit sie schon im DOM
// stehen. Der Server lehnt Änderungen ohnehin ab (save-fields, 403).
(function () {
    document.querySelectorAll('input, textarea').forEach(function (element) {
        element.setAttribute('readonly', 'readonly');
    });
    document.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(function (element) {
        element.setAttribute('disabled', 'disabled');
    });
})();
