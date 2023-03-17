/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { useDispatch } from '@wordpress/data';
import interpolateComponents from 'interpolate-components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Tour from '../../../components/tour';
import { useSettings } from '../../../data';
import tourStepImage from '../../../../assets/images/fraud-protection/tour/enhanced-protection-step.png';
import tourStepImage2x from '../../../../assets/images/fraud-protection/tour/enhanced-protection-step@2x.png';

const FraudProtectionTour: React.FC = () => {
	const { isWelcomeTourDismissed } = wcpaySettings.fraudProtection;

	const { isLoading } = useSettings();
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ showTour, setShowTour ] = useState( false );

	useEffect( () => {
		if ( ! isLoading && ! isWelcomeTourDismissed ) {
			setShowTour( true );
		}
	}, [ isLoading, isWelcomeTourDismissed ] );

	const handleTourEnd = () => {
		updateOptions( {
			wcpay_fraud_protection_welcome_tour_dismissed: true,
		} );
		wcpaySettings.fraudProtection.isWelcomeTourDismissed = true;

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
					srcSet={ `${ tourStepImage }, ${ tourStepImage2x } 2x` }
					src={ tourStepImage }
					alt={ __( 'Enhanced fraud protection is here 🔒' ) }
				/>

				<Tour.Content
					title={ __( 'Enhanced fraud protection is here 🔒' ) }
					description={ __(
						'You can now choose a level of protection to have for screening incoming transactions. You will then be able to review any caught transactions and select whether you would like to approve or decline them.'
					) }
				/>

				<Tour.Footer showCounter={ false }>
					<Tour.NextButton>
						{ __( "See what's new" ) }
					</Tour.NextButton>
				</Tour.Footer>
			</Tour.Step>

			<Tour.Step selector="#fraud-protection-card-title" position="top">
				<Tour.Content
					title={ __( 'Choose your filter level 🚦' ) }
					description={ interpolateComponents( {
						mixedString: __(
							'Decide how aggressively you want to filter suspicious payments, from {{strong}}standard{{/strong}} to {{strong}}advanced{{/strong}}.'
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
				<Tour.Content
					title={ __( 'Take more control 🎚️' ) }
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
				<Tour.Content
					title={ __( 'Ready for review 📥️' ) }
					description={ interpolateComponents( {
						mixedString: __(
							"Payments that have been caught by a risk filter will appear under {{strong}}Payments > Transactions{{/strong}}. We'll let you know why each payment was flagged so that you can determine whether to approve or block it."
						),
						components: { strong: <strong /> },
					} ) }
				/>

				<Tour.Footer>
					<Tour.PreviousButton />
					<Tour.NextButton>{ __( 'Got it' ) }</Tour.NextButton>
				</Tour.Footer>
			</Tour.Step>
		</Tour>
	);
};

export default FraudProtectionTour;
