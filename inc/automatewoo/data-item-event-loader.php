<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\AutomateWoo\Data_Item' ) ) {
	return false;
}

require_once __DIR__ . '/class-data-item-event.php';

return new Mindshare_AutomateWoo_Data_Item_Event();
