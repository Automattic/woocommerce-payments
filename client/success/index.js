document.addEventListener( 'DOMContentLoaded', () => {
	const multibancoInstructionsContainer = document.getElementById(
		'wc-payment-gateway-multibanco-instructions-container'
	);

	if ( multibancoInstructionsContainer ) {
		// Get the computed text color
		const computedTextColor = window.getComputedStyle(
			multibancoInstructionsContainer
		).color;

		// Get the parent's background color
		// Get the parent's background color, accounting for transparency
		const getEffectiveBackgroundColor = ( element ) => {
			let currentElement = element;
			let backgroundColor = window.getComputedStyle( currentElement )
				.backgroundColor;

			// Keep going up the DOM tree until we find a non-transparent background
			while (
				backgroundColor === 'transparent' ||
				backgroundColor.includes( 'rgba(0, 0, 0, 0)' )
			) {
				currentElement = currentElement.parentElement;
				if ( ! currentElement ) {
					return 'rgb(255, 255, 255)'; // Default to white if we reach the root
				}
				backgroundColor = window.getComputedStyle( currentElement )
					.backgroundColor;
			}
			return backgroundColor;
		};

		const parentBgColor = getEffectiveBackgroundColor(
			multibancoInstructionsContainer.parentElement
		);

		// Convert RGB color to RGBA with different opacities
		const convertRgbToRgba = ( rgbColor, opacity ) => {
			return rgbColor.replace( /rgb\((.*)\)/, `rgba($1, ${ opacity })` );
		};

		// Set the CSS variables on the container
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-text-color',
			computedTextColor
		);
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-bg-color',
			convertRgbToRgba( computedTextColor, 0.06 )
		);
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-border-color',
			convertRgbToRgba( computedTextColor, 0.16 )
		);
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-card-bg-color',
			parentBgColor
		);
	}
} );
