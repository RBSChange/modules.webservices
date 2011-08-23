<?php
/**
 * webservices_ServerJSONAction
 * @package modules.webservices.actions
 */
class webservices_ServerJSONAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		try
		{
			$callData = $this->parseRequest($context, $request);
			if (isset($callData["info"]))
			{
				return $this->doInfo($callData);
			}
			
			if (!$this->doSecurity($callData))
			{
				return null;
			}
		
			header('Content-Type: application/json; charset=utf-8');
			$message = $callData["message"];
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
			
			if (Framework::isInfoEnabled())
			{
				webservices_ModuleService::getInstance()->log("JSON REQUEST :" . var_export($json, true));
			}
		
			$service = $this->getJSONProxy($callData);
			$service->handle($method, $arguments);
		}
		catch (Exception $e)
		{
			$this->handleException($e);
		}
		
		return null;
	}
	
	/**
	 * @param array $callData
	 * @return boolean
	 */
	protected function doSecurity($callData)
	{
		$serviceName = $callData["serviceName"];
		$className = $callData["className"];
		
		$secureId = webservices_WsService::getInstance()->getSecureExcuteByClass($className);			
		if ($secureId > 0)
		{
			// Basic authentication
			$realm = "Please provide an admin/password couple for $serviceName service";
			if (!isset($_SERVER['PHP_AUTH_USER']))
			{
				$this->mustLogin($realm);
				return false;
			}
					
			$login = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
				
			$user = users_UserService::getInstance()->getIdentifiedBackendUser($login, $password);
			if ($user === null)
			{
				$this->mustLogin($realm);
				return false;
			}
			if (!change_PermissionService::getInstance()->hasPermission($user, 'modules_webservices.Execute', $secureId))
			{
				$this->mustLogin($realm);
				return false;
			}
		}
		return true;
	}
	
	/**
	 * @param array $callData
	 */
	protected function doInfo($callData)
	{
		$moduleName = $callData["moduleName"];
		$serviceName = $callData["serviceName"];
		$className = $callData["className"];
		
		$template = TemplateLoader::getInstance()->setMimeContentType('html')
			->setPackageName('modules_webservices')
			->setDirectory('templates')->load('jsondef');
		$service = new webservices_ServiceJSONProxy($className);
		$template->setAttribute('jsonurl', Framework::getUIBaseUrl() . "/servicesjson/$moduleName/$serviceName");
		$template->setAttribute('moduleName', $moduleName);
		$template->setAttribute('serviceName', $serviceName);
		$template->setAttribute('jsonService', $service);
		echo $template->execute();
		return null;
	}
	
	/**
	 * @param array $callData
	 * @return webservices_ServiceJSONProxy
	 */
	protected function getJSONProxy($callData)
	{
		$className = $callData["className"];
		return new webservices_ServiceJSONProxy($className);
	}
	
	/**
	 * @param Exception $e
	 */
	protected function handleException($e)
	{
		header('Content-Type: application/json; charset=utf-8');
		$error = array('error' => $e->getMessage());
		if (Framework::inDevelopmentMode())
		{
			$error['stackTrace'] = $e->getTraceAsString();
		}
		echo JsonService::getInstance()->encode($error);
	}
	
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 * @return array
	 */
	protected function parseRequest($context, $request)
	{
		$moduleName = $request->getModuleParameter('webservices', 'moduleName');
		$serviceName = $request->getModuleParameter('webservices', 'serviceName');
		$className = $moduleName . "_" . ucfirst($serviceName) . "WebService";
		$callData = array("moduleName" => $moduleName, "serviceName" => $serviceName, "className" => $className);
		
		if ($request->hasParameter("info") || $request->hasParameter("INFO"))
		{
			$callData["info"] = true;
		}
		
		$callData["message"] = $request->getParameter('REQUEST');
		
		return $callData;
	}

	function isSecure()
	{
		return false;
	}

	private function mustLogin($realm)
	{
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="'.$realm.'"');
	}
}