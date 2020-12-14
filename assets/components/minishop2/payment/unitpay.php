<?php
define('MODX_API_MODE', true);
/** @noinspection PhpIncludeInspection */
require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

/** @var modX $modx */
$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');

/** @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('miniShop2');
$miniShop2->loadCustomClasses('payment');

if (!class_exists('UnitPay')) {
    exit('Error: could not load payment class "UnitPay".');
}
/** @var msOrder $order */
$order = $modx->newObject('msOrder');
/** @var msPaymentInterface|UnitPay $handler */
$handler = new UnitPay($order);

if (isset($_GET['action']) && $_GET['action'] == 'continue' && !empty($_GET['msorder']) && !empty($_GET['mscode'])) {
    if ($order = $modx->getObject('msOrder', (int)$_GET['msorder'])) {
        if ($_GET['mscode'] == $handler->getOrderHash($order)) {
            $response = $handler->send($order);
            if ($response['success'] && !empty($response['data']['redirect'])) {
                $modx->sendRedirect($response['data']['redirect']);
            } else {
                exit($response['message']);
            }
        }
    }
    exit('Error when continuing order');
}

$result = array();

if(!isset($_GET['params']['account'])) {
	$result = array('error' =>
		array('message' => 'Required params not found')
	);
} else {
	$order = $modx->getObject('msOrder', array('id' => $_GET['params']['account']));
	
	if($order) {
		$result = $handler->validateCallback($order);

		if($handler->isProcessSuccess()) {
			$handler->receive($order, $result);
		}
	} else {
		$result = array('error' =>
			array('message' => 'Order not found')
		);
	}
}

header('Content-Type: application/json');

echo json_encode($result); die();
