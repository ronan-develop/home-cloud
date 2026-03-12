import { jest, describe, test, expect, beforeEach } from '@jest/globals';

await import('../components/hc-folder-list.js');

const folders = [
    { id: 'root', name: 'Mes fichiers', icon: '🏠' },
    { id: 'photos', name: 'Photos', icon: '📷' },
    { id: 'docs', name: 'Documents', icon: '📄' },
];

function makeEl() {
    const el = document.createElement('hc-folder-list');
    document.body.appendChild(el);
    return el;
}

describe('HCFolderList', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    test('renders folder buttons after setFolders()', () => {
        const el = makeEl();
        el.setFolders(folders);
        const btns = el.shadowRoot.querySelectorAll('.folder-option');
        expect(btns.length).toBe(3);
        expect(btns[0].textContent).toContain('Mes fichiers');
    });

    test('selects first folder by default', () => {
        const el = makeEl();
        el.setFolders(folders);
        const active = el.shadowRoot.querySelector('.folder-option.active');
        expect(active).toBeTruthy();
        expect(active.getAttribute('data-folder-id')).toBe('root');
    });

    test('pre-selects folder via setSelected()', () => {
        const el = makeEl();
        el.setFolders(folders);
        el.setSelected('photos');
        const active = el.shadowRoot.querySelector('.folder-option.active');
        expect(active.getAttribute('data-folder-id')).toBe('photos');
    });

    test('getSelected() returns the active folder', () => {
        const el = makeEl();
        el.setFolders(folders);
        el.setSelected('docs');
        const selected = el.getSelected();
        expect(selected.id).toBe('docs');
        expect(selected.isNew).toBe(false);
    });

    test('clicking a folder dispatches folder-list:change', () => {
        const el = makeEl();
        el.setFolders(folders);
        const spy = jest.fn();
        el.addEventListener('folder-list:change', spy);
        const btn = el.shadowRoot.querySelectorAll('.folder-option')[1];
        btn.click();
        expect(spy).toHaveBeenCalledTimes(1);
        expect(spy.mock.calls[0][0].detail.id).toBe('photos');
    });

    test('new folder input returns isNew:true from getSelected()', () => {
        const el = makeEl();
        el.setFolders(folders);
        const input = el.shadowRoot.querySelector('.new-folder-input');
        input.value = 'Vacances 2026';
        input.dispatchEvent(new Event('input'));
        const selected = el.getSelected();
        expect(selected.isNew).toBe(true);
        expect(selected.newName).toBe('Vacances 2026');
    });

    test('typing in new folder input deselects existing folders', () => {
        const el = makeEl();
        el.setFolders(folders);
        const input = el.shadowRoot.querySelector('.new-folder-input');
        input.value = 'Nouveau';
        input.dispatchEvent(new Event('input'));
        const active = el.shadowRoot.querySelector('.folder-option.active');
        expect(active).toBeNull();
    });

    test('renders empty state when no folders', () => {
        const el = makeEl();
        el.setFolders([]);
        const empty = el.shadowRoot.querySelector('.folder-list-empty');
        expect(empty).toBeTruthy();
    });
});
