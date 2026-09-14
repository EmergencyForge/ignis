import { registerNavigation } from '@emergencyforge/ui/page-transition.js';
registerNavigation(url => {
    const path = url.pathname.replace(/\/$/, '');
    if (/\/settings\/vehicles\/vehicles$/.test(path)) return { group: 'vehicles', detail: false };
    if (/\/settings\/vehicles\/vehicles\/\d+$/.test(path)) return { group: 'vehicles', detail: true };

    return null;
});
