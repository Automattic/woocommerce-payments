<?php
/**
 * Core loader.
 *
 * @package WooCommerce\Payments
 */

/** Load contracts. */

require_once __DIR__ . '/Contracts/API/Request/interface-base-request.php';
require_once __DIR__ . '/Contracts/API/Response/interface-base-response.php';

/** Load DTOs. */

require_once __DIR__ . '/DataTransferObjects/class-data-transfer-object.php';
require_once __DIR__ . '/DataTransferObjects/API/class-response.php';

/** Load enums. */

require_once __DIR__ . '/Enums/class-http-methods.php';
require_once __DIR__ . '/Enums/class-wc-pay-endpoints.php';

/** Load value objects */

require_once __DIR__ . '/ValueObjects/class-value-object.php';
require_once __DIR__ . '/ValueObjects/API/Request/class-base-request.php';
require_once __DIR__ . '/ValueObjects/API/Request/class-create-charge.php';
