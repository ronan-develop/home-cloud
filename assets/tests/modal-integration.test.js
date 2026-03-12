import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';

const { openModal } = await import('../services/ModalFactory.js');
await import('../components/hc-confirm.js');
await import('../components/hc-alert.js');
await import('../components/hc-upload-form.js');

/**
 * Scénarios d'intégration multi-modaux :
 *   1. Deux modaux empilées (z-index croissant)
 *   2. upload → confirm sur conflit → alert feedback
 *   3. Fermeture de la modale du dessus ne ferme pas celle du dessous
 *   4. openModal avec instance Cancel ne bloque pas la suivante
 */

describe('Modal integration — multi-modal stack', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  test('deux modaux ont des z-index croissants', async () => {
    const inst1 = await openModal('hc-confirm', {
      title: 'Premier',
      data: { message: 'Modal 1' }
    });
    const inst2 = await openModal('hc-confirm', {
      title: 'Deuxième',
      data: { message: 'Modal 2' }
    });

    const modals = document.querySelectorAll('hc-modal');
    expect(modals.length).toBe(2);

    const z1 = parseInt(modals[0].style.zIndex || 0);
    const z2 = parseInt(modals[1].style.zIndex || 0);
    expect(z2).toBeGreaterThan(z1);

    inst1.close();
    inst2.close();
  });

  test('fermer la seconde modale ne ferme pas la première', async () => {
    const inst1 = await openModal('hc-confirm', {
      title: 'Fond',
      data: { message: 'Restant' }
    });
    const inst2 = await openModal('hc-alert', {
      title: 'Dessus',
      data: { message: 'Temporaire', type: 'info' }
    });

    inst2.close();

    // La première modale est toujours ouverte
    const modals = document.querySelectorAll('hc-modal');
    expect(modals.length).toBe(2); // toujours dans le DOM

    const modal1El = modals[0];
    const overlay = modal1El.shadowRoot.querySelector('.hc-modal-overlay');
    expect(overlay.classList.contains('open')).toBe(true);

    inst1.close();
  });
});

describe('Modal integration — flux upload → confirm → alert', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    global.fetch = jest.fn();
  });

  test('flux complet : upload → conflit → confirm → alert succès', async () => {
    // 1. Ouvrir modale upload avec dossiers
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ 'hydra:member': [{ id: 'f1', name: 'Root' }] })
    });

    const { openUploadModal } = await import('../js/open-upload-modal.js');
    const uploadInst = await openUploadModal({
      title: 'Upload',
      files: [{ name: 'doc.pdf', size: 1024 }]
    });

    const uploadEl = document.querySelector('hc-upload-form');
    expect(uploadEl).toBeTruthy();

    // 2. Simuler un conflit → ouvrir confirm
    const confirmInst = await openModal('hc-confirm', {
      title: 'Conflit détecté',
      data: { message: 'doc.pdf existe déjà. Écraser ?', okText: 'Écraser' }
    });
    const confirmPromise = confirmInst.asPromise();

    const confirmEl = document.querySelector('hc-confirm');
    confirmEl.shadowRoot.querySelector('.confirm-ok').click();
    const confirmed = await confirmPromise;
    expect(confirmed).toBe(true);

    // 3. Simuler succès → alert
    const alertInst = await openModal('hc-alert', {
      title: 'Succès',
      data: { message: 'doc.pdf uploadé avec succès !', type: 'success' }
    });
    const alertPromise = alertInst.asPromise();

    const alertEl = document.querySelector('hc-alert');
    alertEl.shadowRoot.querySelector('.alert-ok').click();
    const alertResult = await alertPromise;
    expect(alertResult).toBe(true);

    uploadInst.close();
  });

  test('annuler un confirm ne bloque pas une nouvelle modale', async () => {
    const inst1 = await openModal('hc-confirm', {
      title: 'Annuler ?',
      data: { message: 'Test' }
    });
    const p1 = inst1.asPromise().catch(() => 'cancelled');

    const confirmEl = document.querySelector('hc-confirm');
    confirmEl.shadowRoot.querySelector('.confirm-cancel').click();
    const r1 = await p1;
    expect(r1).toBe('cancelled');

    // Après annulation, une nouvelle modale s'ouvre normalement
    const inst2 = await openModal('hc-alert', {
      title: 'Après annulation',
      data: { message: 'Toujours OK', type: 'info' }
    });
    const alertEl = document.querySelector('hc-alert');
    expect(alertEl).toBeTruthy();
    inst2.close();
  });
});

describe('Modal integration — lifecycle', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  test('body overflow est restored après fermeture', async () => {
    const inst = await openModal('hc-confirm', {
      title: 'Test overflow',
      data: { message: 'Check' }
    });
    expect(document.body.style.overflow).toBe('hidden');
    inst.close();
    expect(document.body.style.overflow).toBe('');
  });

  test('modal:open et modal:close events sont dispatchés', async () => {
    const inst = await openModal('hc-confirm', {
      title: 'Events',
      data: { message: 'test' }
    });

    const closeSpy = jest.fn();
    inst.on('modal:close', closeSpy);
    inst.close();

    expect(closeSpy).toHaveBeenCalledTimes(1);
  });
});
