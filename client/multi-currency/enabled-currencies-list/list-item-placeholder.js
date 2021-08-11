/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import { LoadableBlock } from '../../components/loadable';

const EnabledCurrenciesListItemPlaceholder = ( { isLoading } ) => {
	return (
		<li
			className={ classNames(
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
					<LoadableBlock
						isLoading={ isLoading }
						numLines={ 1 }
					></LoadableBlock>
				</div>
				<div className="enabled-currency__code">
					<LoadableBlock
						isLoading={ isLoading }
						numLines={ 1 }
					></LoadableBlock>
				</div>
			</div>
			<div className="enabled-currency__rate">
				<LoadableBlock
					isLoading={ isLoading }
					numLines={ 1 }
				></LoadableBlock>
			</div>
			<div className="enabled-currency__actions">
				<LoadableBlock
					isLoading={ isLoading }
					numLines={ 1 }
				></LoadableBlock>
			</div>
		</li>
	);
};

export default EnabledCurrenciesListItemPlaceholder;
