/**
 * ignis UI — Dialog aus dem gemeinsamen Paket @emergencyforge/ui, dazu die
 * Aliase aus dem alten assets/js/dialogs.js, die Inline-Scripts von ignis
 * noch rufen (window.intraConfirm in templates/users/edit.php).
 *
 * Der UI-Pass in vite.config.js baut diese Datei als
 * public/assets/js/ui/dialog.js; jede Seite, die dialog.js einbindet,
 * bekommt die Aliase mit. Das Paket setzt window.Dialog, showConfirm,
 * showAlert und showPrompt selbst.
 */
import Dialog from '@emergencyforge/ui/dialog.js';

window.intraConfirm = window.showConfirm;
window.intraAlert   = window.showAlert;
window.intraPrompt  = window.showPrompt;

export { Dialog };
export default Dialog;
