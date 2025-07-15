/** @format */
/**
 * External dependencies
 */
import clsx from 'clsx';
import { LoadableBlock } from 'multi-currency/interface/components';

const EnabledCurrenciesListItemPlaceholder = ( { isLoading } ) => {
	return (
		<li
			className={ clsx(
				'enabled-currency-placeholder',
				'enabled-currency'
			) }
		>
			<div className="enabled-currency__container">
				<div className="enabled-currency__flag">
					<LoadableBlock isLoading={ isLoading } numLines={ 1 }>
						<div className="enabled-currency__flag-text"></div>
					</LoadableBlock>
				</div>
				<div className="enabled-currency__label">
					<LoadableBlock isLoading={ isLoading } numLines={ 1 } />
				</div>
				<div className="enabled-currency__code">
					<LoadableBlock isLoading={ isLoading } numLines={ 1 } />
				</div>
			</div>
			<div className="enabled-currency__rate">
				<LoadableBlock isLoading={ isLoading } numLines={ 1 } />
			</div>
			<div className="enabled-currency__actions">
				<LoadableBlock isLoading={ isLoading } numLines={ 1 } />
			</div>
		</li>
	);
};

export default EnabledCurrenciesListItemPlaceholder;
