/* window.showToast = function(message, type = 'success') {
	let toast = document.getElementById('hc-toast');
	if (!toast) {
		toast = document.createElement('div');
		toast.id = 'hc-toast';
		toast.className = 'fixed bottom-4 right-4 z-[100] px-4 py-3 rounded-2xl text-white text-sm font-medium shadow-xl transition-all duration-300';
		document.body.appendChild(toast);
	}
	toast.textContent = message;
	toast.className = toast.className.replace(/bg-\S+/, '');
	toast.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500');
	toast.classList.remove('opacity-0', 'translate-y-4');
	toast.classList.add('opacity-100', 'translate-y-0');
	setTimeout(() => toast.classList.add('opacity-0', 'translate-y-4'), 8000);
}; */
