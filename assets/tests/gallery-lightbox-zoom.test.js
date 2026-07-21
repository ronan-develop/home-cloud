import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import GalleryLightboxController from '../controllers/gallery_lightbox_controller.js';

/**
 * Zoom de la lightbox galerie : clic sur la photo pour zoomer sur le point
 * cliqué, clics suivants pour zoomer davantage (paliers), jusqu'à un
 * plafond puis retour à l'état non zoomé. Reset au changement de photo.
 * L'origine du zoom reste celle du premier clic tant qu'on reste zoomé.
 */
describe('gallery-lightbox zoom', () => {
  let application;

  beforeEach(() => {
    document.body.innerHTML = `
      <a data-lightbox data-media-type="photo" data-full-src="/a.jpg"></a>
      <a data-lightbox data-media-type="photo" data-full-src="/b.jpg"></a>
      <a data-lightbox data-media-type="video" data-full-src="/c.mp4"></a>
      <div id="lightbox" data-controller="gallery-lightbox">
        <button data-gallery-lightbox-target="prev" data-action="gallery-lightbox#prev"></button>
        <img data-gallery-lightbox-target="img" data-action="click->gallery-lightbox#toggleZoom">
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
  });

  function getController() {
    const el = document.getElementById('lightbox');
    return application.getControllerForElementAndIdentifier(el, 'gallery-lightbox');
  }

  function clickAt(imgTarget, clientX, clientY, width = 200, height = 100) {
    imgTarget.getBoundingClientRect = () => ({
      left: 0, top: 0, width, height, right: width, bottom: height,
    });
    imgTarget.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX, clientY }));
  }

  test('clicking the image zooms in centered on the clicked point', () => {
    const controller = getController();
    controller.show(0);

    clickAt(controller.imgTarget, 150, 75);

    expect(controller.imgTarget.style.transform).toBe('scale(2)');
    expect(controller.imgTarget.style.transformOrigin).toBe('75% 75%');
  });

  test('clicking again while zoomed increases the zoom level further', () => {
    const controller = getController();
    controller.show(0);

    clickAt(controller.imgTarget, 150, 75);
    expect(controller.imgTarget.style.transform).toBe('scale(2)');

    clickAt(controller.imgTarget, 10, 10);

    expect(controller.imgTarget.style.transform).toBe('scale(3)');
  });

  test('clicking again while zoomed keeps the origin of the first click', () => {
    const controller = getController();
    controller.show(0);

    clickAt(controller.imgTarget, 150, 75);
    expect(controller.imgTarget.style.transformOrigin).toBe('75% 75%');

    clickAt(controller.imgTarget, 10, 10);

    expect(controller.imgTarget.style.transformOrigin).toBe('75% 75%');
  });

  test('clicking past the max zoom level resets to the unzoomed state', () => {
    const controller = getController();
    controller.show(0);

    clickAt(controller.imgTarget, 150, 75); // scale 2
    clickAt(controller.imgTarget, 10, 10); // scale 3 (max)
    clickAt(controller.imgTarget, 10, 10); // back to unzoomed

    expect(controller.imgTarget.style.transform).toBe('scale(1)');
    expect(controller.imgTarget.style.transformOrigin).toBe('50% 50%');
  });

  test('changing photo resets the zoom', () => {
    const controller = getController();
    controller.show(0);
    clickAt(controller.imgTarget, 150, 75);
    expect(controller.imgTarget.style.transform).toBe('scale(2)');

    controller.show(1);

    expect(controller.imgTarget.style.transform).toBe('scale(1)');
    expect(controller.imgTarget.style.transformOrigin).toBe('50% 50%');
  });

  test('adds a zoomed class while zoomed and removes it once back to unzoomed', () => {
    const controller = getController();
    controller.show(0);

    clickAt(controller.imgTarget, 150, 75); // scale 2
    expect(controller.imgTarget.classList.contains('hc-lightbox-img--zoomed')).toBe(true);

    clickAt(controller.imgTarget, 150, 75); // scale 3 (max)
    expect(controller.imgTarget.classList.contains('hc-lightbox-img--zoomed')).toBe(true);

    clickAt(controller.imgTarget, 150, 75); // back to unzoomed
    expect(controller.imgTarget.classList.contains('hc-lightbox-img--zoomed')).toBe(false);
  });
});
