/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './styles.scss';
import { CardFooter } from 'wcpay/components/wp-components-wrapped/components/card-footer';

interface CardNoticeProps {
	children: React.ReactNode;
	actions?: JSX.Element;
}

const CardNotice: React.FC< React.PropsWithChildren< CardNoticeProps > > = ( {
	children,
	actions,
} ) => {
	return (
		<CardFooter className="card-notice">
			<div className="card-notice__section">
				<div className="card-notice__text">{ children }</div>
				<div className="card-notice__button">{ actions }</div>
			</div>
		</CardFooter>
	);
};

export default CardNotice;
