/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { Badge } from '@woocommerce/components';
import { CollapsibleList, TaskItem, Text } from '@woocommerce/experimental';

/**
 * Internal dependencies.
 */

const TaskList = ( { tasks } ) => {
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
				>
					{ tasks.map( ( task ) => (
						<TaskItem
							key={ task.key }
							title={ task.title }
							completed={ task.completed }
							content={ task.content }
							expanded
							onClick={ task.onClick }
							time={ task.time }
							level={ task.level }
						/>
					) ) }
				</CollapsibleList>
			</CardBody>
		</Card>
	);
};

export default TaskList;
