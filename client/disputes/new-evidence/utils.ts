/**
 * True when a value is non-empty (and, for objects, has a meaningful leaf).
 * Shared by recommendations.ts and evidence-field-status.ts so "provided vs
 * missing" stays consistent.
 */
export const hasMeaningfulValue = ( value: unknown ): boolean => {
	if ( value === undefined || value === null ) {
		return false;
	}
	if ( typeof value === 'string' ) {
		return value.trim().length > 0;
	}
	if ( typeof value === 'object' ) {
		return Object.values( value as Record< string, unknown > ).some(
			hasMeaningfulValue
		);
	}
	return Boolean( value );
};

/**
 * apiFetch rejects with the REST error body (`{ code, message, data }`), not an
 * `Error`, so `String( err )` would render "[object Object]".
 */
export const getErrorMessage = ( err: unknown ): string => {
	const message = ( err as { message?: unknown } | null )?.message;
	return typeof message === 'string' ? message : String( err );
};

/**
 * Formats a size in bytes as KB or MB, with one decimal place when needed.
 */
export const formatFileSize = ( bytes: number ): string => {
	const mb = bytes / ( 1024 * 1024 );
	const kb = bytes / 1024;

	if ( mb >= 1 ) {
		return mb % 1 === 0 ? `${ mb }MB` : `${ mb.toFixed( 1 ) }MB`;
	}
	return kb % 1 === 0 ? `${ kb }KB` : `${ kb.toFixed( 1 ) }KB`;
};

/**
 * The most evidence, in bytes, that a dispute's files may add up to.
 */
export const evidenceTotalSizeLimit = 4500000;

/**
 * Says which limit, if any, a new evidence file would break.
 *
 * Each file is uploaded to the store before it reaches Stripe, so it must also
 * fit the store's own upload limit. A limit of 0 or less means PHP reported
 * none, so only the dispute total applies.
 */
export const getEvidenceFileSizeError = (
	fileSize: number,
	uploadedTotal: number,
	maxUploadSize?: number
): 'upload_limit' | 'dispute_total' | null => {
	if ( maxUploadSize && maxUploadSize > 0 && fileSize > maxUploadSize ) {
		return 'upload_limit';
	}
	if ( uploadedTotal + fileSize > evidenceTotalSizeLimit ) {
		return 'dispute_total';
	}
	return null;
};

/**
 * Formats a file name with size, returning separate parts for CSS-based truncation.
 *
 * @param fileName - The original file name
 * @param fileSize - The file size in bytes
 * @return Object with namePart and extensionSizePart for flexible CSS styling
 */
export const formatFileNameWithSize = (
	fileName: string,
	fileSize: number
): { namePart: string; extensionSizePart: string } => {
	// Extract file extension
	const lastDotIndex = fileName.lastIndexOf( '.' );
	const extension =
		lastDotIndex !== -1 ? fileName.substring( lastDotIndex ) : '';
	const nameWithoutExtension =
		lastDotIndex !== -1 ? fileName.substring( 0, lastDotIndex ) : fileName;

	const formattedSize = formatFileSize( fileSize );

	// Return separate parts for CSS-based truncation
	return {
		namePart: nameWithoutExtension,
		extensionSizePart: extension
			? `${ extension } (${ formattedSize })`
			: ` (${ formattedSize })`,
	};
};
