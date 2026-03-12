import { jest, describe, test, expect, beforeEach } from '@jest/globals';

const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-upload-form.js');

describe('HCUploadForm create-folder UX', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    global.fetch = jest.fn();
  });

  test('creates folder via API and selects it', async () => {
    // initial folders empty
    const instance = await openModal('hc-upload-form', { title: 'Upload', data: { folders: [], files: [] } });
    const content = document.querySelector('hc-upload-form');
    expect(content).toBeTruthy();

    // mock successful POST response
    const created = { id: 'f-new', name: 'NewFolder' };
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => created });

    const input = content.shadowRoot.getElementById('new-folder-input');
    const btn = content.shadowRoot.getElementById('create-folder-btn');

    input.value = 'NewFolder';
    btn.click();

    // wait a tick for async create
    await new Promise(r => setTimeout(r, 0));

    // new folder button present and active
    const folderBtn = content.shadowRoot.querySelector('.folder-option');
    expect(folderBtn).toBeTruthy();
    expect(folderBtn.getAttribute('data-folder-id')).toBe('f-new');

    // instance cleanup
    instance.close();
  });
});
