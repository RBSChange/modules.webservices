<?php
// Configuration
$fqdn = "aproject.intsimoa.rd.devlinux.france.rbs.fr";
//$fqdn = "demo.vmchange.rbs.fr";
$login = "admin";
$password = "password";

// Load API (generated)
require_once("shopServiceAPI.php");

// Specific code
function usage()
{
	echo "Usage: ".basename(__FILE__)." <command> <options>
- nav
- set-stock <productId> <newStockValue>
- orders [dayCount = 1]
- set-order-status <orderId> <orderStatus> [trackingNumber]
  Where <orderStatus> in:
  - PAYMENT_SUCCESS
  - PAYMENT_WAITING
  - PAYMENT_FAILED
  - PAYMENT_DELAYED
  - CANCELED
  - SHIPPED\n";
	exit();
}

if ($_SERVER["argc"] == 1)
{
	usage();
}

// Let's go...
ini_set("soap.wsdl_cache_enabled", "0"); // in production, turn use "1" for value

$client = new samples_ShopWebServiceClient("http://$fqdn/webservices/samples/shop");
$client->setLogin($login);
$client->setPassword($password);

$cmd = $_SERVER["argv"][1];
switch($cmd)
{
	case "orders":
		if (isset($_SERVER["argv"][2]))
		{
			$dayCount = intval($_SERVER["argv"][2]);
		}
		else
		{
			$dayCount = 1;
		}
		foreach ($client->getLastDayOrders($dayCount) as $order)
		{
			echo "Order: [".$order->id.", ".$order->orderStatus."] ".$order->label." ".
				$order->totalAmountWithoutTax." HT | ".$order->totalAmountWithTax." TTC";
				if ($order->packageTrackingNumber)
				{
					echo " tracking number: ".$order->packageTrackingNumber;
				}
				echo "\n";
		}
		break;
	case "set-order-status":
		if (!isset($_SERVER["argv"][3]))
		{
			usage();
		}
		$orderId = $_SERVER["argv"][2];
		$orderStatus = $_SERVER["argv"][3];
		if (isset($_SERVER["argv"][4]))
		{
			$trackingNumber = $_SERVER["argv"][4];
		}
		else
		{
			$trackingNumber = null;
		}
		if ($client->setOrderStatus($orderId, $orderStatus, $trackingNumber))
		{
			echo "Status of order $orderId is now $orderStatus";
			if ($trackingNumber !== null)
			{
				echo " and tracking number is setted to $trackingNumber";
			}
			echo "\n";
		}
		else
		{
			echo "Could not set status of order $orderId to $orderStatus\n";
		}
		break;
	case "nav":
		foreach ($client->getShops() as $shop)
		{
			echo "Shop: [".$shop->id."] ".$shop->label."\n";
			foreach ($client->getPrimaryShelves("fr", $shop->id) as $primaryShelf)
			{
				echo "  Shelf: [".$primaryShelf->id."] ".$primaryShelf->label;
				if ($primaryShelf->visualURL)
				{
					echo " | Visual: ".$primaryShelf->visualURL;
				}
				echo "\n";
				foreach ($client->getProducts("fr", $primaryShelf->id) as $product)
				{
					echo "    Product: [".$product->id."|".$product->type."] ".$product->label." | ".$product->formattedCurrentShopPrice;
					if (!$product->stockQuantity)
					{
						$quantity = 0;
					}
					else
					{
						$quantity = $product->stockQuantity;
					}
					echo " | in stock: $quantity";
					echo "\n";
				}
			}
		}
		break;
	case "set-stock":
		if (count($_SERVER["argv"]) != 4)
		{
			usage();
		}
		if ($client->updateStock($_SERVER["argv"][2], $_SERVER["argv"][3]))
		{
			echo "Update stock OK\n";
		}
		else
		{
			echo "Could not update stock\n";
		}
		break;
	default:
		echo "Unknown command $cmd\n";
		usage();
}
