/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import { getFileUrl } from 'wcpay/utils';

interface FileUploadProps {
	fileName: string;
	showPreview?: boolean;
}

const FileUploadPreview = ( {
	fileName,
	showPreview,
}: FileUploadProps ): JSX.Element => {
	const url = showPreview ? getFileUrl( fileName ) : '';

	return (
		<>
			{ url && (
				<img
					src={ url }
					style={ { maxWidth: 100, marginTop: 12 } }
					alt={ fileName }
				/>
			) }

			{ ! showPreview && fileName && (
				<span className="upload-message">{ fileName }</span>
			) }
		</>
	);
};

export default FileUploadPreview;
