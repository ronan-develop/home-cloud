const TOAST_ICONS = {
	success: '<polyline points="20 6 9 17 4 12"/>',
	error: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
	warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
};

function escapeHtml(text) {
	const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
	return String(text).replace(/[&<>"']/g, m => map[m]);
}

window.showToast = function(message, type = 'success') {
	let toast = document.getElementById('hc-toast');
	if (!toast) {
		toast = document.createElement('div');
		toast.id = 'hc-toast';
		toast.className = 'fixed bottom-4 right-4 z-[100] px-4 py-3 rounded-2xl text-white text-sm font-medium shadow-xl transition-all duration-300 flex items-center gap-2';
		document.body.appendChild(toast);
	}
	const iconPaths = TOAST_ICONS[type] || TOAST_ICONS.success;
	toast.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><g>${iconPaths}</g></svg><span>${escapeHtml(message)}</span>`;
	toast.className = toast.className.replace(/bg-\S+/, '');
	const bgClass = type === 'success' ? 'bg-green-500' : type === 'warning' ? 'bg-amber-500' : 'bg-red-500';
	toast.classList.add(bgClass);
	toast.classList.remove('opacity-0', 'translate-y-4');
	toast.classList.add('opacity-100', 'translate-y-0');
	setTimeout(() => toast.classList.add('opacity-0', 'translate-y-4'), 3000);
};
