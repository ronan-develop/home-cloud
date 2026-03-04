export const Modal = {
	open(id) {
		const el = document.getElementById(id);
		if (!el) return;
		el.classList.remove('hidden');
		el.classList.add('modal-open');
		el.focus?.();
	},
	close(id) {
		const el = document.getElementById(id);
		if (!el) return;
		el.classList.add('hidden');
		el.classList.remove('modal-open');
		document.body.focus();
	},
};

window.closeModal           = (id) => Modal.close(id);
window.openMoveElementModal = (id) => Modal.open(id);
