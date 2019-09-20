/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */

const CardSummary = ( props ) => {
	const card = props.card;
	return card
	?	<span>
			{ /* TODO: use card brand image instead of wrapping its name in a code tag*/ }
			<code>{ card.brand }</code>
			{ ' •••• ' }
			{ card.last4 }
		</span>
	: 	<span>&ndash;</span>;
};

export default CardSummary;
