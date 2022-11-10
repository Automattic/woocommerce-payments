<?php
/**
 * Core loader.
 *
 * @package WooCommerce\Payments
 */

/** Load contracts. */

require_once __DIR__ . '/contracts/server/request/class-base-request-interface.php';
require_once __DIR__ . '/contracts/server/response/class-base-response-interface.php';


/** Load enums. */

require_once __DIR__ . '/enums/class-endpoints.php';

/** Load Core Server files */

require_once __DIR__ . '/server/request/class-base-request.php';
require_once __DIR__ . '/server/request/class-create-charge.php';
require_once __DIR__ . '/server/request/class-create-intention.php';
require_once __DIR__ . '/server/response/class-response.php';
