/**
 * Internal dependencies
 */
import { getPaymentRequestData } from '../utils';

export const CustomButton = ( { onClick } ) => {
	// TODO: migrate button data
	const { theme, height, label } = getPaymentRequestData( 'button' );
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
