/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Spotlight from './index';
import KlarnaPromotionIllustration from 'assets/images/illustrations/klarna-promotion-spotlight.svg?asset';

/**
 * Example usage of the Spotlight component with Klarna promotion.
 */
const KlarnaPromotionSpotlightExample: React.FC = () => {
	const handleActivate = () => {
		// eslint-disable-next-line no-console
		console.log( 'Activate Klarna clicked' );
		// In actual implementation, this would:
		// 1. Call the activate promotion API
		// 2. Show success message
		// 3. Navigate to payment methods or refresh settings
	};

	const handleLearnMore = () => {
		// eslint-disable-next-line no-console
		console.log( 'Learn more clicked' );
		// In actual implementation, this would open the T&Cs link
		// window.open( tcUrl, '_blank' );
	};

	const handleDismiss = () => {
		// eslint-disable-next-line no-console
		console.log( 'Spotlight dismissed' );
		// In actual implementation, this would:
		// 1. Call the dismiss promotion API
		// 2. Update local state to hide the spotlight
	};

	return (
		<div
			style={ { padding: '20px', position: 'relative', height: '100vh' } }
		>
			<h1>Spotlight Component - Klarna Promotion Example</h1>
			<p>
				This example demonstrates the Spotlight component with Klarna
				promotion content.
			</p>
			<p>
				The spotlight will appear in the bottom-right corner after 4
				seconds (or immediately if showImmediately is true).
			</p>

			<Spotlight
				badge="Limited time offer"
				heading="Save 50% on Klarna processing fees for 3 months"
				description={
					<>
						In 2024, shoppers spent $52.4B using buy now, pay later.
						Enable flexible payments with Klarna in WooPayments for
						50% off processing fees for 3 months.
					</>
				}
				disclaimer={ <>*Nasdaq Oct. 2025</> }
				image={ KlarnaPromotionIllustration }
				primaryButtonLabel="Activate Klarna"
				onPrimaryClick={ handleActivate }
				secondaryButtonLabel="Learn more"
				onSecondaryClick={ handleLearnMore }
				onDismiss={ handleDismiss }
				showImmediately={ true } // Set to false in production
			/>
		</div>
	);
};

export default KlarnaPromotionSpotlightExample;
