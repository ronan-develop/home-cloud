import { JSDOM } from 'jsdom';

// Two tests: when folder is empty -> form submitted immediately; when not empty -> modal opened

describe('delete-folder-modal behavior', () => {
  beforeEach(() => {
    // Reset DOM
    const dom = new JSDOM(`<!doctype html><html><body>
      <form id="delete-folder-form" method="POST" action="">
        <input type="hidden" name="delete_contents" id="delete-folder-contents-input" value="1">
        <input type="hidden" name="redirect_folder_id" id="delete-folder-redirect-input" value="">
      </form>
    </body></html>`, { runScripts: 'dangerously', resources: 'usable' });

    global.window = dom.window;
    global.document = dom.window.document;
    global.HTMLElement = dom.window.HTMLElement;

    // Provide a mock Modal global
    global.Modal = { open: jest.fn() };

    // Provide a mock form.submit implementation that we can spy on
    const form = document.getElementById('delete-folder-form');
    form.submit = jest.fn();
  });

  test('auto-submit when folder is empty', async () => {
    const moduleUrl = new URL('../../assets/js/delete-folder-modal.js', import.meta.url);
    await import(moduleUrl.href);

    // Call the global function as produced by the script
    // Parameters: folderId, folderName, parentId, isEmpty
    window.openDeleteFolderModal('id-1', 'EmptyFolder', '', '1');

    const form = document.getElementById('delete-folder-form');
    expect(form.submit).toHaveBeenCalled();
    // Ensure Modal.open was NOT called
    expect(Modal.open).not.toHaveBeenCalled();
  });

  test('opens modal when folder is not empty', async () => {
    const moduleUrl = new URL('../../assets/js/delete-folder-modal.js', import.meta.url);
    await import(moduleUrl.href);

    window.openDeleteFolderModal('id-2', 'NonEmpty', '', '0');

    // Should not submit
    const form = document.getElementById('delete-folder-form');
    expect(form.submit).not.toHaveBeenCalled();
    // Modal should be opened
    expect(Modal.open).toHaveBeenCalledWith('delete-folder-modal');
  });
});
