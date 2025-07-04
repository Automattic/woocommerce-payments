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

	// Format file size
	const formatFileSize = ( bytes: number ): string => {
		const mb = bytes / ( 1024 * 1024 );
		const kb = bytes / 1024;

		if ( mb >= 1 ) {
			// Show decimal only if not a whole number
			return mb % 1 === 0 ? `${ mb }MB` : `${ mb.toFixed( 1 ) }MB`;
		}
		// Show decimal only if not a whole number
		return kb % 1 === 0 ? `${ kb }KB` : `${ kb.toFixed( 1 ) }KB`;
	};

	const formattedSize = formatFileSize( fileSize );

	// Return separate parts for CSS-based truncation
	return {
		namePart: nameWithoutExtension,
		extensionSizePart: extension
			? `${ extension } (${ formattedSize })`
			: ` (${ formattedSize })`,
	};
};
