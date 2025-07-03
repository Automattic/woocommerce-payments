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
 * Formats a file name with size, truncating the name if it's too long.
 * 
 * @param fileName - The original file name
 * @param fileSize - The file size in bytes
 * @returns Formatted string like "My file name... .jpg (3.1mb)" or "Short name.jpg (3.0mb)"
 */
export const formatFileNameWithSize = (
	fileName: string,
	fileSize: number
): string => {
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
			return `${ mb.toFixed( 1 ) }mb`;
		} else {
			return `${ kb.toFixed( 1 ) }kb`;
		}
	};

	// Truncate name if it's longer than 25 characters (i.e., 26+ characters)
	const maxNameLength = 25;
	let displayName = nameWithoutExtension;
	let displayExtension = extension;
	
	if ( nameWithoutExtension.length > maxNameLength ) {
		displayName = nameWithoutExtension.substring( 0, maxNameLength ) + '...';
		// Add space before extension when truncating
		displayExtension = extension ? ' ' + extension : '';
	}

	// Combine name, extension, and size
	const formattedSize = formatFileSize( fileSize );
	return `${ displayName }${ displayExtension } (${ formattedSize })`;
}; 