import { jest, describe, test, expect, beforeEach } from '@jest/globals';

jest.unstable_mockModule('../js/modal.js', () => ({
  Modal: {
    open: jest.fn(),
    close: jest.fn(),
  },
}));

const { Modal } = await import('../js/modal.js');
await import('../js/pdf-viewer-modal.js');

describe('pdf-viewer-modal behavior', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    document.body.innerHTML = `
      <iframe id="pdf-viewer-iframe" src="about:blank"></iframe>
      <span id="pdf-viewer-name"></span>
    `;
  });

  test('sets iframe src to the view URL and opens the modal', () => {
    window.openPdfViewerModal('/files/abc-123/view', 'rapport.pdf');

    const iframe = document.getElementById('pdf-viewer-iframe');
    expect(iframe.src).toContain('/files/abc-123/view');
    expect(document.getElementById('pdf-viewer-name').textContent).toBe('rapport.pdf');
    expect(Modal.open).toHaveBeenCalledWith('pdf-viewer-modal');
  });

  test('closing resets the iframe src and closes the modal', () => {
    window.openPdfViewerModal('/files/abc-123/view', 'rapport.pdf');
    window.closePdfViewerModal();

    const iframe = document.getElementById('pdf-viewer-iframe');
    expect(iframe.src).toContain('about:blank');
    expect(Modal.close).toHaveBeenCalledWith('pdf-viewer-modal');
  });
});
