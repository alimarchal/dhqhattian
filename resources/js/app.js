import './bootstrap';

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// Livewire 3 bundles and starts its own Alpine (with the focus plugin). Starting a second copy
// logs "Detected multiple instances of Alpine running", so ours only starts on pages without Livewire
// (for example the login page).
if (!window.Livewire) {
    window.Alpine = Alpine;
    Alpine.plugin(focus);
    Alpine.start();
}
