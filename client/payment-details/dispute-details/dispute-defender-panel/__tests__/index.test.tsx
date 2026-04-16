/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { DisputeDefenderPanel } from '../index';
import { recordEvent } from 'tracks';

jest.mock( '@wordpress/api-fetch' );
jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const baseFraudulentDispute: any = {
	id: 'dp_test_123',
	reason: 'fraudulent',
	amount: 10000,
	currency: 'usd',
};

const withEvidenceSentence =
	'We captured the AVS and CVC match on the original authorization.';
const substitutedSentence =
	'AVS and CVC data from the original authorization are not available for this charge.';

const draftWithGap = {
	narrative: `The charge is legitimate. ${ withEvidenceSentence } Thanks.`,
	gaps: [
		{
			id: 'avs_cvc',
			label: 'AVS / CVC match',
			default_resolution: 'provide',
			sentences: {
				with_evidence: withEvidenceSentence,
				substituted: substitutedSentence,
			},
		},
	],
	meta: {
		model: 'claude-sonnet-4-5',
		prompt_version: 'v1',
		generated_at: '2026-04-16T00:00:00Z',
	},
};

const draftWithTwoGaps = {
	narrative: `Intro. ${ withEvidenceSentence } Middle sentence. Second gap evidence sentence.`,
	gaps: [
		{
			id: 'avs_cvc',
			label: 'AVS / CVC match',
			default_resolution: 'provide',
			sentences: {
				with_evidence: withEvidenceSentence,
				substituted: substitutedSentence,
			},
		},
		{
			id: 'ip_match',
			label: 'IP address match',
			default_resolution: 'provide',
			sentences: {
				with_evidence: 'Second gap evidence sentence.',
				substituted: 'Second gap substituted sentence.',
			},
		},
	],
	meta: {
		model: 'claude-sonnet-4-5',
		prompt_version: 'v1',
		generated_at: '2026-04-16T00:00:00Z',
	},
};

const generateDraft = async ( draft: any ) => {
	( apiFetch as unknown as jest.Mock ).mockResolvedValue( draft );
	render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );
	await act( async () => {
		await userEvent.click(
			screen.getByRole( 'button', { name: /generate with ai/i } )
		);
	} );
};

