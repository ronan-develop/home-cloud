import './stimulus_bootstrap.js';
import './styles/app.css';

import './js/modal.js';
import './js/toast.js';
import './js/move-modal.js';
import './js/folder-children.js';
import './js/rename.js';
import './js/delete-folder-modal.js';
import { initUploadModal } from './js/upload-modal.js';

// Initialize upload modal listener
console.log('[app.js] Initializing upload modal');
try {
    initUploadModal();
    console.log('[app.js] ✅ Upload modal initialized');
} catch (err) {
    console.error('[app.js] ❌ Failed to initialize upload modal:', err);
}
