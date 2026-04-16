/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { recordEvent } from 'tracks';
import type { Dispute } from 'wcpay/types/disputes';
import DraftEditor from './draft-editor';
import type { DefenseDraft, DefenseGap, GapResolution } from './types';
import './style.scss';

interface Props {
	dispute: Dispute;
	onUseDraft?: ( narrative: string ) => void;
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

/**
 * Roughly estimates how much the narrative has diverged from the original,
 * expressed as a percentage. Uses a length-plus-common-prefix heuristic so
 * the MVP avoids pulling in a Levenshtein dependency — we only need an
 * order-of-magnitude signal for analytics.
 */
const computeEditDistancePercent = (
	original: string,
	current: string
): number => {
	if ( ! original ) {
		return current.length > 0 ? 100 : 0;
	}
	let commonPrefix = 0;
	const max = Math.min( original.length, current.length );
	while (
		commonPrefix < max &&
		original[ commonPrefix ] === current[ commonPrefix ]
	) {
		commonPrefix++;
	}
	const divergence =
		Math.abs( original.length - current.length ) +
		( original.length - commonPrefix );
	return Math.min(
		100,
		Math.round( ( divergence / original.length ) * 100 )
	);
};

export const DisputeDefenderPanel: React.FC< Props > = ( {
	dispute,
	onUseDraft,
} ) => {
	// All three pieces of state are collapsed into a single setState call so
	// that completion updates (loading=false + draft/error) land in a single
	// React commit, even after an `await` boundary where React 18 no longer
	// auto-batches.
	const [ state, setState ] = useState< PanelState >( initialState );
	const { isLoading, draft, error } = state;

	// Editor state. `narrative` is the working copy that reflects user edits
	// and toggle-driven sentence swaps; `draft.narrative` stays frozen as the
	// original so we can compute edit distance later.
	const [ narrative, setNarrative ] = useState< string >( '' );
	const [ resolutions, setResolutions ] = useState<
		Record< string, GapResolution >
	>( {} );
	const [ orphanWarning, setOrphanWarning ] = useState< boolean >( false );
	const [ clipboardNotice, setClipboardNotice ] =
		useState< boolean >( false );

	const isEnabled =
		dispute.reason === 'fraudulent' &&
		!! wcpaySettings?.featureFlags?.isDisputeDefenderEnabled;

	// Seed the editor state whenever a fresh draft arrives.
	useEffect( () => {
		if ( ! draft ) {
			return;
		}
		setNarrative( draft.narrative );
		const initialResolutions: Record< string, GapResolution > = {};
		draft.gaps.forEach( ( gap ) => {
			initialResolutions[ gap.id ] = gap.default_resolution || 'provide';
		} );
		setResolutions( initialResolutions );
		setOrphanWarning( false );
		setClipboardNotice( false );
	}, [ draft ] );

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

	const handleResolutionChange = (
		gap: DefenseGap,
		nextResolution: GapResolution
	) => {
		const currentResolution = resolutions[ gap.id ] || 'provide';
		if ( currentResolution === nextResolution ) {
			return;
		}

		recordEvent( 'wcpay_dispute_defender_gap_resolved', {
			gap_id: gap.id,
			resolution: nextResolution,
		} );

		setResolutions( ( prev ) => ( {
			...prev,
			[ gap.id ]: nextResolution,
		} ) );

		const oldSentence =
			currentResolution === 'provide'
				? gap.sentences.with_evidence
				: gap.sentences.substituted;
		const newSentence =
			nextResolution === 'provide'
				? gap.sentences.with_evidence
				: gap.sentences.substituted;

		// Attempt an in-place swap; if the user has edited the sentence out
		// of existence we fall back to appending plus a visible warning.
		if ( narrative.includes( oldSentence ) ) {
			setNarrative( narrative.replace( oldSentence, newSentence ) );
			return;
		}

		setNarrative(
			narrative.endsWith( '\n' ) || narrative.length === 0
				? narrative + newSentence
				: `${ narrative } ${ newSentence }`
		);
		setOrphanWarning( true );
	};

	const handleUseDraft = () => {
		const originalNarrative = draft?.narrative || '';
		const editDistancePercent = computeEditDistancePercent(
			originalNarrative,
			narrative
		);

		recordEvent( 'wcpay_dispute_defender_draft_submitted', {
			dispute_id: dispute.id,
			narrative_length: narrative.length,
			edit_distance_percent: editDistancePercent,
		} );

		if ( onUseDraft ) {
			onUseDraft( narrative );
			return;
		}

		// Fallback: copy to clipboard and surface a brief confirmation so
		// the merchant still has a way to move the text into the existing
		// evidence form while the wiring for that form lands.
		if (
			typeof navigator !== 'undefined' &&
			navigator.clipboard &&
			typeof navigator.clipboard.writeText === 'function'
		) {
			navigator.clipboard.writeText( narrative ).catch( () => {
				// Clipboard can reject when the page isn't focused; we
				// still flip the notice so the user knows the action ran.
			} );
		}
		setClipboardNotice( true );
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
				<DraftEditor
					narrative={ narrative }
					onNarrativeChange={ setNarrative }
					gaps={ draft.gaps }
					resolutions={ resolutions }
					onResolutionChange={ handleResolutionChange }
					orphanWarning={ orphanWarning }
					onDismissOrphanWarning={ () => setOrphanWarning( false ) }
					clipboardNotice={ clipboardNotice }
					onDismissClipboardNotice={ () =>
						setClipboardNotice( false )
					}
					onUseDraft={ handleUseDraft }
					isBusy={ isLoading }
				/>
			) }
		</div>
	);
};

export default DisputeDefenderPanel;
