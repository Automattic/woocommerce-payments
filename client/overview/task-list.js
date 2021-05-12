/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { Badge } from '@woocommerce/components';
import { CollapsibleList, Text } from '@woocommerce/experimental';

/**
 * Internal dependencies.
 */
import './style.scss';

const tasks = [];

const TaskList = () => {
	return (
		<Card
			size="large"
			className="woocommerce-task-card woocommerce-homescreen-card"
		>
			<CardHeader size="medium">
				<div className="wooocommerce-task-card__header">
					<Text variant="title.small">
						{ __( 'Things to do', 'woocommerce-payments' ) }
					</Text>
					<Badge count={ tasks.length } />
				</div>
			</CardHeader>
			<CardBody>
				<CollapsibleList
					collapsed={ false }
					collapseLabel={ __( 'Hide tasks', 'woocommerce-payments' ) }
					expandLabel={ __( 'Show tasks', 'woocommerce-payments' ) }
				/>
			</CardBody>
		</Card>
	);
};

export default TaskList;
