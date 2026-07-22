import { jest, describe, test, expect, beforeEach } from '@jest/globals';

jest.unstable_mockModule('../js/modal.js', () => ({
  Modal: {
    open: jest.fn(),
    close: jest.fn(),
  },
}));

const { Modal } = await import('../js/modal.js');
await import('../js/media-viewer-modal.js');

describe('media-viewer-modal behavior', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    window.HTMLMediaElement.prototype.pause = jest.fn();
    window.HTMLMediaElement.prototype.load = jest.fn();

    document.body.innerHTML = `
      <span id="media-viewer-name"></span>
      <img id="media-viewer-image" class="hidden" src="">
      <video id="media-viewer-video" class="hidden" src=""></video>
    `;
  });

  test('opening an image shows the img element and hides the video', () => {
    window.openMediaViewerModal('/files/abc-123/view', 'photo.jpg', 'image');

    const img = document.getElementById('media-viewer-image');
    const video = document.getElementById('media-viewer-video');

    expect(img.src).toContain('/files/abc-123/view');
    expect(img.classList.contains('hidden')).toBe(false);
    expect(video.classList.contains('hidden')).toBe(true);
    expect(document.getElementById('media-viewer-name').textContent).toBe('photo.jpg');
    expect(Modal.open).toHaveBeenCalledWith('media-viewer-modal');
  });

  test('opening a video shows the video element and hides the img', () => {
    window.openMediaViewerModal('/files/def-456/view', 'clip.mp4', 'video');

    const img = document.getElementById('media-viewer-image');
    const video = document.getElementById('media-viewer-video');

    expect(video.src).toContain('/files/def-456/view');
    expect(video.classList.contains('hidden')).toBe(false);
    expect(img.classList.contains('hidden')).toBe(true);
    expect(document.getElementById('media-viewer-name').textContent).toBe('clip.mp4');
    expect(Modal.open).toHaveBeenCalledWith('media-viewer-modal');
  });

  test('closing after a video stops playback and releases the source', () => {
    window.openMediaViewerModal('/files/def-456/view', 'clip.mp4', 'video');
    window.closeMediaViewerModal();

    const video = document.getElementById('media-viewer-video');
    const img = document.getElementById('media-viewer-image');

    expect(video.pause).toHaveBeenCalled();
    expect(video.load).toHaveBeenCalled();
    expect(video.hasAttribute('src')).toBe(false);
    expect(video.classList.contains('hidden')).toBe(true);
    expect(img.getAttribute('src')).toBe('');
    expect(Modal.close).toHaveBeenCalledWith('media-viewer-modal');
  });

  test('switching from video to image hides the previously shown video', () => {
    window.openMediaViewerModal('/files/def-456/view', 'clip.mp4', 'video');
    window.openMediaViewerModal('/files/abc-123/view', 'photo.jpg', 'image');

    const img = document.getElementById('media-viewer-image');
    const video = document.getElementById('media-viewer-video');

    expect(img.classList.contains('hidden')).toBe(false);
    expect(video.classList.contains('hidden')).toBe(true);
  });
});
