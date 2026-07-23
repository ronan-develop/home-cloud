import { jest, describe, test, expect, beforeEach } from '@jest/globals';

jest.unstable_mockModule('../js/modal.js', () => ({
  Modal: {
    open: jest.fn(),
    close: jest.fn(),
  },
}));

const { Modal } = await import('../js/modal.js');
await import('../js/delete-file-modal.js');

describe('delete-file-modal behavior', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    document.body.innerHTML = `
      <form id="delete-file-form" method="POST" action="">
        <input type="hidden" name="keep_in_albums" id="delete-file-keep-in-albums-input" value="0">
        <input type="hidden" name="folder_id" id="delete-file-folder-input" value="">
        <span id="delete-file-name"></span>
      </form>
      <div id="delete-file-album-option" class="hidden"></div>
      <div id="delete-file-simple-confirm" class="hidden"></div>
    `;

    const form = document.getElementById('delete-file-form');
    form.submit = jest.fn();
  });

  test('opens modal and shows the album option when file is in an album', () => {
    window.openDeleteFileModal('file-1', 'vacances.jpg', 'folder-1', true);

    expect(Modal.open).toHaveBeenCalledWith('delete-file-modal');
    expect(document.getElementById('delete-file-album-option').classList.contains('hidden')).toBe(false);
    expect(document.getElementById('delete-file-simple-confirm').classList.contains('hidden')).toBe(true);
  });

  test('opens modal with simple confirmation only when file is not in any album', () => {
    window.openDeleteFileModal('file-2', 'doc.pdf', 'folder-1', false);

    expect(Modal.open).toHaveBeenCalledWith('delete-file-modal');
    expect(document.getElementById('delete-file-album-option').classList.contains('hidden')).toBe(true);
    expect(document.getElementById('delete-file-simple-confirm').classList.contains('hidden')).toBe(false);
  });

  test('submitDeleteFile sets keep_in_albums flag and submits the form', () => {
    window.openDeleteFileModal('file-1', 'vacances.jpg', 'folder-1', true);
    window.submitDeleteFile(true);

    const form = document.getElementById('delete-file-form');
    expect(document.getElementById('delete-file-keep-in-albums-input').value).toBe('1');
    expect(form.action).toContain('file-1');
    expect(form.submit).toHaveBeenCalled();
  });

  test('submitDeleteFile without keep sets keep_in_albums to 0', () => {
    window.openDeleteFileModal('file-1', 'vacances.jpg', 'folder-1', true);
    window.submitDeleteFile(false);

    expect(document.getElementById('delete-file-keep-in-albums-input').value).toBe('0');
  });
});
