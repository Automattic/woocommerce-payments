/**
 * External dependencies
 */
import * as React from 'react';
import { FlexBlock } from '@wordpress/components';

interface DepositsInformationBlockProps {
	title: string;
	value: string;
	children?: any;
}

const DepositsInformationBlock: React.FunctionComponent< DepositsInformationBlockProps > = ( {
	title,
	value,
	children,
} ) => {
	return (
		<FlexBlock className="wcpay-deposits-information-block">
			<div className="wcpay-deposits-information-block__title">
				{ title }
			</div>
			<div className="wcpay-deposits-information-block__value">
				{ value }
			</div>
			<div
				className="wcpay-deposits-information-block__extra"
				data-testid="extra"
			>
				{ children }
			</div>
		</FlexBlock>
	);
};

export default DepositsInformationBlock;
