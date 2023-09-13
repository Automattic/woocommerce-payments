/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { download, Icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { IssuerEvidence } from 'wcpay/types/disputes';
import { useFiles } from 'wcpay/data';
import Loadable from 'wcpay/components/loadable';

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
		className="wcpay-text-evidence"
		download="evidence.txt"
	>
		<Icon icon={ download } size={ 16 } />
		{ __( 'Evidence.txt', 'woocommerce-payments' ) }
	</a>
);

const FileEvidence: React.FC< {
	fileId: string;
} > = ( { fileId } ) => {
	const { file, isLoading } = useFiles( fileId );

	return (
		<Loadable
			isLoading={ isLoading }
			placeholder={ __( 'Loading', 'woocommerce-payments' ) }
		>
			{
				/* eslint-disable jsx-a11y/anchor-is-valid */
				file && (
					<a href="#" className="wcpay-text-evidence">
						<Icon icon={ download } size={ 16 } />
						{ file?.title || file.filename } ({ file.type })
					</a>
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
			{ issuerEvidence.file_evidence.map( ( fileId: string, i: any ) => (
				// eslint-disable-next-line react/jsx-key
				<FileEvidence fileId={ fileId } />
			) ) }
			{ issuerEvidence.text_evidence && (
				<TextEvidence evidence={ issuerEvidence.text_evidence } />
			) }
		</>
	);
};

export default EvidenceList;
