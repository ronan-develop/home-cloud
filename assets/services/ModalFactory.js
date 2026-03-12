import { HCModal } from '../components/hc-modal.js';

// Simple ModalFactory : openModal(ComponentClass, options) => returns ModalInstance
// ModalInstance: { modalElement, asPromise(), on(event, cb), close() }

export async function openModal(ComponentClass, options = {}) {
  // create modal element
  const modal = document.createElement('hc-modal');

  if (options.title) modal.setAttribute('title', options.title);
  if (options.size) modal.setAttribute('size', options.size);
  if (options.closeable === false) modal.setAttribute('closeable', 'false');
  if (options.backdrop === 'static') modal.setAttribute('backdrop', 'static');

  // Create content element
  let content;
  if (typeof ComponentClass === 'function') {
    try {
      content = new ComponentClass();
    } catch (e) {
      // If ComponentClass is an element tag name
      content = document.createElement(ComponentClass);
    }
  } else if (typeof ComponentClass === 'string') {
    content = document.createElement(ComponentClass);
  } else if (ComponentClass instanceof Element) {
    content = ComponentClass;
  } else {
    throw new TypeError('ComponentClass must be constructor, tagName or Element');
  }

  // Attach data/config
  if (options.data && typeof content.setData === 'function') {
    content.setData(options.data);
  } else if (options.data) {
    content.data = options.data;
  }

  modal.setContent(content);

  // If actions provided (HTML element), attach
  if (options.actions instanceof Element) {
    options.actions.setAttribute('slot', 'actions');
    modal.appendChild(options.actions);
  }

  // Append to body and open
  document.body.appendChild(modal);
  modal.open();

  // Return a small API surface
  const instance = {
    modal,
    close: () => modal.close(),
    on: (eventName, cb) => modal.addEventListener(eventName, cb),
    off: (eventName, cb) => modal.removeEventListener(eventName, cb),
    asPromise: () => modal.asPromise(),
  };

  // Propagate content events to modal
  content.addEventListener && content.addEventListener('submit', (e) => modal._handleSubmit(e.detail || null));
  content.addEventListener && content.addEventListener('cancel', () => modal._handleCancel());

  // If options.autoFocus === true, focus content first
  if (options.autoFocus && typeof content.focus === 'function') content.focus();

  return instance;
}
