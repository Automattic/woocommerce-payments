/**
 * External dependencies
 */
import React from 'react';

const Emoticons = ( props ) => {
	const { icon, rating, onClick, disabled, currentRating } = props;

	const iconUrl =
		'/wp-content/plugins/woocommerce-payments/dist/../assets/images/smilies/' +
		icon;

	const buttonCss =
		'components-button has-icon' +
		( rating === currentRating ? ' selected' : '' );

	return (
		<>
			<button
				disabled={ disabled }
				type="button"
				className={ buttonCss }
				onClick={ function () {
					onClick( rating );
				} }
			>
				<img src={ iconUrl } alt="WooPayments" />
			</button>
		</>
	);
};

export default Emoticons;
