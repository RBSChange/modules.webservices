<?php
/**
 * webservices_GetSchemaAction
 * @package modules.webservices.actions
 */
class webservices_GetSchemaAction extends f_action_BaseAction
{

	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$moduleName = $request->getModuleParameter('webservices', 'moduleName');
		$serviceName = $request->getModuleParameter('webservices', 'serviceName');
		$className = $moduleName . "_" . ucfirst($serviceName) . "WebService";
		header('Content-Type: text/xml');	
		echo $this->getWsdl($className);
		return null;
	}

	function isSecure()
	{
		return false;
	}

	// private methods
	private function getWsdl($webserviceClassName)
	{
		return webservices_ModuleService::getInstance()->getWsdl($webserviceClassName);
	}
}