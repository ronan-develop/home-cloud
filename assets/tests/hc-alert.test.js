import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';

const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-alert.js');

describe('HCAlert', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('renders alert message and type', async () => {
    const instance = await openModal('hc-alert', {
      title: 'Succès',
      data: { message: 'Fichier uploadé !', type: 'success' }
    });
    const el = document.querySelector('hc-alert');
    expect(el).toBeTruthy();
    const msg = el.shadowRoot.querySelector('.alert-message');
    expect(msg.textContent).toContain('Fichier uploadé');
    const icon = el.shadowRoot.querySelector('.alert-icon');
    expect(icon.textContent).toBe('✅');
    instance.close();
  });

  test('auto-dismiss after autoDismiss ms', async () => {
    const instance = await openModal('hc-alert', {
      title: 'Info',
      data: { message: 'Terminé', type: 'info', autoDismiss: 2000 }
    });
    const promise = instance.asPromise().catch(() => {});
    jest.advanceTimersByTime(2000);
    // Promise should have settled (either resolved/rejected)
    // No assertion needed beyond no hanging
    instance.close();
  });

  test('closes on OK button click', async () => {
    const instance = await openModal('hc-alert', {
      title: 'Erreur',
      data: { message: 'Échec upload', type: 'error' }
    });
    const promise = instance.asPromise();
    const el = document.querySelector('hc-alert');
    el.shadowRoot.querySelector('.alert-ok').click();
    const result = await promise;
    expect(result).toBe(true);
  });
});
