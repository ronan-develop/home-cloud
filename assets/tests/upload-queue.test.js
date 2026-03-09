import { jest, describe, it, expect, beforeEach } from '@jest/globals';

const createUploadQueue = (await import('../js/upload-queue.js')).createUploadQueue;

describe('UploadQueue', () => {
  let queue;
  let mockUploadFn;
  let callbacks;

  beforeEach(() => {
    mockUploadFn = jest.fn();
    callbacks = {
      onProgress: jest.fn(),
      onComplete: jest.fn(),
      onError: jest.fn(),
      onQueueDone: jest.fn(),
    };
  });

  // Helper: create file
  function createFile(name = 'test.txt', size = 1024) {
    return new File(['x'.repeat(size)], name, { type: 'text/plain' });
  }

  describe('enqueue & getStats', () => {
    it('should add files in PENDING state', () => {
      mockUploadFn.mockImplementation(() => new Promise(() => {})); // Never resolves
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const files = [createFile('a.txt'), createFile('b.txt')];

      queue.enqueue(files, { folderId: 'folder-1' });

      const stats = queue.getStats();
      expect(stats.total).toBe(2);
      expect(stats.pending).toBe(0); // They start uploading immediately (up to maxConcurrent=3)
      expect(stats.uploading).toBe(2);
      expect(stats.done).toBe(0);
      expect(stats.error).toBe(0);
      expect(stats.cancelled).toBe(0);
    });

    it('should launch up to maxConcurrent uploads', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, maxConcurrent: 2, ...callbacks });
      mockUploadFn.mockImplementation(() => new Promise(() => {})); // Never resolves

      const files = [createFile('a.txt'), createFile('b.txt'), createFile('c.txt')];
      queue.enqueue(files, {});

      // Wait for event loop
      await new Promise(r => setTimeout(r, 50));

      expect(mockUploadFn).toHaveBeenCalledTimes(2);
      const stats = queue.getStats();
      expect(stats.uploading).toBe(2);
      expect(stats.pending).toBe(1);
    });

    it('should start next upload when one finishes', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, maxConcurrent: 2, ...callbacks });
      mockUploadFn.mockResolvedValue({ id: 'file-123' });

      const files = [createFile('a.txt'), createFile('b.txt'), createFile('c.txt')];
      queue.enqueue(files, {});

      await new Promise(r => setTimeout(r, 100));

      expect(mockUploadFn).toHaveBeenCalledTimes(3);
    });

    it('should call onComplete with (file, response)', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const file = createFile('test.txt');
      const response = { id: 'file-123', name: 'test.txt' };
      mockUploadFn.mockResolvedValue(response);

      queue.enqueue([file], {});
      await new Promise(r => setTimeout(r, 100));

      expect(callbacks.onComplete).toHaveBeenCalledWith(file, response);
    });

    it('should call onError with (file, error) if upload rejects', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const file = createFile('test.txt');
      const error = new Error('Upload failed');
      mockUploadFn.mockRejectedValue(error);

      queue.enqueue([file], {});
      await new Promise(r => setTimeout(r, 100));

      expect(callbacks.onError).toHaveBeenCalledWith(file, expect.any(Error));
    });

    it('should call onProgress with (file, {loaded, total, percent})', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const file = createFile('test.txt');

      mockUploadFn.mockImplementation((f, metadata, progressFn) => {
        progressFn({ loaded: 512, total: 1024, percent: 50 });
        return Promise.resolve({ id: 'file-123' });
      });

      queue.enqueue([file], {});
      await new Promise(r => setTimeout(r, 100));

      expect(callbacks.onProgress).toHaveBeenCalledWith(file, {
        loaded: 512,
        total: 1024,
        percent: 50,
      });
    });

    it('should call onQueueDone when all uploads finish', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      mockUploadFn.mockResolvedValue({ id: 'file-123' });

      queue.enqueue([createFile('a.txt'), createFile('b.txt')], {});
      await new Promise(r => setTimeout(r, 150));

      expect(callbacks.onQueueDone).toHaveBeenCalled();
    });
  });

  describe('cancel', () => {
    it('should cancel a specific file', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const file = createFile('test.txt');
      mockUploadFn.mockImplementation(() => new Promise(() => {})); // Never resolves

      queue.enqueue([file], {});
      await new Promise(r => setTimeout(r, 50));

      queue.cancel(file);

      const stats = queue.getStats();
      expect(stats.cancelled).toBe(1);
    });

    it('should cancelAll', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, maxConcurrent: 1, ...callbacks });
      mockUploadFn.mockImplementation(() => new Promise(() => {}));

      const files = [createFile('a.txt'), createFile('b.txt'), createFile('c.txt')];
      queue.enqueue(files, {});
      await new Promise(r => setTimeout(r, 50));

      queue.cancelAll();

      const stats = queue.getStats();
      expect(stats.cancelled).toBe(3);
    });
  });

  describe('retry', () => {
    it('should retry a file in ERROR state', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const file = createFile('test.txt');

      mockUploadFn.mockRejectedValueOnce(new Error('Fail'));
      mockUploadFn.mockResolvedValueOnce({ id: 'file-123' });

      queue.enqueue([file], {});
      await new Promise(r => setTimeout(r, 100));

      expect(callbacks.onError).toHaveBeenCalled();

      queue.retry(file);
      await new Promise(r => setTimeout(r, 100));

      expect(mockUploadFn).toHaveBeenCalledTimes(2);
      expect(callbacks.onComplete).toHaveBeenCalled();
    });

    it('should retryAll error files', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, maxConcurrent: 3, ...callbacks });
      const files = [createFile('a.txt'), createFile('b.txt'), createFile('c.txt')];

      mockUploadFn
        .mockRejectedValueOnce(new Error('Fail'))
        .mockRejectedValueOnce(new Error('Fail'))
        .mockResolvedValueOnce({ id: 'file-a' })
        .mockResolvedValueOnce({ id: 'file-b' })
        .mockResolvedValueOnce({ id: 'file-c' });

      queue.enqueue(files, {});
      await new Promise(r => setTimeout(r, 100));

      expect(callbacks.onError).toHaveBeenCalledTimes(2);

      queue.retryAll();
      await new Promise(r => setTimeout(r, 100));

      expect(mockUploadFn).toHaveBeenCalledTimes(5);
    });
  });

  describe('getItems', () => {
    it('should return array of items with {file, state, progress, error?, response?}', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const file = createFile('test.txt');
      mockUploadFn.mockResolvedValue({ id: 'file-123' });

      queue.enqueue([file], {});
      await new Promise(r => setTimeout(r, 100));

      const items = queue.getItems();
      expect(items).toHaveLength(1);
      expect(items[0].file).toBe(file);
      expect(items[0].state).toBe('done');
      expect(items[0].response).toEqual({ id: 'file-123' });
    });
  });

  describe('destroy', () => {
    it('should cancel all and clear items', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      mockUploadFn.mockImplementation(() => new Promise(() => {}));

      queue.enqueue([createFile('a.txt'), createFile('b.txt')], {});
      await new Promise(r => setTimeout(r, 50));

      queue.destroy();

      expect(queue.getItems()).toHaveLength(0);
      expect(queue.getStats().total).toBe(0);
    });
  });

  describe('concurrency limit', () => {
    it('should never exceed maxConcurrent', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, maxConcurrent: 3, ...callbacks });

      // Track concurrent calls
      let maxConcurrent = 0;
      let currentConcurrent = 0;
      mockUploadFn.mockImplementation(async () => {
        currentConcurrent++;
        maxConcurrent = Math.max(maxConcurrent, currentConcurrent);
        await new Promise(r => setTimeout(r, 50));
        currentConcurrent--;
        return { id: 'file-123' };
      });

      const files = Array.from({ length: 10 }, (_, i) => createFile(`file${i}.txt`));
      queue.enqueue(files, {});

      await new Promise(r => setTimeout(r, 600));

      expect(maxConcurrent).toBeLessThanOrEqual(3);
    });
  });

  describe('FIFO order', () => {
    it('should process files in order of enqueue', async () => {
      queue = createUploadQueue({ uploadFn: mockUploadFn, ...callbacks });
      const callOrder = [];

      mockUploadFn.mockImplementation((file) => {
        callOrder.push(file.name);
        return Promise.resolve({ id: 'file-123' });
      });

      const files = [createFile('a.txt'), createFile('b.txt'), createFile('c.txt')];
      queue.enqueue(files, {});

      await new Promise(r => setTimeout(r, 150));

      expect(callOrder).toEqual(['a.txt', 'b.txt', 'c.txt']);
    });
  });

  describe('setUploadFn & process', () => {
    it('should allow setting uploadFn after creation', async () => {
      queue = createUploadQueue({ maxConcurrent: 1, ...callbacks }); // No uploadFn
      const files = [createFile('test.txt')];
      
      queue.enqueue(files, {});

      // Set uploadFn and process
      mockUploadFn.mockImplementation(() => Promise.resolve({ id: 'file-123' }));
      queue.setUploadFn(mockUploadFn);
      
      await queue.process();

      expect(callbacks.onComplete).toHaveBeenCalledWith(files[0], { id: 'file-123' });
      expect(callbacks.onQueueDone).toHaveBeenCalled();
    });

    it('should allow passing uploadFn to process()', async () => {
      queue = createUploadQueue({ maxConcurrent: 1, ...callbacks }); // No uploadFn
      const files = [createFile('test.txt')];
      
      queue.enqueue(files, {});

      // Pass uploadFn to process()
      mockUploadFn.mockImplementation(() => Promise.resolve({ id: 'file-456' }));
      
      await queue.process(mockUploadFn);

      expect(callbacks.onComplete).toHaveBeenCalledWith(files[0], { id: 'file-456' });
    });
  });
});

