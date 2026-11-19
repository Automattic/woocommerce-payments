/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param notice Error notice.
 * @return Error messages.
 */
export const getErrorMessageFromNotice = ( notice: string | undefined ) => {
	if ( ! notice ) return '';

	const parsed = new DOMParser().parseFromString(
		notice.trim(),
		'text/html'
	);
	return parsed.body.firstChild ? parsed.body.firstChild.textContent : '';
};
