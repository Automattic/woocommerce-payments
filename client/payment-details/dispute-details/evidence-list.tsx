/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

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

	const onDownload = async ( e: React.MouseEvent< HTMLAnchorElement > ) => {
		e.preventDefault();
		if ( ! file || ! file.id ) {
			return;
		}
		try {
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
						className="dispute-evidence-link"
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
		return (
			<span>
				{ __( 'No evidence available', 'woocommerce-payments' ) }
			</span>
		);
	}

	return (
		<>
			{ issuerEvidence.text_evidence && (
				<TextEvidence evidence={ issuerEvidence.text_evidence } />
			) }
			{ issuerEvidence.file_evidence.map( ( fileId: string, i: any ) => (
				<FileEvidence key={ i } fileId={ fileId } />
			) ) }
		</>
	);
};

export default EvidenceList;
