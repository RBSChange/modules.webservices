<?php
/**
 * webservices_serverAction
 * @package modules.webservices.actions
 */
class webservices_ServerAction extends f_action_BaseAction
{

	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$soapRequest = file_get_contents("php://input");
		$moduleName = $request->getModuleParameter('webservices', 'moduleName');
		$serviceName = $request->getModuleParameter('webservices', 'serviceName');
		
		$className = $moduleName . "_" . ucfirst($serviceName) . "WebService";
		
		$secureId = webservices_WsService::getInstance()->getSecureExcuteByClass($className);
		
		if ($request->hasParameter("wsdl") || $request->hasParameter("WSDL"))
		{
			$wsdl = $this->getWsdl($className);
			header('Content-Length: ' . strlen($wsdl));
			header('Content-Type: text/xml');
			echo $wsdl;
			return null;
		}

		if ($secureId > 0)
		{
			// Basic authentication
			$realm = "Please provide an admin/password couple for $serviceName service";
			if (!isset($_SERVER['PHP_AUTH_USER']))
			{
				return $this->mustLogin($realm);
			}
			$login = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
			
			$user = users_UserService::getInstance()->getIdentifiedBackendUser($login, $password);
			if ($user === null)
			{
				return $this->mustLogin($realm);
			}
			if (!f_permission_PermissionService::getInstance()->hasPermission($user, 'modules_webservices.Execute', $secureId))
			{
				return $this->mustLogin($realm);
			}
			
		}

		if (Framework::inDevelopmentMode())
		{
			ini_set("soap.wsdl_cache_enabled", "0");
		}

		$wsdl = $this->getWsdlPath($className);
		$server = new SoapServer($wsdl, array('uri' => "http://change.rbs.fr/ws/$serviceName"));
		// with PHP >= 5.2.0 we could use setObject()
		webservices_WebServiceProxy::$serviceClassName = $className;
		$server->setClass("webservices_WebServiceProxy");
		//$server->setClass($className);
		
		if (substr($soapRequest, 0, 6) != "<?xml ")
		{
			Framework::debug("SOAP: add XML Header");
			$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>'.$soapRequest;
		}
		
		if (Framework::isInfoEnabled())
		{
			webservices_ModuleService::getInstance()->log("SOAP REQUEST $serviceName:" . $soapRequest);
		}
		
		try
		{
			ob_start();
			$server->handle($soapRequest);
			$out = ob_get_clean();
			if (Framework::isInfoEnabled())
			{
				webservices_ModuleService::getInstance()->log("SOAP RESPONSE $serviceName:" . $out);
			}
			echo $out;
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			webservices_ModuleService::getInstance()->log("SOAP RESPONSE EXCEPTION $serviceName: " . $e->getCode() .','. $e->getMessage());
			$server->fault($e->getCode(), $e->getMessage());
		}
		return null;
	}

	function isSecure()
	{
		return false;
	}

	// private methods

	private function getWsdlPath($webserviceClassName)
	{
		return webservices_ModuleService::getInstance()->getWsdlPath($webserviceClassName);
	}

	private function getWsdl($webserviceClassName)
	{
		return webservices_ModuleService::getInstance()->getWsdl($webserviceClassName);
	}

	private function mustLogin($realm)
	{
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="'.$realm.'"');
	}
}