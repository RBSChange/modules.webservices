<?php
class samples_ShopWebService implements webservices_WebService
{
	/**
	 * @return catalog_persistentdocument_shop[]
	 */
	public function getShops()
	{
		return catalog_ShopService::getInstance()->createQuery()->find();
	}

	/**
	 * @param integer $dayCount
	 * @return order_persistentdocument_order[]
	 */
	public function getLastDayOrders($dayCount)
	{
		$date = filter_DateFilterHelper::getReferenceDate("day", $dayCount);
		$orders = order_OrderService::getInstance()->createQuery()->add(Restrictions::gt('creationdate', $date))->find();
		return $orders;
	}

	/**
	 * @param integer $orderId
	 * @param string $orderStatus
	 * @return boolean
	 */
	public function setOrderStatus($orderId, $orderStatus)
	{
		try
		{
			$order = DocumentHelper::getDocumentInstance($orderId, "modules_order/order");
			$order->setOrderStatus($orderStatus);
			$order->save();
			return true;
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			return false;
		}
	}

	/**
	 * @param integer $shopId
	 * @param string $lang
	 * @return catalog_persistentdocument_shelf[]
	 */
	function getPrimaryShelves($lang, $shopId)
	{
		$this->setLang($lang);
		$shop = $this->getShop($shopId);
		return $shop->getPublishedTopShelfArray();
	}

	/**
	 * @param string $lang
	 * @param string $shelfId
	 * @return catalog_persistentdocument_shelf[]
	 */
	function getSubShelves($lang, $shelfId)
	{
		$this->setLang($lang);
		$shelfService = catalog_ShelfService::getInstance();
		$shelf = DocumentHelper::getDocumentInstance($shelfId, "modules_catalog/shelf");
		return $shelfService->getPublishedSubShelves($shelf);
	}

	/**
	 * @param string $lang
	 * @param integer $shelfId
	 * @return catalog_persistentdocument_product[]
	 */
	public function getProducts($lang, $shelfId)
	{
		$this->setLang($lang);
		$shelf = DocumentHelper::getDocumentInstance($shelfId, "modules_catalog/shelf");
		return catalog_ProductService::getInstance()->createQuery()->add(Restrictions::eq("shelf", $shelf))->find();
	}

	/**
	 * @param string $lang
	 * @param integer $productId
	 * @return catalog_persistentdocument_product
	 */
	function getProductDetail($lang, $productId)
	{
		$this->setLang($lang);
		return DocumentHelper::getDocumentInstance($productId, "modules_catalog/product");
	}

	/**
	 * @param integer $productId
	 * @param integer $newStockValue
	 * @return boolean
	 */
	public function updateStock($productId, $newStockValue)
	{
		$product = DocumentHelper::getDocumentInstance($productId, "modules_catalog/product");
		$product->setStockQuantity($newStockValue);
		$product->save();
		return true;
	}

	// private content

	/**
	 * @param Integer $shopId
	 * @return catalog_persistentdocument_shop
	 */
	private function getShop($shopId)
	{
		$shop = DocumentHelper::getDocumentInstance($shopId, "modules_catalog/shop");
		website_WebsiteModuleService::getInstance()->setCurrentWebsite($shop->getWebsite());
		return $shop;
	}

	/**
	 * @param String $lang
	 */
	private function setLang($lang)
	{
		RequestContext::getInstance()->setLang($lang);
	}
}