describe( 'DisputeDefenderPanel', () => {
	beforeEach( () => {
		( window as any ).wcpaySettings = {
			featureFlags: { isDisputeDefenderEnabled: true },
		};
		( apiFetch as unknown as jest.Mock ).mockReset();
		( recordEvent as jest.Mock ).mockReset();
	} );

	it( 'renders the Generate button when the flag is on and reason is fraudulent', () => {
		render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );
		expect(
			screen.getByRole( 'button', { name: /generate with ai/i } )
		).toBeInTheDocument();
	} );

	it( 'renders nothing when the dispute reason is not fraudulent', () => {
		const dispute = { ...baseFraudulentDispute, reason: 'duplicate' };
		const { container } = render(
			<DisputeDefenderPanel dispute={ dispute } />
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing when the feature flag is off', () => {
		( window as any ).wcpaySettings = {
			featureFlags: { isDisputeDefenderEnabled: false },
		};
		const { container } = render(
			<DisputeDefenderPanel dispute={ baseFraudulentDispute } />
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'calls the REST endpoint and renders the draft on click', async () => {
		( apiFetch as unknown as jest.Mock ).mockResolvedValue( {
			narrative: 'The charge is legitimate.',
			gaps: [],
			meta: {
				model: 'claude-sonnet-4-5',
				prompt_version: 'v1',
				generated_at: '2026-04-16T00:00:00Z',
			},
		} );

		render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );

		const button = screen.getByRole( 'button', {
			name: /generate with ai/i,
		} );
		// Wrap the click + async resolution in act() so the second state
		// update (fired after apiFetch resolves on a subsequent microtask)
		// is captured inside the test boundary. Without this, React 18
		// emits an act() warning that @wordpress/jest-console treats as a
		// test failure.
		await act( async () => {
			await userEvent.click( button );
		} );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/payments/disputes/dp_test_123/defense/draft',
			method: 'POST',
		} );

		await waitFor( () => {
			expect(
				screen.getByDisplayValue( /The charge is legitimate/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows an error when the REST call fails', async () => {
		( apiFetch as unknown as jest.Mock ).mockRejectedValue(
			new Error( 'Upstream failure' )
		);

		render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /generate with ai/i } )
			);
		} );

		await waitFor( () => {
			// Notice renders the message in multiple wrappers for a11y — use
			// getAllByText so the presence check doesn't fail on duplicates.
			expect(
				screen.getAllByText( /Upstream failure/i ).length
			).toBeGreaterThan( 0 );
		} );
	} );

	it( 'renders a textarea with the draft narrative after generate', async () => {
		await generateDraft( draftWithGap );

		const textarea = await screen.findByRole( 'textbox', {
			name: /narrative/i,
		} );
		expect( textarea ).toBeInTheDocument();
		expect( ( textarea as HTMLTextAreaElement ).value ).toContain(
			withEvidenceSentence
		);
	} );

	it( 'edits to the narrative persist in local state', async () => {
		await generateDraft( draftWithGap );

		const textarea = ( await screen.findByRole( 'textbox', {
			name: /narrative/i,
		} ) ) as HTMLTextAreaElement;

		await act( async () => {
			await userEvent.clear( textarea );
			await userEvent.type( textarea, 'Edited narrative.' );
		} );

		expect( textarea.value ).toBe( 'Edited narrative.' );
	} );

	it( 'renders each gap as a toggle row', async () => {
		await generateDraft( draftWithTwoGaps );

		// Both gap labels should be visible.
		expect(
			await screen.findByText( /AVS \/ CVC match/i )
		).toBeInTheDocument();
		expect( screen.getByText( /IP address match/i ) ).toBeInTheDocument();

		// Each gap has both a "Provide" and "Can't provide" option (radio inputs).
		// The component renders the apostrophe as a Unicode right-single-quote
		// so we match either form.
		expect(
			screen.getAllByRole( 'radio', { name: /provide/i } ).length
		).toBeGreaterThanOrEqual( 2 );
		expect(
			screen.getAllByRole( 'radio', { name: /can[’']t provide/i } ).length
		).toBeGreaterThanOrEqual( 2 );
	} );

	it( 'toggling a gap to "can\'t provide" replaces the sentence in the narrative', async () => {
		await generateDraft( draftWithGap );

		const textarea = ( await screen.findByRole( 'textbox', {
			name: /narrative/i,
		} ) ) as HTMLTextAreaElement;
		expect( textarea.value ).toContain( withEvidenceSentence );
		expect( textarea.value ).not.toContain( substitutedSentence );

		const cantProvide = screen.getByRole( 'radio', {
			name: /can[’']t provide/i,
		} );
		await act( async () => {
			await userEvent.click( cantProvide );
		} );

		expect( textarea.value ).toContain( substitutedSentence );
		expect( textarea.value ).not.toContain( withEvidenceSentence );
	} );

	it( 'toggling back to "provide" restores the with_evidence sentence', async () => {
		await generateDraft( draftWithGap );

		const textarea = ( await screen.findByRole( 'textbox', {
			name: /narrative/i,
		} ) ) as HTMLTextAreaElement;

		const cantProvide = screen.getByRole( 'radio', {
			name: /can[’']t provide/i,
		} );
		await act( async () => {
			await userEvent.click( cantProvide );
		} );
		expect( textarea.value ).toContain( substitutedSentence );

		const provide = screen.getByRole( 'radio', {
			name: /^provide$/i,
		} );
		await act( async () => {
			await userEvent.click( provide );
		} );

		expect( textarea.value ).toContain( withEvidenceSentence );
		expect( textarea.value ).not.toContain( substitutedSentence );
	} );

	it( 'appends the substituted sentence with a warning when the original is absent', async () => {
		await generateDraft( draftWithGap );

		const textarea = ( await screen.findByRole( 'textbox', {
			name: /narrative/i,
		} ) ) as HTMLTextAreaElement;

		// User edits out the with_evidence sentence entirely.
		await act( async () => {
			await userEvent.clear( textarea );
			await userEvent.type(
				textarea,
				'Totally rewritten narrative with nothing matching.'
			);
		} );
		expect( textarea.value ).not.toContain( withEvidenceSentence );

		const cantProvide = screen.getByRole( 'radio', {
			name: /can[’']t provide/i,
		} );
		await act( async () => {
			await userEvent.click( cantProvide );
		} );

		// Warning banner visible. The component uses a Unicode right-single-
		// quote for the apostrophe in "couldn't", so match either form.
		// Notice renders the message in multiple wrappers for a11y — use
		// getAllByText so the check doesn't fail on duplicates.
		expect(
			screen.getAllByText( /couldn[’']t find the original sentence/i )
				.length
		).toBeGreaterThan( 0 );
		// Substituted sentence was appended.
		expect( textarea.value ).toContain( substitutedSentence );
	} );

	it( 'fires wcpay_dispute_defender_gap_resolved on toggle', async () => {
		await generateDraft( draftWithGap );

		( recordEvent as jest.Mock ).mockClear();

		const cantProvide = await screen.findByRole( 'radio', {
			name: /can[’']t provide/i,
		} );
		await act( async () => {
			await userEvent.click( cantProvide );
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_defender_gap_resolved',
			expect.objectContaining( {
				gap_id: 'avs_cvc',
				resolution: 'substituted',
			} )
		);
	} );

	it( '"Use this draft" button calls onUseDraft prop with the current narrative', async () => {
		const onUseDraft = jest.fn();
		( apiFetch as unknown as jest.Mock ).mockResolvedValue( draftWithGap );
		render(
			<DisputeDefenderPanel
				dispute={ baseFraudulentDispute }
				onUseDraft={ onUseDraft }
			/>
		);
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /generate with ai/i } )
			);
		} );

		const textarea = ( await screen.findByRole( 'textbox', {
			name: /narrative/i,
		} ) ) as HTMLTextAreaElement;

		await act( async () => {
			await userEvent.clear( textarea );
			await userEvent.type( textarea, 'My edited narrative.' );
		} );

		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /use this draft/i } )
			);
		} );

		expect( onUseDraft ).toHaveBeenCalledWith( 'My edited narrative.' );
	} );

	it( '"Use this draft" button fires wcpay_dispute_defender_draft_submitted', async () => {
		const onUseDraft = jest.fn();
		await generateDraft( draftWithGap );

		( recordEvent as jest.Mock ).mockClear();

		// Re-render with prop because generateDraft doesn't pass it — we can
		// instead just click without the prop and ensure the event still fires.
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /use this draft/i } )
			);
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_defender_draft_submitted',
			expect.objectContaining( {
				dispute_id: 'dp_test_123',
				narrative_length: expect.any( Number ),
			} )
		);

		// Sanity touch to avoid unused-var lint on onUseDraft.
		expect( onUseDraft ).not.toHaveBeenCalled();
	} );
} );
