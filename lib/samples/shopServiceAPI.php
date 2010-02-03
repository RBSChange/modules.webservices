<?php
class samples_shopwebservice_GetShopsParam {
}
class samples_shopwebservice_GetLastDayOrdersParam {
	public $dayCount;
	function __construct($dayCount) {
		$this->dayCount = $dayCount;
	}
}
class samples_shopwebservice_SetOrderStatusParam {
	public $orderId, $orderStatus, $trackingNumber;
	function __construct($orderId, $orderStatus, $trackingNumber) {
		$this->orderId = $orderId;
		$this->orderStatus = $orderStatus;
		$this->trackingNumber = $trackingNumber;
	}
}
class samples_shopwebservice_GetPrimaryShelvesParam {
	public $lang, $shopId;
	function __construct($lang, $shopId) {
		$this->lang = $lang;
		$this->shopId = $shopId;
	}
}
class samples_shopwebservice_GetSubShelvesParam {
	public $lang, $shelfId;
	function __construct($lang, $shelfId) {
		$this->lang = $lang;
		$this->shelfId = $shelfId;
	}
}
class samples_shopwebservice_GetProductsParam {
	public $lang, $shelfId;
	function __construct($lang, $shelfId) {
		$this->lang = $lang;
		$this->shelfId = $shelfId;
	}
}
class samples_shopwebservice_GetProductDetailParam {
	public $lang, $productId;
	function __construct($lang, $productId) {
		$this->lang = $lang;
		$this->productId = $productId;
	}
}
class samples_shopwebservice_UpdateStockParam {
	public $productId, $newStockValue;
	function __construct($productId, $newStockValue) {
		$this->productId = $productId;
		$this->newStockValue = $newStockValue;
	}
}
class samples_ShopWebServiceClient {
	private $client;
	private $endPoint;
	private $clientOptions;

	/**
	 * @param String $endPoint the change webservice location (http[s]://<targetFQDN>/webservices/<moduleName>/<serviceName>)
	 */
	function __construct($endPoint) {
		$this->endPoint = $endPoint;
		$this->clientOptions = array('encoding' => 'utf-8', 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP, 'trace' => true, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS);
	}
	
	/**
	 * @param String $login
	 */
	function setLogin($login) {
		$this->clientOptions['login'] = $login;
	}
	
	/**
	 * @param String $password
	 */
	function setPassword($password) {
		$this->clientOptions['password'] = $password;
	}
	
	/**
	 * See http://php.net/manual/en/soapclient.soapclient.php for possible options
	 * @param String $name
	 * @param mixed $value
	 */
	function setSoapOption($name, $value) {
		$this->clientOptions[$name] = $value;
	}
	
	private function getClient() {
		if ($this->client === null) {
			$this->client = new SoapClient($this->endPoint.'?wsdl', $this->clientOptions);
		}
		return $this->client;
	}
	
	private function getArray($res) {
		if (!isset($res->items)) {
			return array();	
		}
		if (is_array($res->items)) {
			return $res->items;
		}
		/* This one is not necessary when SOAP_SINGLE_ELEMENT_ARRAYS feature is enabled
		if (is_object($res->items)) {
			return array($res->items);
		}*/
		throw new Exception('Unknown array type result '.var_export($res, true));
	}

	/**
	 * @return catalog_persistentdocument_shop[]
	 */
	function getShops() {
		$res = $this->getClient()->getShops(new samples_shopwebservice_GetShopsParam())->getShopsResult;
		$res = $this->getArray($res);
		return $res;
	}

	/**
	 * @param Integer $dayCount
	 * @return order_persistentdocument_order[]
	 */
	function getLastDayOrders($dayCount) {
		$res = $this->getClient()->getLastDayOrders(new samples_shopwebservice_GetLastDayOrdersParam($dayCount))->getLastDayOrdersResult;
		$res = $this->getArray($res);
		return $res;
	}

	/**
	 * @param Integer $orderId
	 * @param String $orderStatus
	 * @param String $trackingNumber
	 * @return Boolean
	 */
	function setOrderStatus($orderId, $orderStatus, $trackingNumber) {
		$res = $this->getClient()->setOrderStatus(new samples_shopwebservice_SetOrderStatusParam($orderId, $orderStatus, $trackingNumber))->setOrderStatusResult;
		return $res;
	}

	/**
	 * @param Integer $shopId
	 * @param String $lang
	 * @return catalog_persistentdocument_shelf[]
	 */
	function getPrimaryShelves($lang, $shopId) {
		$res = $this->getClient()->getPrimaryShelves(new samples_shopwebservice_GetPrimaryShelvesParam($lang, $shopId))->getPrimaryShelvesResult;
		$res = $this->getArray($res);
		return $res;
	}

	/**
	 * @param String $lang
	 * @param Integer $shelfId
	 * @return catalog_persistentdocument_shelf[]
	 */
	function getSubShelves($lang, $shelfId) {
		$res = $this->getClient()->getSubShelves(new samples_shopwebservice_GetSubShelvesParam($lang, $shelfId))->getSubShelvesResult;
		$res = $this->getArray($res);
		return $res;
	}

	/**
	 * @param String $lang
	 * @param Integer $shelfId
	 * @return catalog_persistentdocument_product[]
	 */
	function getProducts($lang, $shelfId) {
		$res = $this->getClient()->getProducts(new samples_shopwebservice_GetProductsParam($lang, $shelfId))->getProductsResult;
		$res = $this->getArray($res);
		return $res;
	}

	/**
	 * @param String $lang
	 * @param Integer $productId
	 * @return catalog_persistentdocument_product
	 */
	function getProductDetail($lang, $productId) {
		$res = $this->getClient()->getProductDetail(new samples_shopwebservice_GetProductDetailParam($lang, $productId))->getProductDetailResult;
		return $res;
	}

	/**
	 * @param Integer $productId
	 * @param Integer $newStockValue
	 * @return Boolean
	 */
	function updateStock($productId, $newStockValue) {
		$res = $this->getClient()->updateStock(new samples_shopwebservice_UpdateStockParam($productId, $newStockValue))->updateStockResult;
		return $res;
	}

}
