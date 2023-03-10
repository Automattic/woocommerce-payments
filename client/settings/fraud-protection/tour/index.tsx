/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import interpolateComponents from 'interpolate-components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Tour from '../../../components/tour';
import { useSettings } from '../../../data';

const FraudProtectionTour: React.FC = () => {
	const { isLoading } = useSettings();
	const [ showTour, setShowTour ] = useState( false );

	useEffect( () => {
		if ( ! isLoading ) {
			setShowTour( true );
		}
	}, [ isLoading ] );

	const handleTourEnd = () => {
		setShowTour( false );
	};

	if ( ! showTour ) return null;

	return (
		<Tour onTourEnd={ handleTourEnd }>
			<Tour.Step
				selector="#wpcontent"
				position={ { bottom: 20, left: 20 } }
			>
				<Tour.Image
					src="https://picsum.photos/350/204"
					alt={ __( 'Enhanced fraud protection is here 🔒' ) }
				/>

				<Tour.Content
					title={ __( 'Enhanced fraud protection is here 🔒' ) }
					// eslint-disable-next-line max-len
					description={ __(
						"Incoming transactions will now be screened for common risk factors, at the level of your choosing. Review any transactions caught by these filters and select whether you'd like to approve or decline them"
					) }
				/>

				<Tour.Footer showCounter={ false }>
					<Tour.NextButton />
				</Tour.Footer>
			</Tour.Step>

			<Tour.Step selector="#fraud-protection-card-title" position="top">
				<Tour.Image
					src="https://picsum.photos/350/204"
					alt={ __( 'Choose your filter level 🚦' ) }
					mobileOnly
				/>

				<Tour.Content
					title={ __( 'Choose your filter level 🚦' ) }
					// eslint-disable-next-line max-len
					description={ interpolateComponents( {
						mixedString: __(
							'Decide how aggressively you want to filter suspicious payments, from {{strong}}standard{{/strong}} to {{strong}}advanced.{{/strong}}'
						),
						components: { strong: <strong /> },
					} ) }
				/>

				<Tour.Footer />
			</Tour.Step>

			<Tour.Step
				selector="#fraud-protection-level-select_advanced-level"
				position="left"
			>
				<Tour.Image
					src="https://picsum.photos/350/204"
					alt={ __( 'Take more control 🎚️' ) }
					mobileOnly
				/>

				<Tour.Content
					title={ __( 'Take more control 🎚️' ) }
					// eslint-disable-next-line max-len
					description={ __(
						'We recommend using one of the preset risk levels, but if you need more control, head to Advanced to fine-tune the various filters.'
					) }
				/>

				<Tour.Footer />
			</Tour.Step>

			<Tour.Step
				selector="#toplevel_page_wc-admin-path--payments-overview"
				position="right"
			>
				<Tour.Image
					src="https://picsum.photos/350/204"
					alt={ __( 'Ready for review 📥️' ) }
					mobileOnly
				/>

				<Tour.Content
					title={ __( 'Ready for review 📥️' ) }
					// eslint-disable-next-line max-len
					description={ interpolateComponents( {
						mixedString: __(
							"Payments that have been caught by a risk filter will appear under {{strong}}Transactions > Payments{{/strong}}. We'll let you know why each payment was flagged so that you can determine whether to approve or block it."
						),
						components: { strong: <strong /> },
					} ) }
				/>

				<Tour.Footer>
					<Tour.PreviousButton />
					<Tour.NextButton>{ __( 'Got it!' ) }</Tour.NextButton>
				</Tour.Footer>
			</Tour.Step>
		</Tour>
	);
};

export default FraudProtectionTour;
