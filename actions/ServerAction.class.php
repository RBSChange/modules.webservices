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
		if (Framework::isDebugEnabled())
		{
			Framework::debug("REQUEST SOAP ".var_export(apache_request_headers(), true)."\n".var_export($soapRequest, true));
		}
		$moduleName = $request->getModuleParameter('webservices', 'moduleName');
		$serviceName = $request->getModuleParameter('webservices', 'serviceName');
		$serviceConf = Framework::getConfiguration("modules/$moduleName/webservices/$serviceName", false);
		if (!$serviceConf)
		{
			throw new Exception("Service not found");
		}

		if (!isset($serviceConf["class"]))
		{
			throw new Exception("Bad service configuration");
		}
		$className = $serviceConf["class"];
		$class = new ReflectionClass($className);
		if (!$class->implementsInterface("webservices_WebService"))
		{
			throw new Exception("Bad service class");
		}

		$isSecured = isset($serviceConf["login"]);

		if ($request->hasParameter("wsdl") || $request->hasParameter("WSDL"))
		{
			$wsdl = $this->getWsdl($className);
			header('Content-Length: ' . strlen($wsdl));
			header('Content-Type: text/xml');
			echo $wsdl;
			return null;
		}

		if ($isSecured)
		{
			// Digest authentication
			$realm = "Please provide an admin/password couple for $serviceName service";

			if (!isset($_SERVER['PHP_AUTH_DIGEST']))
			{
				return $this->mustLogin($realm);
			}
				
			$users = array($serviceConf['login'] => $serviceConf['password']);

			// analyze the PHP_AUTH_DIGEST variable
			if (!($data = $this->http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) ||
			!isset($users[$data['username']]))
			{
				return $this->mustLogin($realm);
			}

			// generate the valid response
			$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
			$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
			$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

			if ($data['response'] != $valid_response)
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
		ob_start();
		$server->handle($soapRequest);
		$out = ob_get_clean();
		if (Framework::isDebugEnabled())
		{
			Framework::debug("SOAP RESPONSE : $out");
		}
		echo $out;
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

	private function http_digest_parse($txt)
	{
		// protect against missing data
		$needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
		$data = array();
		$keys = implode('|', array_keys($needed_parts));

		if (!preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER))
		{
			return false;
		}

		foreach ($matches as $m) {
			$data[$m[1]] = $m[3] ? $m[3] : $m[4];
			unset($needed_parts[$m[1]]);
		}

		return $needed_parts ? false : $data;
	}

	private function mustLogin($realm)
	{
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
	}
}