/**
 * Formats a file size in bytes to a human-readable string
 * @param bytes - File size in bytes
 * @returns Formatted file size string (e.g., "3.1MB", "500KB")
 */
export const formatFileSize = ( bytes: number ): string => {
	if ( bytes === 0 ) return '0B';
	
	const k = 1024;
	const sizes = [ 'B', 'KB', 'MB', 'GB' ];
	const i = Math.floor( Math.log( bytes ) / Math.log( k ) );
	
	const size = bytes / Math.pow( k, i );
	const formattedSize = size.toFixed( 1 );
	
	return formattedSize + sizes[ i ];
};

/**
 * Extracts the file extension from a filename
 * @param filename - The filename to extract extension from
 * @returns The file extension (e.g., ".jpg", ".pdf") or empty string if no extension
 */
export const getFileExtension = ( filename: string ): string => {
	const lastDotIndex = filename.lastIndexOf( '.' );
	return lastDotIndex !== -1 ? filename.slice( lastDotIndex ) : '';
};

/**
 * Formats a file name with size, returning separate parts for CSS-based truncation.
 * 
 * @param fileName - The original file name
 * @param fileSize - The file size in bytes
 * @returns Object with namePart and extensionSizePart for flexible CSS styling
 */
export const formatFileNameWithSize = (
	fileName: string,
	fileSize: number
): { namePart: string; extensionSizePart: string } => {
	// Extract file extension
	const lastDotIndex = fileName.lastIndexOf( '.' );
	const extension = lastDotIndex !== -1 ? fileName.substring( lastDotIndex ) : '';
	const nameWithoutExtension = lastDotIndex !== -1 
		? fileName.substring( 0, lastDotIndex ) 
		: fileName;

	// Format file size
	const formatFileSize = ( bytes: number ): string => {
		const mb = bytes / ( 1024 * 1024 );
		const kb = bytes / 1024;
		
		if ( mb >= 1 ) {
			// Show decimal only if not a whole number
			return mb % 1 === 0 ? `${ mb }MB` : `${ mb.toFixed( 1 ) }MB`;
		} else {
			// Show decimal only if not a whole number
			return kb % 1 === 0 ? `${ kb }KB` : `${ kb.toFixed( 1 ) }KB`;
		}
	};

	// Return separate parts for CSS-based truncation
	const formattedSize = formatFileSize( fileSize );
	const extensionSizePart = extension ? `${ extension } (${ formattedSize })` : `(${ formattedSize })`;
	
	return {
		namePart: nameWithoutExtension,
		extensionSizePart: extensionSizePart
	};
}; 