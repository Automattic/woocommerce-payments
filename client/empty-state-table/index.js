/**
 * External dependencies
 */
import classNames from 'classnames';
/**
 * Internal dependencis
 */
import './style.scss';

export const EmptyStateTable = ( props ) => {
	return (
		<div className="components-card empty-state-table is-size-medium woocommerce-table woocommerce-report-table has-menu">
			<div className="components-flex components-card__header is-size-medium">
				<h2>{ props.title }</h2>
			</div>
			<div className="components-card__body">
				<div className="woocommerce-table__table" role="group">
					<table>
						<tbody>
							<tr>
								{ props.headers.map( ( header, key ) => {
									// Return the element. Also pass key
									return (
										<th
											key={ key }
											className={ classNames(
												'woocommerce-table__header',
												header.classNames
											) }
										>
											{ header.text }
										</th>
									);
								} ) }
							</tr>
							<tr>
								<td
									className="woocommerce-table__empty-item"
									colSpan={ props.headers.length }
								>
									{ props.content }
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	);
};

export default EmptyStateTable;
