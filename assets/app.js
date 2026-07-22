import './stimulus_bootstrap.js';
import './styles/app.css';

import './js/modal.js';
import './js/toast.js';
import './js/move-modal.js';
import './js/folder-children.js';
import './js/rename.js';
import './js/delete-folder-modal.js';
import './js/pdf-viewer-modal.js';
import './js/media-viewer-modal.js';
import { initUploadModal } from './js/upload-modal.js';
import { initExplorerDrop } from './js/explorer-drop.js';

initUploadModal();
initExplorerDrop();
