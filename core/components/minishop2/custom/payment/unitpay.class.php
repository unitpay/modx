<?php
if (!class_exists('msPaymentInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class UnitPay extends msPaymentHandler implements msPaymentInterface
{
	/**
     * @var
     */
    protected $currentMethod;
	
	/**
     * @var
     */
    protected $domain;
    /**
     * @var
     */
    protected $publicKey;
    /**
     * @var
     */
    protected $secretKey;

    /**
     * @var bool
     */
    public $processSuccess = false;
	
    /**
     * PayPal constructor.
     *
     * @param xPDOObject $object
     * @param array $config
     */
    function __construct(xPDOObject $object, $config = array())
    {
        parent::__construct($object, $config);

        $siteUrl = $this->modx->getOption('site_url');
        $assetsUrl = $this->modx->getOption('assets_url') . 'components/minishop2/';
        $paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/unitpay.php';

        $this->config = array_merge(array(
			'ms2_payment_link' => $paymentUrl,
            'paymentUrl' => $paymentUrl,
			'domain' => $this->modx->getOption('ms2_payment_unitpay_domain'),
			'public_key' => $this->modx->getOption('ms2_payment_unitpay_public_key'),
			'secret_key' => $this->modx->getOption('ms2_payment_unitpay_secret_key'),
            'currency' => $this->modx->getOption('ms2_payment_unitpay_currency', null, 'RUB'),
			'nds' => $this->modx->getOption('ms2_payment_unitpay_nds', null, 'none'),
			'delivery_nds' => $this->modx->getOption('ms2_payment_unitpay_delivery_nds', null, 'none'),
        ), $config);
		
		$this->modx->cacheManager->refresh();
		
		$this->setDomain($this->modx->getOption('ms2_payment_unitpay_domain'));
        $this->setPublicKey($this->modx->getOption('ms2_payment_unitpay_public_key'));
        $this->setSecretKey($this->modx->getOption('ms2_payment_unitpay_secret_key'));
		
		$this->modx->lexicon->load('minishop2:unitpay');
    }


	/**
     * @param Order $order
     * @return array
     */
    public function getRedirectUrl(msOrder $order)
    {
        $items = [];
        $description = "Оплата заказа № " . $order->get("id");
        $sum = $this->priceFormat($order->get("cost"));
		$address = false;
		$profile = false;
		
		if ($this->modx->getOption('ms2_payment_paypal_order_details', null, true)) {
            $products = $order->getMany('Products');
			$address = $order->getOne('Address');
			
			$user = $order->getOne("User");
			if ($user) {
				$profile = $user->getOne('Profile');
			}
			
			foreach ($products as $item) {
				$name = $item->get('name');
                if (empty($name) && $product = $item->getOne('Product')) {
                    $name = $product->get('pagetitle');
                }
				
				$items[] = [
					"name" => $name,
					"count" => $item->get('count'),
					//"price" => $this->priceFormat($item->get('price')),
					"price" => $item->get('price'),
					"type" => "commodity",
					"currency" => $this->config['currency'],
					"nds" => $this->config['nds'],
				];
			}
		}

        if($order->get('delivery_cost') > 0) {
            $items[] = array(
                'name' => "Доставка",
                //'price' => $this->priceFormat($order->get('delivery_cost')),
				'price' => $order->get('delivery_cost'),
                'count'   => 1,
                'type' => 'service',
                'currency' => $this->config['currency'],
				"nds" => $this->config['delivery_nds'],
            );
        }
	
        $cashItems = $this->cashItems($items);

        $signature = $this->generateSignature($order->get('id'), $this->config['currency'], $description, $sum);

        $params = [
            'account' => $order->get("id"),
            'desc' => $description,
            'sum' => $sum,
            'signature' => $signature,
            'currency' => $this->config['currency'],
            'cashItems' => $cashItems,
            'customerEmail' => $profile ? $profile->get("email") : "",
            'customerPhone' => $address ? $this->phoneFormat($address->get("phone")) : "",
        ];
		
		return $this->success('', array('msorder' => $order->get('id'), 'redirect' => $this->endpoint() . "?" . http_build_query($params)));
    }
	
    /**
     * @param msOrder $order
     *
     * @return array|string
     */
    public function send(msOrder $order)
    {
        if ($order->get('status') > 1) {
            return $this->error('ms2_err_status_wrong');
        }
		
		return $this->getRedirectUrl($order);
    }
	
	/**
     * @return bool
     */
    public function isProcessSuccess()
    {
        return $this->processSuccess;
    }

    /**
     * @param bool $processSuccess
     */
    public function setProcessSuccess($processSuccess)
    {
        $this->processSuccess = $processSuccess;
    }


    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @param mixed $publicKey
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return mixed
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @param mixed $secretKey
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @return string
     */
    public function endpoint() {
        return 'https://' . $this->getDomain() . '/pay/' . $this->getPublicKey();
    }

    /**
     * @param $order_id
     * @param $currency
     * @param $desc
     * @param $sum
     * @return string
     */
    public function generateSignature($order_id, $currency, $desc, $sum) {
        return hash('sha256', join('{up}', array(
            $order_id,
            $currency,
            $desc,
            $sum ,
            $this->getSecretKey()
        )));
    }

    /**
     * @param $method
     * @param array $params
     * @return string
     */
    public function getSignature($method, array $params)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $this->getSecretKey());
        array_unshift($params, $method);

        return hash('sha256', join('{up}', $params));
    }

    /**
     * @param $params
     * @param $method
     * @return bool
     */
    public function verifySignature($params, $method)
    {
        return $params['signature'] == $this->getSignature($method, $params);
    }

    /**
     * @param $items
     * @return string
     */
    public function cashItems($items) {
        return base64_encode(json_encode($items));
    }

    /**
     * @param $rate
     * @return string
     */
    function getTaxRates($rate){
        switch (intval($rate)){
            case 10:
                $vat = 'vat10';
                break;
            case 20:
                $vat = 'vat20';
                break;
            case 0:
                $vat = 'vat0';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
    }

    /**
     * @param $value
     * @return string
     */
    public function priceFormat($value) {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param $value
     * @return string
     */
    public function phoneFormat($value) {
        return  preg_replace('/\D/', '', $value);
    }

    /**
     * @param msOrder $order
     * @return array
     */
    public function validateCallback(msOrder $order)
    {
        $method = '';
        $params = [];
        $result = [];

        try {
            if (!$order) {
				$result = array('error' =>
					array('message' => 'Order not found')
				);
            } else {
				if ((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature']))){
					$params = $_GET['params'];
					$method = $_GET['method'];
					$signature = $params['signature'];

					if (empty($signature)){
						$status_sign = false;
					}else{
						$status_sign = $this->verifySignature($params, $method);
					}

				}else{
					$status_sign = false;
				}

				if ($status_sign){
					if(in_array($method, array('check', 'pay', 'error'))) {
						$this->currentMethod = $method;

						$result = $this->findErrors($params, $this->priceFormat($order->get('cost')), $this->config['currency']);
					} else {
						$result = array('error' =>
							array('message' => 'Method not exists')
						);
					}
				}else{
					$result = array('error' =>
						array('message' => 'Signature verify error')
					);
				}
			}

        } catch (\Exception $e) {
            $this->_logger->error($e);
        }

        return $result;
    }

    /**
     * @param $params
     * @param $sum
     * @param $currency
     * @return array
     */
    public function findErrors($params, $sum, $currency) {
        $this->setProcessSuccess(false);

        $order_id = $params['account'];

        if (is_null($order_id)){
            $result = array('error' =>
                array('message' => 'Order id is required')
            );
        } elseif ((float) $this->priceFormat($sum) != (float) $this->priceFormat($params['orderSum'])) {
            $result = array('error' =>
                array('message' => 'Price not equals ' . $sum . ' != ' . $params['orderSum'])
            );
        }elseif ($currency != $params['orderCurrency']) {
            $result = array('error' =>
                array('message' => 'Currency not equals ' . $currency . ' != ' . $params['orderCurrency'])
            );
        }
        else{
            $this->setProcessSuccess(true);

            $result = array('result' =>
                array('message' => 'Success')
            );
        }

        return $result;
    }

    /**
     * @param msOrder $order
     * @param array $params
     *
     * @return bool
     */
    public function receive(msOrder $order, $params = array())
    {
		if(!isset($params["error"])) {
			$this->ms2->changeOrderStatus($order->get('id'), 2); // Set status "paid"
		} else {
			$this->ms2->changeOrderStatus($order->get('id'), 4); // Set status "cancelled"
		}

        return true;
    }


    /**
     * Returns a direct link for continue payment process of existing order
     *
     * @param msOrder $order
     *
     * @return string
     */
    public function getPaymentLink(msOrder $order)
    {
        return $this->config['paymentUrl'] . '?' .
        http_build_query(array(
            'action' => 'continue',
            'msorder' => $order->get('id'),
            'mscode' => $this->getOrderHash($order),
        ));
    }

}