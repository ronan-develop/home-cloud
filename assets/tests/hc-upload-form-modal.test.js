import { jest, describe, test, expect, beforeEach } from '@jest/globals';

// Import components and factory under test
const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-upload-form.js');
await import('../components/hc-modal.js');

describe('HCUploadForm within HCModal integration', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    jest.clearAllMocks && jest.clearAllMocks();
  });

  test('openModal returns instance and resolves when content dispatches submit', async () => {
    // Create data to pass
    const data = { folders: [{ id: 'f1', name: 'Root' }], files: [{ name: 'a.txt', size: 100 }] };

    const instance = await openModal('hc-upload-form', { title: 'Upload', data });

    // instance.asPromise() should be pending; simulate submit from content
    const contentEl = document.querySelector('hc-upload-form');
    expect(contentEl).toBeTruthy();

    // Simulate the upload form emitting a submit event with detail
    const payload = { uploaded: ['a.txt'] };
    const submitEvent = new CustomEvent('submit', { detail: payload });
    contentEl.dispatchEvent(submitEvent);

    // Wait next tick to allow modal to resolve
    const result = await instance.asPromise();
    expect(result).toEqual(payload);

    // Modal should be removed or closed
    const modalEl = document.querySelector('hc-modal');
    expect(modalEl).toBeTruthy(); // still present in DOM but closed
  });
});
