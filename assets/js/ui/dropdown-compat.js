/**
 * ignis UI — Dropdown aus dem gemeinsamen Paket @emergencyforge/ui, dazu
 * der Alias aus dem alten enotf-custom-dropdown.js: eNOTF (Login,
 * Medikamente) und die fireTab-Formulare rufen eNOTFCustomDropdown.init()
 * und eNOTFCustomDropdown.refresh(select), nachdem sie Optionen per JS
 * geändert haben.
 *
 * Der UI-Pass in vite.config.js baut diese Datei als
 * public/assets/js/ui/dropdown.js; eNOTF bindet genau diesen Pfad ein.
 * window.Dropdown und window.ignisDropdownInit setzt das Paket selbst.
 */
import Dropdown, { getDropdown, initAll } from '@emergencyforge/ui/dropdown.js';

window.eNOTFCustomDropdown = {
    init: () => initAll(),
    refresh: (selectEl) => {
        if (!selectEl) return;
        const inst = getDropdown(selectEl);
        if (inst) inst.refresh();
        else new Dropdown(selectEl);
    },
};

export { Dropdown, getDropdown, initAll };
export default Dropdown;
