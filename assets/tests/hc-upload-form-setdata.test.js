import { jest, describe, test, expect, beforeEach } from '@jest/globals';

const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-upload-form.js');

describe('HCUploadForm setData integration', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  test('openModal attaches data to hc-upload-form via setData', async () => {
    const data = { folders: [{ id: 'f1', name: 'Root' }], files: [{ name: 'b.txt', size: 200 }], currentFolderId: 'f1' };
    const instance = await openModal('hc-upload-form', { title: 'Upload', data });

    const contentEl = document.querySelector('hc-upload-form');
    expect(contentEl).toBeTruthy();

    // The form should have rendered the folder list
    const folderBtn = contentEl.shadowRoot.querySelector('.folder-option');
    expect(folderBtn).toBeTruthy();
    expect(folderBtn.getAttribute('data-folder-id')).toBe('f1');

    // Clean up
    instance.close();
  });
});
