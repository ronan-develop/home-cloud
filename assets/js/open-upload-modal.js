import { openModal } from '../services/ModalFactory.js';

/**
 * Fetch folders from the existing API and open the upload modal with them.
 * Uses the already-tested API endpoint /api/v1/folders.
 *
 * options:
 *  - title
 *  - files (optional initial files array)
 *  - currentFolderId
 */
export async function openUploadModal(options = {}) {
  const endpoint = options.endpoint || '/api/v1/folders';

  const res = await fetch(endpoint, { credentials: 'same-origin' });
  if (!res.ok) throw new Error(`Failed to fetch folders: ${res.status}`);

  const json = await res.json();
  // Support different API shapes (array, hydra:member, items)
  let folders = [];
  if (Array.isArray(json)) folders = json;
  else if (Array.isArray(json['hydra:member'])) folders = json['hydra:member'];
  else if (Array.isArray(json.items)) folders = json.items;
  else if (Array.isArray(json.data)) folders = json.data;

  const files = options.files || [];

  const instance = await openModal('hc-upload-form', {
    title: options.title || 'Upload',
    data: { folders, files, currentFolderId: options.currentFolderId },
    autoFocus: true
  });

  return instance;
}
