import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import GalleryLightboxController from '../controllers/gallery_lightbox_controller.js';

/**
 * Lecture vidéo dans la lightbox galerie : les médias de type "video"
 * s'affichent dans une balise <video> lisible, les photos restent dans <img>.
 */
describe('gallery-lightbox video playback', () => {
  let application;

  beforeEach(() => {
    jest.useFakeTimers();

    document.body.innerHTML = `
      <a data-lightbox data-media-type="photo" data-full-src="/a.jpg"></a>
      <a data-lightbox data-media-type="video" data-full-src="/b.mp4"></a>
      <a data-lightbox data-media-type="photo" data-full-src="/c.jpg"></a>
      <div id="lightbox" data-controller="gallery-lightbox">
        <button data-gallery-lightbox-target="prev" data-action="gallery-lightbox#prev"></button>
        <img data-gallery-lightbox-target="img">
        <video data-gallery-lightbox-target="video" controls></video>
        <button data-gallery-lightbox-target="next" data-action="gallery-lightbox#next"></button>
        <button data-gallery-lightbox-target="play" data-action="gallery-lightbox#togglePlay"></button>
      </div>
    `;

    HTMLMediaElement.prototype.play = jest.fn();
    HTMLMediaElement.prototype.pause = jest.fn();

    application = Application.start();
    application.register('gallery-lightbox', GalleryLightboxController);
  });

  afterEach(() => {
    application.stop();
    jest.useRealTimers();
  });

  function getController() {
    const el = document.getElementById('lightbox');
    return application.getControllerForElementAndIdentifier(el, 'gallery-lightbox');
  }

  test('shows the video element and hides the img for a video slide', () => {
    const controller = getController();
    controller.show(1);

    expect(controller.videoTarget.src).toContain('b.mp4');
    expect(controller.videoTarget.style.display).not.toBe('none');
    expect(controller.imgTarget.style.display).toBe('none');
  });

  test('shows the img element and hides the video for a photo slide', () => {
    const controller = getController();
    controller.show(1);
    controller.show(0);

    expect(controller.imgTarget.src).toContain('a.jpg');
    expect(controller.imgTarget.style.display).not.toBe('none');
    expect(controller.videoTarget.style.display).toBe('none');
  });

  test('pauses the video when navigating away from it', () => {
    const controller = getController();
    controller.show(1);

    controller.next();

    expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled();
  });

  test('pauses the video when the lightbox closes', () => {
    const controller = getController();
    controller.show(1);

    controller.close();

    expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled();
  });

  test('slideshow waits for the video to finish before advancing, not the fixed interval', () => {
    const controller = getController();
    controller.show(0);
    controller.togglePlay();

    jest.advanceTimersByTime(4000);
    expect(controller.videoTarget.src).toContain('b.mp4');

    jest.advanceTimersByTime(60000);
    expect(controller.videoTarget.src).toContain('b.mp4');

    controller.videoTarget.dispatchEvent(new Event('ended'));
    expect(controller.imgTarget.src).toContain('c.jpg');
  });

  test('slideshow resumes the fixed interval once past the video', () => {
    const controller = getController();
    controller.show(0);
    controller.togglePlay();

    jest.advanceTimersByTime(4000);
    controller.videoTarget.dispatchEvent(new Event('ended'));

    jest.advanceTimersByTime(4000);
    expect(controller.imgTarget.src).toContain('a.jpg');
  });
});
