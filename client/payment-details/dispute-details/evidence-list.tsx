/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import type { IssuerEvidence } from 'wcpay/types/disputes';
import { useFiles } from 'wcpay/data';
import Loadable from 'wcpay/components/loadable';
import { NAMESPACE } from 'wcpay/data/constants';
import { FileContent } from 'wcpay/data/files/types';

interface Props {
	issuerEvidence: IssuerEvidence | null;
}
const TextEvidence: React.FC< {
	evidence: string;
} > = ( { evidence } ): JSX.Element => (
	// eslint-disable-next-line jsx-a11y/anchor-is-valid
	<a
		href={ URL.createObjectURL(
			new Blob( [ evidence ], { type: 'text/plain' } )
		) }
		className="dispute-evidence-link"
		download="evidence.txt"
	>
		{ __( 'Evidence.txt', 'woocommerce-payments' ) }
	</a>
);

const FileEvidence: React.FC< {
	fileId: string;
} > = ( { fileId } ) => {
	const { file, isLoading } = useFiles( fileId );
	const { createNotice } = useDispatch( 'core/notices' );
	const [ isDownloading, setIsDownloading ] = React.useState( false );

	const onDownload = async ( e: React.MouseEvent< HTMLAnchorElement > ) => {
		e.preventDefault();
		if ( ! file || ! file.id || isDownloading ) {
			return;
		}
		try {
			setIsDownloading( true );
			const downloadRequest = await apiFetch< FileContent >( {
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
			{
				/* eslint-disable jsx-a11y/anchor-is-valid */
				file && file.id ? (
					<a
						href="#"
						className={ classNames( {
							'dispute-evidence-link': true,
							'dispute-evidence-link--downloading': isDownloading,
						} ) }
						onClick={ onDownload }
					>
						{ file?.title || file.filename }
					</a>
				) : (
					<></>
				)
			}
		</Loadable>
	);
};

const EvidenceList: React.FC< Props > = ( { issuerEvidence } ) => {
	if (
		! issuerEvidence ||
		! issuerEvidence.file_evidence.length ||
		! issuerEvidence.text_evidence
	) {
		return <></>;
	}

	return (
		<div className="dispute-evidence">
			<div className="dispute-evidence__header">
				{ __( 'Issuer Evidence:', 'woocommercts' ) }
			</div>
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
		</div>
	);
};

export default EvidenceList;
