/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param notice Error notice.
 * @return Error messages.
 */
export const getErrorMessageFromNotice = ( notice: string | undefined ) => {
	if ( ! notice ) return '';

	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};
