import { jest, describe, test, expect, beforeEach } from '@jest/globals';

// Mock the modal module BEFORE any dynamic import of the module under test
jest.unstable_mockModule('../js/modal.js', () => ({
  Modal: {
    open: jest.fn(),
    close: jest.fn(),
  },
}));

// Import mocked Modal and the module under test (top-level await, ESM only)
const { Modal } = await import('../js/modal.js');
await import('../js/delete-folder-modal.js');

// Two tests: when folder is empty -> form submitted immediately; when not empty -> modal opened

describe('delete-folder-modal behavior', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    // Set up DOM using jest's built-in jsdom environment
    document.body.innerHTML = `
      <form id="delete-folder-form" method="POST" action="">
        <input type="hidden" name="delete_contents" id="delete-folder-contents-input" value="1">
        <input type="hidden" name="redirect_folder_id" id="delete-folder-redirect-input" value="">
        <span id="delete-folder-name"></span>
      </form>
    `;

    // Provide a mock form.submit implementation that we can spy on
    const form = document.getElementById('delete-folder-form');
    form.submit = jest.fn();
  });

  test('auto-submit when folder is empty', () => {
    window.openDeleteFolderModal('id-1', 'EmptyFolder', '', '1');

    const form = document.getElementById('delete-folder-form');
    expect(form.submit).toHaveBeenCalled();
    expect(Modal.open).not.toHaveBeenCalled();
  });

  test('opens modal when folder is not empty', () => {
    window.openDeleteFolderModal('id-2', 'NonEmpty', '', '0');

    const form = document.getElementById('delete-folder-form');
    expect(form.submit).not.toHaveBeenCalled();
    expect(Modal.open).toHaveBeenCalledWith('delete-folder-modal');
  });
});

