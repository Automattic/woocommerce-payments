/** @format */

/**
 * Internal dependencies
 */
import { toggleEarlyFraudWarningDismissal } from '../early-fraud-warning-dismissal';
import { setEarlyFraudWarningDismissed } from 'wcpay/data/early-fraud-warnings/api';

jest.mock( 'wcpay/data/early-fraud-warnings/api', () => ( {
	setEarlyFraudWarningDismissed: jest.fn(),
} ) );

const mockSetDismissed = setEarlyFraudWarningDismissed as jest.Mock;

const renderBlock = ( dismissed = false ) => {
	document.body.innerHTML = `
		<div class="wcpay-fraud-risk-efw wcpay-fraud-risk-efw--actionable${
			dismissed ? ' wcpay-fraud-risk-efw--dismissed' : ''
		}">
			<p class="wcpay-fraud-risk-efw__dismissal">
				<button type="button" class="wcpay-efw-dismiss-toggle wcpay-efw-dismiss-toggle--dismiss" data-order-id="42" data-dismissed="1">Dismiss</button>
				<button type="button" class="wcpay-efw-dismiss-toggle wcpay-efw-dismiss-toggle--undo" data-order-id="42" data-dismissed="0">Undo dismissal</button>
			</p>
		</div>`;

	const block = document.querySelector(
		'.wcpay-fraud-risk-efw'
	) as HTMLElement;
	const dismissButton = document.querySelector(
		'.wcpay-efw-dismiss-toggle--dismiss'
	) as HTMLButtonElement;
	const undoButton = document.querySelector(
		'.wcpay-efw-dismiss-toggle--undo'
	) as HTMLButtonElement;

	return { block, dismissButton, undoButton };
};

describe( 'toggleEarlyFraudWarningDismissal', () => {
	beforeEach( () => {
		mockSetDismissed.mockReset();
	} );

	it( 'dismisses the warning and switches the block to its dismissed state', async () => {
		mockSetDismissed.mockResolvedValue( { dismissed: true } );
		const { block, dismissButton } = renderBlock();

		await toggleEarlyFraudWarningDismissal( dismissButton );

		expect( mockSetDismissed ).toHaveBeenCalledWith( 42, true );
		expect(
			block.classList.contains( 'wcpay-fraud-risk-efw--dismissed' )
		).toBe( true );
	} );

	it( 'restores the warning and switches the block back', async () => {
		mockSetDismissed.mockResolvedValue( { dismissed: false } );
		const { block, undoButton } = renderBlock( true );

		await toggleEarlyFraudWarningDismissal( undoButton );

		expect( mockSetDismissed ).toHaveBeenCalledWith( 42, false );
		expect(
			block.classList.contains( 'wcpay-fraud-risk-efw--dismissed' )
		).toBe( false );
	} );

	it( 'moves focus to the button that replaces the pressed one', async () => {
		mockSetDismissed.mockResolvedValue( { dismissed: true } );
		const { dismissButton, undoButton } = renderBlock();
		dismissButton.focus();

		await toggleEarlyFraudWarningDismissal( dismissButton );

		// eslint-disable-next-line @wordpress/no-global-active-element
		expect( document.activeElement ).toBe( undoButton );
	} );

	it( 'sends one request when the button is pressed twice', async () => {
		// eslint-disable-next-line @typescript-eslint/no-empty-function
		let resolve: ( value: { dismissed: boolean } ) => void = () => {};
		mockSetDismissed.mockReturnValue(
			new Promise( ( r ) => {
				resolve = r;
			} )
		);
		const { dismissButton } = renderBlock();

		const first = toggleEarlyFraudWarningDismissal( dismissButton );
		await toggleEarlyFraudWarningDismissal( dismissButton );
		resolve( { dismissed: true } );
		await first;

		expect( mockSetDismissed ).toHaveBeenCalledTimes( 1 );
		expect( dismissButton.disabled ).toBe( false );
	} );

	it( 'leaves the block unchanged and shows an error when the request fails', async () => {
		mockSetDismissed.mockRejectedValue( new Error( 'not actionable' ) );
		const { block, dismissButton } = renderBlock();

		await toggleEarlyFraudWarningDismissal( dismissButton );

		expect(
			block.classList.contains( 'wcpay-fraud-risk-efw--dismissed' )
		).toBe( false );
		const error = block.querySelector( '.wcpay-fraud-risk-efw__error' );
		expect( error?.getAttribute( 'role' ) ).toBe( 'alert' );
		expect( error?.textContent ).toBe(
			'There was an error updating the early fraud warning. Please try again later.'
		);
		expect( dismissButton.disabled ).toBe( false );
	} );

	it( 'returns focus to the pressed button when the request fails', async () => {
		const { dismissButton } = renderBlock();
		mockSetDismissed.mockImplementation( () => {
			// Browsers drop focus from a button once it is disabled; jsdom does not, and
			// ignores blur() on a disabled element, so re-enable it briefly to blur it.
			dismissButton.disabled = false;
			dismissButton.blur();
			dismissButton.disabled = true;
			return Promise.reject( new Error( 'nope' ) );
		} );
		dismissButton.focus();

		await toggleEarlyFraudWarningDismissal( dismissButton );

		// eslint-disable-next-line @wordpress/no-global-active-element
		expect( document.activeElement ).toBe( dismissButton );
	} );

	it( 'clears an earlier error when a retry succeeds', async () => {
		mockSetDismissed.mockRejectedValueOnce( new Error( 'nope' ) );
		mockSetDismissed.mockResolvedValueOnce( { dismissed: true } );
		const { block, dismissButton } = renderBlock();

		await toggleEarlyFraudWarningDismissal( dismissButton );
		await toggleEarlyFraudWarningDismissal( dismissButton );

		expect(
			block.querySelector( '.wcpay-fraud-risk-efw__error' )
		).toBeNull();
	} );
} );
