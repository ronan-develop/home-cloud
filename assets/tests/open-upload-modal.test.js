import { jest, describe, test, expect, beforeEach } from '@jest/globals';

// Use real ModalFactory and components
const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-upload-form.js');
await import('../components/hc-modal.js');
const { openUploadModal } = await import('../js/open-upload-modal.js');

describe('openUploadModal helper', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    global.fetch = jest.fn();
  });

  test('fetches folders and opens modal with data', async () => {
    const apiResp = { 'hydra:member': [{ id: 'f1', name: 'Root' }] };
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => apiResp });

    const instance = await openUploadModal({ title: 'Upload Test' });

    expect(global.fetch).toHaveBeenCalledWith('/api/v1/folders', { credentials: 'same-origin' });

    const contentEl = document.querySelector('hc-upload-form');
    expect(contentEl).toBeTruthy();

    const folderBtn = contentEl.shadowRoot.querySelector('.folder-option');
    expect(folderBtn.getAttribute('data-folder-id')).toBe('f1');

    // cleanup
    instance.close();
  });
});
