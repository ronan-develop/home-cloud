import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import Slideshow from '../controllers/Slideshow.js';

describe('Slideshow', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('is not playing initially', () => {
    const slideshow = new Slideshow(() => {});
    expect(slideshow.isPlaying).toBe(false);
  });

  test('start() calls onAdvance every intervalMs', () => {
    const onAdvance = jest.fn();
    const slideshow = new Slideshow(onAdvance, 1000);

    slideshow.start();
    jest.advanceTimersByTime(3000);

    expect(onAdvance).toHaveBeenCalledTimes(3);
    expect(slideshow.isPlaying).toBe(true);
  });

  test('stop() halts further calls', () => {
    const onAdvance = jest.fn();
    const slideshow = new Slideshow(onAdvance, 1000);

    slideshow.start();
    jest.advanceTimersByTime(1000);
    slideshow.stop();
    jest.advanceTimersByTime(3000);

    expect(onAdvance).toHaveBeenCalledTimes(1);
    expect(slideshow.isPlaying).toBe(false);
  });

  test('toggle() switches between playing and stopped', () => {
    const slideshow = new Slideshow(() => {}, 1000);

    slideshow.toggle();
    expect(slideshow.isPlaying).toBe(true);

    slideshow.toggle();
    expect(slideshow.isPlaying).toBe(false);
  });

  test('start() is idempotent while already playing', () => {
    const onAdvance = jest.fn();
    const slideshow = new Slideshow(onAdvance, 1000);

    slideshow.start();
    slideshow.start();
    jest.advanceTimersByTime(1000);

    expect(onAdvance).toHaveBeenCalledTimes(1);
  });
});
