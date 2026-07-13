import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '../vendor/@hotwired/stimulus/stimulus.index.js';
import GalleryLightboxController from '../controllers/gallery_lightbox_controller.js';

/**
 * Diaporama de la lightbox galerie : auto-avance à intervalle fixe,
 * pause/lecture, arrêt à la fermeture.
 */
describe('gallery-lightbox slideshow', () => {
  let application;

  beforeEach(() => {
    jest.useFakeTimers();

    document.body.innerHTML = `
      <a data-lightbox data-full-src="/a.jpg"></a>
      <a data-lightbox data-full-src="/b.jpg"></a>
      <a data-lightbox data-full-src="/c.jpg"></a>
      <div id="lightbox" data-controller="gallery-lightbox">
        <button data-gallery-lightbox-target="prev" data-action="gallery-lightbox#prev"></button>
        <img data-gallery-lightbox-target="img">
        <button data-gallery-lightbox-target="next" data-action="gallery-lightbox#next"></button>
        <button data-gallery-lightbox-target="play" data-action="gallery-lightbox#togglePlay"></button>
      </div>
    `;

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

  test('togglePlay advances to the next slide every 4 seconds', () => {
    const controller = getController();
    controller.show(0);

    controller.togglePlay();
    expect(controller.imgTarget.src).toContain('a.jpg');

    jest.advanceTimersByTime(4000);
    expect(controller.imgTarget.src).toContain('b.jpg');

    jest.advanceTimersByTime(4000);
    expect(controller.imgTarget.src).toContain('c.jpg');
  });

  test('togglePlay again pauses the slideshow', () => {
    const controller = getController();
    controller.show(0);
    controller.togglePlay();

    jest.advanceTimersByTime(4000);
    expect(controller.imgTarget.src).toContain('b.jpg');

    controller.togglePlay();
    jest.advanceTimersByTime(8000);
    expect(controller.imgTarget.src).toContain('b.jpg');
  });

  test('slideshow loops back to the first slide after the last', () => {
    const controller = getController();
    controller.show(2);
    controller.togglePlay();

    jest.advanceTimersByTime(4000);
    expect(controller.imgTarget.src).toContain('a.jpg');
  });

  test('closing the lightbox stops the slideshow', () => {
    const controller = getController();
    controller.show(0);
    controller.togglePlay();

    controller.close();
    jest.advanceTimersByTime(8000);
    expect(controller.element.style.display).toBe('none');
  });

  test('manual navigation while playing does not desync the timer', () => {
    const controller = getController();
    controller.show(0);
    controller.togglePlay();

    controller.next();
    expect(controller.imgTarget.src).toContain('b.jpg');

    jest.advanceTimersByTime(4000);
    expect(controller.imgTarget.src).toContain('c.jpg');
  });
});
