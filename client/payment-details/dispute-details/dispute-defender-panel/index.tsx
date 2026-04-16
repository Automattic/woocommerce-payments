/** @format */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { recordEvent } from 'tracks';
import type { Dispute } from 'wcpay/types/disputes';
import './style.scss';

interface DefenseDraftMeta {
	model: string;
	prompt_version: string;
	generated_at: string;
}

interface DefenseGap {
	id: string;
	label: string;
	default_resolution: 'provide' | 'substituted';
	sentences: {
		with_evidence: string;
		substituted: string;
	};
}

interface DefenseDraft {
	narrative: string;
	gaps: DefenseGap[];
	meta: DefenseDraftMeta;
}

interface Props {
	dispute: Dispute;
}

interface PanelState {
	isLoading: boolean;
	draft: DefenseDraft | null;
	error: string | null;
}

const initialState: PanelState = {
	isLoading: false,
	draft: null,
	error: null,
};

export const DisputeDefenderPanel: React.FC< Props > = ( { dispute } ) => {
	// All three pieces of state are collapsed into a single setState call so
	// that completion updates (loading=false + draft/error) land in a single
	// React commit, even after an `await` boundary where React 18 no longer
	// auto-batches.
	const [ state, setState ] = useState< PanelState >( initialState );
	const { isLoading, draft, error } = state;

	const isEnabled =
		dispute.reason === 'fraudulent' &&
		!! wcpaySettings?.featureFlags?.isDisputeDefenderEnabled;

	if ( ! isEnabled ) {
		return null;
	}

	const handleGenerate = async () => {
		setState( { isLoading: true, draft: null, error: null } );
		recordEvent( 'wcpay_dispute_defender_generate_clicked', {
			dispute_id: dispute.id,
		} );

		try {
			const response = await apiFetch< DefenseDraft >( {
				path: `/wc/v3/payments/disputes/${ dispute.id }/defense/draft`,
				method: 'POST',
			} );
			setState( { isLoading: false, draft: response, error: null } );
		} catch ( err ) {
			const message =
				err instanceof Error
					? err.message
					: __(
							'Failed to generate a draft.',
							'woocommerce-payments'
					  );
			setState( { isLoading: false, draft: null, error: message } );
		}
	};

	return (
		<div className="dispute-defender-panel">
			<h3>{ __( 'Dispute Defender AI', 'woocommerce-payments' ) }</h3>
			<p>
				{ __(
					'Generate a draft response to this dispute based on the signals we already have. You always review before submitting.',
					'woocommerce-payments'
				) }
			</p>
			<Button
				variant="primary"
				onClick={ handleGenerate }
				isBusy={ isLoading }
				disabled={ isLoading }
			>
				{ __( 'Generate with AI', 'woocommerce-payments' ) }
			</Button>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ draft && (
				<pre className="dispute-defender-panel__draft-preview">
					{ JSON.stringify( draft, null, 2 ) }
				</pre>
			) }
		</div>
	);
};

export default DisputeDefenderPanel;
