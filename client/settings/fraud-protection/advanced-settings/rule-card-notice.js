/**
 * External dependencies
 */
import React from 'react';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies
 */
import './../style.scss';
import BannerNotice from '../../../components/banner-notice';

const getInfoSVGIcon = () => {
	return (
		<svg
			width="24"
			height="24"
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			data-testid="rule-card-notice-info-icon-svg"
		>
			<circle
				cx="12"
				cy="9"
				r="5"
				stroke="#007CBA"
				strokeWidth="1.42857"
			/>
			<line
				x1="9"
				y1="16.8818"
				x2="15"
				y2="16.8818"
				stroke="#007CBA"
				strokeWidth="1.5"
			/>
			<line
				x1="10"
				y1="19.25"
				x2="14"
				y2="19.25"
				stroke="#007CBA"
				strokeWidth="1.5"
			/>
		</svg>
	);
};

const FraudProtectionRuleCardNotice = ( { type, children } ) => {
	let icon = <NoticeOutlineIcon />;

	if ( 'info' === type ) {
		icon = getInfoSVGIcon();
	}

	return (
		0 <= [ 'error', 'warning', 'info' ].indexOf( type ) && (
			<>
				<BannerNotice
					status={ type }
					icon={ icon }
					className={
						'fraud-protection-rule-card-notice fraud-protection-rule-card-notice-' +
						type
					}
					children={ children }
					isDismissible={ false }
				/>
			</>
		)
	);
};

export default FraudProtectionRuleCardNotice;
