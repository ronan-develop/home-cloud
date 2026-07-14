import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import RenameMediaController from '../controllers/rename_media_controller.js';

describe('rename-media controller', () => {
  let application;
  let promptSpy;

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="thumb" data-controller="rename-media"
           data-rename-media-url-value="/gallery/abc/rename"
           data-rename-media-current-name-value="ancien-nom.jpg"
           data-rename-media-csrf-token-value="fake-csrf-token">
        <button data-action="click->rename-media#prompt"></button>
        <div data-rename-media-target="caption">ancien-nom.jpg</div>
      </div>
    `;

    application = Application.start();
    application.register('rename-media', RenameMediaController);

    global.fetch = jest.fn();
  });

  afterEach(() => {
    application.stop();
    jest.restoreAllMocks();
  });

  function clickRenameButton() {
    document.querySelector('button').click();
  }

  test('does nothing when prompt is cancelled', async () => {
    promptSpy = jest.spyOn(window, 'prompt').mockReturnValue(null);

    clickRenameButton();
    await Promise.resolve();

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('does nothing when the name is unchanged', async () => {
    promptSpy = jest.spyOn(window, 'prompt').mockReturnValue('ancien-nom.jpg');

    clickRenameButton();
    await Promise.resolve();

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('sends a POST request and updates the caption on success', async () => {
    promptSpy = jest.spyOn(window, 'prompt').mockReturnValue('nouveau-nom.jpg');
    global.fetch.mockResolvedValue({
      ok: true,
      json: async () => ({ name: 'nouveau-nom.jpg' }),
    });

    clickRenameButton();
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    expect(global.fetch).toHaveBeenCalledWith('/gallery/abc/rename', expect.objectContaining({ method: 'POST' }));
    const sentBody = global.fetch.mock.calls[0][1].body;
    expect(new URLSearchParams(sentBody).get('_token')).toBe('fake-csrf-token');
    expect(document.querySelector('[data-rename-media-target="caption"]').textContent).toBe('nouveau-nom.jpg');
  });

  test('alerts and does not update the caption on failure', async () => {
    promptSpy = jest.spyOn(window, 'prompt').mockReturnValue('inva/lid.jpg');
    const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
    global.fetch.mockResolvedValue({ ok: false });

    clickRenameButton();
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    expect(alertSpy).toHaveBeenCalled();
    expect(document.querySelector('[data-rename-media-target="caption"]').textContent).toBe('ancien-nom.jpg');
  });
});
