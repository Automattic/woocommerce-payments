/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { useUserPreferences } from '@woocommerce/data';
import { recordEvent } from 'tracks';

/**
 * Internal dependencies
 */
import ReportFeedbackSurvey from '..';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '@woocommerce/data', () => ( {
	useUserPreferences: jest.fn(),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;
const mockUseUserPreferences = useUserPreferences as jest.MockedFunction<
	typeof useUserPreferences
>;
const mockRecordEvent = recordEvent as jest.MockedFunction<
	typeof recordEvent
>;
const mockUpdateUserPreferences = jest.fn();

declare const global: {
	wcpaySettings: {
		featureFlags: Record< string, boolean >;
	};
};

const renderSurvey = () => render( <ReportFeedbackSurvey /> );

describe( 'ReportFeedbackSurvey', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		global.wcpaySettings = {
			...( global.wcpaySettings ?? {} ),
			featureFlags: {
				...( global.wcpaySettings?.featureFlags ?? {} ),
				reportsArea: true,
			},
		};

		mockUseUserPreferences.mockReturnValue( {
			updateUserPreferences: mockUpdateUserPreferences,
			wc_payments_reports_feedback_dismissed: undefined,
		} as any );
		mockApiFetch.mockResolvedValue( {} );
	} );

	it( 'renders collapsed and records the view event once', async () => {
		const { rerender } = renderSurvey();

		expect(
			screen.getByText(
				'Did this report give you the information you needed?'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'What did you use this report for? (optional)'
			)
		).not.toBeInTheDocument();

		await waitFor( () => {
			expect( mockRecordEvent ).toHaveBeenCalledWith(
				'wcpay_reports_feedback_view',
				{ report_type: 'balance' }
			);
		} );

		rerender( <ReportFeedbackSurvey /> );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'expands for both thumbs and does not auto-submit', async () => {
		renderSurvey();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'This report was helpful' } )
		);

		expect(
			screen.getByLabelText(
				'What did you use this report for? (optional)'
			)
		).toBeInTheDocument();
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_thumbs_up',
			{ report_type: 'balance' }
		);
		expect( mockApiFetch ).not.toHaveBeenCalled();

		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'This report was not helpful',
			} )
		);

		expect(
			screen.getByLabelText( "What's missing? (optional)" )
		).toBeInTheDocument();
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_thumbs_down',
			{ report_type: 'balance' }
		);
		expect( mockApiFetch ).not.toHaveBeenCalled();
	} );

	it( 'collapses and clears without persisting when cancelled', async () => {
		renderSurvey();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'This report was helpful' } )
		);
		await userEvent.type(
			screen.getByLabelText(
				'What did you use this report for? (optional)'
			),
			'checking balance'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Cancel' } )
		);

		expect(
			screen.queryByLabelText(
				'What did you use this report for? (optional)'
			)
		).not.toBeInTheDocument();
		expect( mockUpdateUserPreferences ).not.toHaveBeenCalled();
		expect( mockRecordEvent ).not.toHaveBeenCalledWith(
			'wcpay_reports_feedback_dismiss',
			expect.anything()
		);
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_cancel',
			{ report_type: 'balance' }
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: 'This report was helpful' } )
		);

		expect(
			screen.getByLabelText(
				'What did you use this report for? (optional)'
			)
		).toHaveValue( '' );
	} );

	it( 'submits text feedback, persists dismissal, and hides on success', async () => {
		renderSurvey();

		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'This report was not helpful',
			} )
		);
		await userEvent.type(
			screen.getByLabelText( "What's missing? (optional)" ),
			'  missing export details  '
		);
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Send' } )
			);
		} );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/payments/survey/reports-feedback',
				method: 'POST',
				data: {
					rating: 'thumbs-down',
					comments: 'missing export details',
				},
			} );
		} );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_submit',
			{ report_type: 'balance', sentiment: 'down', has_text: true }
		);
		expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
			wc_payments_reports_feedback_dismissed: expect.any( Number ),
		} );
		await waitFor( () => {
			expect(
				screen.queryByText(
					'Did this report give you the information you needed?'
				)
			).not.toBeInTheDocument();
		} );
	} );

	it( 'submits without text', async () => {
		renderSurvey();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'This report was helpful' } )
		);
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Send' } )
			);
		} );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/payments/survey/reports-feedback',
				method: 'POST',
				data: {
					rating: 'thumbs-up',
					comments: '',
				},
			} );
		} );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_submit',
			{ report_type: 'balance', sentiment: 'up', has_text: false }
		);
	} );

	it( 'keeps the form open after submit error and allows retry', async () => {
		mockApiFetch
			.mockRejectedValueOnce( new Error( 'request failed' ) )
			.mockResolvedValueOnce( {} );
		renderSurvey();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'This report was helpful' } )
		);
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Send' } )
			);
		} );

		expect(
			await screen.findByText(
				'Your feedback could not be sent. Please try again.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
		expect(
			screen.getByLabelText(
				'What did you use this report for? (optional)'
			)
		).toBeInTheDocument();
		expect( mockUpdateUserPreferences ).not.toHaveBeenCalled();
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_submit_error',
			{ report_type: 'balance', sentiment: 'up', has_text: false }
		);

		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Send' } )
			);
		} );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledTimes( 2 );
			expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
				wc_payments_reports_feedback_dismissed: expect.any( Number ),
			} );
		} );
	} );

	it( 'persists dismissal and hides when closed', async () => {
		renderSurvey();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Dismiss feedback survey' } )
		);

		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_feedback_dismiss',
			{ report_type: 'balance' }
		);
		expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
			wc_payments_reports_feedback_dismissed: expect.any( Number ),
		} );
		expect(
			screen.queryByText(
				'Did this report give you the information you needed?'
			)
		).not.toBeInTheDocument();
	} );

	it( 'renders nothing and records no view event when already dismissed', () => {
		mockUseUserPreferences.mockReturnValue( {
			updateUserPreferences: mockUpdateUserPreferences,
			wc_payments_reports_feedback_dismissed: 1780000000,
		} as any );

		renderSurvey();

		expect(
			screen.queryByText(
				'Did this report give you the information you needed?'
			)
		).not.toBeInTheDocument();
		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );

	it( 'renders nothing and does not read preferences when reports area is disabled', () => {
		global.wcpaySettings.featureFlags.reportsArea = false;

		renderSurvey();

		expect(
			screen.queryByText(
				'Did this report give you the information you needed?'
			)
		).not.toBeInTheDocument();
		expect( mockUseUserPreferences ).not.toHaveBeenCalled();
		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );
} );
