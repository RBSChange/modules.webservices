<?php
/**
 * @wsdlProperties(catalog_persistentdocument_shelf:_minimal_;catalog_persistentdocument_product:id,lang,type,label;catalog_persistentdocument_shop:_minimal_;order_persistentdocument_order:id,lang,type,label,totalAmountWithTax,totalAmountWithoutTax,orderStatus)
 * @extraWsdlProperties(catalog_persistentdocument_shelf:visualURL|String;catalog_persistentdocument_product:formattedCurrentShopPrice|String,stockQuantity|Double;order_persistentdocument_order:packageTrackingNumber)
 */
class samples_ShopWebService implements webservices_WebService
{
	/**
	 * @return catalog_persistentdocument_shop[]
	 */
	public function getShops()
	{
		return webservices_PersistentDocument::fromDocumentArray(catalog_ShopService::getInstance()->createQuery()->find());
	}

	/**
	 * @param Integer $dayCount
	 * @return order_persistentdocument_order[]
	 */
	public function getLastDayOrders($dayCount)
	{
		$date = filter_DateFilterHelper::getReferenceDate("day", $dayCount);
		$orders = order_OrderService::getInstance()->createQuery()->add(Restrictions::gt('creationdate', $date))->find();
		return webservices_PersistentDocument::fromDocumentArray($orders, array("totalAmountWithTax" ,"totalAmountWithoutTax", "orderStatus", "packageTrackingNumber"));
	}

	/**
	 * @param Integer $orderId
	 * @param String $orderStatus
	 * @param String $trackingNumber
	 * @return Boolean
	 */
	public function setOrderStatus($orderId, $orderStatus, $trackingNumber)
	{
		try
		{
			$order = DocumentHelper::getDocumentInstance($orderId, "modules_order/order");
			$order->setOrderStatus($orderStatus);
			$order->setPackageTrackingNumber($trackingNumber);
			// TODO: move this check into orderService ?
			if ($order->getOrderStatus() == "SHIPPED" && f_util_StringUtils::isEmpty($trackingNumber))
			{
				throw new Exception("Tried to update orderStatus of $orderId to SHIPPED without providing $trackingNumber");
			}
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
	 * @param Integer $shopId
	 * @param String $lang
	 * @return catalog_persistentdocument_shelf[]
	 */
	function getPrimaryShelves($lang, $shopId)
	{
		$this->setLang($lang);
		$shop = $this->getShop($shopId);
		return webservices_PersistentDocument::fromDocumentArray($shop->getPublishedTopShelfArray(), array("visualURL"));
	}

	/**
	 * @param String $lang
	 * @param Integer $shelfId
	 * @return catalog_persistentdocument_shelf[]
	 */
	function getSubShelves($lang, $shelfId)
	{
		$this->setLang($lang);
		$shelfService = catalog_ShelfService::getInstance();
		$shelf = DocumentHelper::getDocumentInstance($shelfId, "modules_catalog/shelf");

		return webservices_PersistentDocument::fromDocumentArray($shelfService->getPublishedSubShelves($shelf), array("visualURL"));
	}

	/**
	 * @param String $lang
	 * @param Integer $shelfId
	 * @return catalog_persistentdocument_product[]
	 */
	public function getProducts($lang, $shelfId)
	{
		$this->setLang($lang);
		$shelf = DocumentHelper::getDocumentInstance($shelfId, "modules_catalog/shelf");
		return webservices_PersistentDocument::fromDocumentArray(catalog_ProductService::getInstance()->createQuery()->add(Restrictions::eq("shelf", $shelf))->find(), array("formattedCurrentShopPrice", "stockQuantity"));
	}

	/**
	 * @param String $lang
	 * @param Integer $productId
	 * @return catalog_persistentdocument_product
	 */
	function getProductDetail($lang, $productId)
	{
		$this->setLang($lang);
		$product = DocumentHelper::getDocumentInstance($productId, "modules_catalog/product");
		return webservices_PersistentDocument::fromDocument($product);
	}

	/**
	 * @param Integer $productId
	 * @param Integer $newStockValue
	 * @return Boolean
	 */
	public function updateStock($productId, $newStockValue)
	{
		$product = DocumentHelper::getDocumentInstance($productId, "modules_catalog/product");
		if ($product instanceof catalog_StockableDocument)
		{
			$product->setStockQuantity($newStockValue);
			$product->save();
			return true;
		}
		return false;
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
