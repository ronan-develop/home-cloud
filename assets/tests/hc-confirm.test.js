import { jest, describe, test, expect, beforeEach } from '@jest/globals';

const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-confirm.js');

describe('HCConfirm', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  test('renders confirm message', async () => {
    const instance = await openModal('hc-confirm', {
      title: 'Supprimer ?',
      data: { message: 'Êtes-vous sûr de vouloir supprimer ce dossier ?' }
    });
    const el = document.querySelector('hc-confirm');
    expect(el).toBeTruthy();
    const msg = el.shadowRoot.querySelector('.confirm-message');
    expect(msg.textContent).toContain('Êtes-vous sûr');
    instance.close();
  });

  test('resolves with true on OK click', async () => {
    const instance = await openModal('hc-confirm', {
      title: 'Confirmer',
      data: { message: 'Continuer ?' }
    });
    const promise = instance.asPromise();
    const el = document.querySelector('hc-confirm');
    el.shadowRoot.querySelector('.confirm-ok').click();
    const result = await promise;
    expect(result).toBe(true);
  });

  test('rejects on Cancel click', async () => {
    const instance = await openModal('hc-confirm', {
      title: 'Confirmer',
      data: { message: 'Continuer ?' }
    });
    const promise = instance.asPromise();
    const el = document.querySelector('hc-confirm');
    el.shadowRoot.querySelector('.confirm-cancel').click();
    await expect(promise).rejects.toThrow('cancel');
  });
});
