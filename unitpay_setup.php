<?php
$tstart= microtime(true);

if (!defined('MODX_API_MODE')) {
    define('MODX_API_MODE', false);
}

@include(dirname(__FILE__) . '/config.core.php');
if (!defined('MODX_CORE_PATH')) define('MODX_CORE_PATH', dirname(__FILE__) . '/core/');

if (!@include_once (MODX_CORE_PATH . "model/modx/modx.class.php")) {
    $errorMessage = 'Site temporarily unavailable';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header($_SERVER['SERVER_PROTOCOL'] . ' 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}

ob_start();

$modx= new modX();
if (!is_object($modx) || !($modx instanceof modX)) {
    ob_get_level() && @ob_end_flush();
    $errorMessage = '<a href="setup/">MODX not installed. Install now?</a>';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header($_SERVER['SERVER_PROTOCOL'] . ' 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}

$modx->startTime= $tstart;

$modx->initialize('web');

if ($miniShop2 = $modx->getService('miniShop2')) {
    $miniShop2->addService('payment', 'UnitPay',
        '{core_path}components/minishop2/custom/payment/unitpay.class.php'
    );
	
	$modx->runProcessor('system/settings/create', array(
		'key' => "ms2_payment_unitpay_domain",
		'value' => "unitpay.ru",
		'namespace' => 'minishop2',
		'area' => 'ms2_payment',
	));
	
	$modx->runProcessor('system/settings/create', array(
		'key' => "ms2_payment_unitpay_public_key",
		'value' => "",
		'namespace' => 'minishop2',
		'area' => 'ms2_payment',
	));
	
	$modx->runProcessor('system/settings/create', array(
		'key' => "ms2_payment_unitpay_secret_key",
		'value' => "",
		'namespace' => 'minishop2',
		'area' => 'ms2_payment',
	));
	
	$modx->runProcessor('system/settings/create', array(
		'key' => "ms2_payment_unitpay_currency",
		'value' => "RUB",
		'namespace' => 'minishop2',
		'area' => 'ms2_payment',
	));
	
	$modx->runProcessor('system/settings/create', array(
		'key' => "ms2_payment_unitpay_nds",
		'value' => "none",
		'namespace' => 'minishop2',
		'area' => 'ms2_payment',
	));
	
	$modx->runProcessor('system/settings/create', array(
		'key' => "ms2_payment_unitpay_delivery_nds",
		'value' => "none",
		'namespace' => 'minishop2',
		'area' => 'ms2_payment',
	));
	
	$modx->cacheManager->refresh();
	
	unlink(__FILE__);
	
	header("Location: /");
	die();
}