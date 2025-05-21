/** @format **/

/**
 * External dependencies
 */
import React from 'react';

interface UploadErrorProps {
	error?: string;
}

const FileUploadError = ( { error }: UploadErrorProps ): JSX.Element => {
	return <span className="upload-message is-destructive">{ error }</span>;
};

export default FileUploadError;
