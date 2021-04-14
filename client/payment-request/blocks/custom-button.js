/**
 * Internal dependencies
 */
// - TODO: Add shared dependency

export const CustomButton = ( { onClick } ) => {
	// - TODO: Replace global with helper function from shared dependency.
	/* global wcpayPaymentRequestParams */
	const { theme, height, label } = wcpayPaymentRequestParams.button;
	return (
		<button
			type="button"
			id="wcpay-custom-button"
			className={ `button ${ theme }` }
			style={ {
				height: height + 'px',
			} }
			onClick={ onClick }
		>
			{ label }
		</button>
	);
};