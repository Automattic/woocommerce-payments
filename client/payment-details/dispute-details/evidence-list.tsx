/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { Button, PanelBody } from '@wordpress/components';
import { Icon, page } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { IssuerEvidence } from 'wcpay/types/disputes';
import { useFiles } from 'wcpay/data';
import Loadable from 'wcpay/components/loadable';
import { NAMESPACE } from 'wcpay/data/constants';
import { FileDownload } from 'wcpay/data/files/types';

const TextEvidence: React.FC< {
	evidence: string;
} > = ( { evidence } ) => {
	const onClick = () => {
		const link = document.createElement( 'a' );
		link.href = URL.createObjectURL(
			new Blob( [ evidence ], { type: 'text/plain' } )
		);
		link.download = 'evidence.txt';
		link.click();
	};

	return (
		<Button
			variant="secondary"
			onClick={ onClick }
			isSmall={ true }
			icon={ <Icon icon={ page } /> }
		>
			{
				/* translators: Default filename for issuer evidence on a dispute */
				__( 'Evidence.txt', 'woocommerce-payments' )
			}
		</Button>
	);
};

const FileEvidence: React.FC< {
	fileId: string;
} > = ( { fileId } ) => {
	const { file, isLoading } = useFiles( fileId );
	const { createNotice } = useDispatch( 'core/notices' );
	const [ isDownloading, setIsDownloading ] = React.useState( false );

	const onClick = async () => {
		if ( ! file || ! file.id || isDownloading ) {
			return;
		}
		try {
			setIsDownloading( true );
			const downloadRequest = await apiFetch< FileDownload >( {
				path: `${ NAMESPACE }/file/${ encodeURI( file.id ) }/content`,
				method: 'GET',
			} );

			const link = document.createElement( 'a' );
			link.href =
				'data:application/octect-stream;base64,' +
				downloadRequest.file_content;
			link.download = file.filename;
			link.click();
		} catch ( exception ) {
			createNotice(
				'error',
				__( 'Error downloading file', 'woocommerce-payments' )
			);
		}
		setIsDownloading( false );
	};

	return (
		<Loadable
			isLoading={ isLoading }
			placeholder={ __( 'Loading', 'woocommerce-payments' ) }
		>
			{ file && file.id ? (
				<Button
					variant="secondary"
					isBusy={ isDownloading }
					disabled={ isDownloading }
					isSmall={ true }
					icon={ <Icon icon={ page } /> }
					onClick={ onClick }
				>
					{ file?.title || file.filename }
				</Button>
			) : (
				<></>
			) }
		</Loadable>
	);
};

interface Props {
	issuerEvidence: IssuerEvidence | null;
}

const IssuerEvidenceList: React.FC< Props > = ( { issuerEvidence } ) => {
	if (
		! issuerEvidence ||
		! issuerEvidence.file_evidence.length ||
		! issuerEvidence.text_evidence
	) {
		return <></>;
	}

	return (
		<PanelBody
			className="dispute-evidence"
			title={ __( 'Issuer evidence', 'woocommerce' ) }
			initialOpen={ false }
		>
			<ul className="dispute-evidence__list">
				{ issuerEvidence.text_evidence && (
					<li className="dispute-evidence__list-item">
						<TextEvidence
							evidence={ issuerEvidence.text_evidence }
						/>
					</li>
				) }
				{ issuerEvidence.file_evidence.map(
					( fileId: string, i: any ) => (
						<li className="dispute-evidence__list-item" key={ i }>
							<FileEvidence fileId={ fileId } />
						</li>
					)
				) }
			</ul>
		</PanelBody>
	);
};

export default IssuerEvidenceList;
