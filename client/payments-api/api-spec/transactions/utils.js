/** @format */

export function getTransactionsResourcePage( resourceName ) {
	const pageIndex = getPageIndex( resourceName );
	const hyphenAfterIndex = resourceName.indexOf( '-', pageIndex );

	const page = resourceName.substring( pageIndex, hyphenAfterIndex );
	const pageInt = parseInt( page, 10 );

	// Default to page 0 (doesn't exist).
	// TODO: would it make more sense to throw an exception if parsing fails?
	return NaN === pageInt ? 0 : pageInt;
}

export function getTransactionsResourcePerPage( resourceName ) {
	const perPageIndex = getPerPageIndex( resourceName );
	const perPage = resourceName.substring( perPageIndex );
	const perPageInt = parseInt( perPage, 10 );

	// Default to 0 posts per page (doesn't happen).
	// TODO: would it make more sense to throw an exception if parsing fails?
	return NaN === perPageInt ? 0 : perPageInt;
}

export function getPageIndex( resourceName ) {
	return resourceName.indexOf( '-', resourceName.indexOf( '-', resourceName.indexOf( '-' ) + 1 ) + 1 ) + 1;
}

export function getPerPageIndex( resourceName ) {
	return resourceName.indexOf( '-', resourceName.indexOf( '-', getPageIndex( resourceName ) ) + 1 ) + 1;
}
