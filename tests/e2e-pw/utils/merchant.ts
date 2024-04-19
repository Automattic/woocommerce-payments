/**
 * External dependencies
 */
import { Page } from 'playwright/test';

export const goToOrder = async (
	page: Page,
	orderId: string
): Promise< void > => {
	await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );
};
