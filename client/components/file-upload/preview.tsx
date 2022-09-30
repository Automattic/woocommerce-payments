/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies.
 */
import { NAMESPACE } from 'wcpay/data/constants';

interface FileUploadProps {
	fileName: string;
	showPreview?: boolean;
}

const FileUploadPreview = ( {
	fileName,
	showPreview,
}: FileUploadProps ): JSX.Element => {
	const url =
		wcpaySettings.restUrl + NAMESPACE.substring( 1 ) + '/file/' + fileName;

	return (
		<>
			{ showPreview && fileName && (
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
