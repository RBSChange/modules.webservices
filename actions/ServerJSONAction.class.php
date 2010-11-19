<?php
/**
 * webservices_ServerJSONAction
 * @package modules.webservices.actions
 */
class webservices_ServerJSONAction extends f_action_BaseJSONAction
{

	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		header('Content-Type: application/json; charset=utf-8');
		try 
		{
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
			
			$message = $request->getParameter('REQUEST');
			if (empty($message) || $message[0] !== '{')
			{
				throw new Exception("Bad REQUEST : " . $message);
			}
			$json = JsonService::getInstance()->decode($message);
			if (!isset($json['method']) || !is_string($json['method']))
			{
				throw new Exception("Invalid JSON REQUEST method" . var_export($json, true));
			}
			$method = $json['method'];
			$arguments = isset($json['arguments']) ? $json['arguments'] : array();
			if (!is_array($arguments))
			{
				throw new Exception("Invalid JSON REQUEST arguments" . var_export($arguments, true));
			}
			$service = new webservices_ServiceJSONProxy($className);
			$service->handle($method, $arguments);
		}
		catch (Exception $e)
		{
			$error = array('error' => $e->getMessage());
			if (Framework::inDevelopmentMode())
			{
				$error['stackTrace'] = $e->getTraceAsString();
			}
			echo JsonService::getInstance()->encode($error);
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