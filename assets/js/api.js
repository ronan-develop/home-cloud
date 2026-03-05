export async function apiFetch(url, options = {}) {
	const getToken = (window.HC && typeof window.HC.getToken === 'function')
		? window.HC.getToken
		: async () => '';
	let token = '';
	try {
		token = await getToken();
	} catch (err) {
		console.debug('apiFetch: no token provider', err);
	}
	const authHeader = token ? { 'Authorization': 'Bearer ' + token } : {};
	return fetch(url, {
		...options,
		headers: {
			...authHeader,
			'Accept': 'application/json',
			...options.headers,
		},
	});
}
