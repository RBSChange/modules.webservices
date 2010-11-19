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
		try 
		{
			$moduleName = $request->getModuleParameter('webservices', 'moduleName');
			$serviceName = $request->getModuleParameter('webservices', 'serviceName');
			$className = $moduleName . "_" . ucfirst($serviceName) . "WebService";
			
			$secureId = webservices_WsService::getInstance()->getSecureExcuteByClass($className);
			Framework::info(__METHOD__ . $secureId);
			
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
			
			header('Content-Type: application/json; charset=utf-8');
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
			header('Content-Type: application/json; charset=utf-8');
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

	private function mustLogin($realm)
	{
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="'.$realm.'"');
	}
}