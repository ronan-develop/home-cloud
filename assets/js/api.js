export async function apiFetch(url, options = {}) {
	const token = await window.HC.getToken();
	return fetch(url, {
		...options,
		headers: {
			'Authorization': 'Bearer ' + token,
			'Accept': 'application/json',
			...options.headers,
		},
	});
}
