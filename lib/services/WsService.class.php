<?php
/**
 * @package modules.webservices
 * @method webservices_WsService getInstance()
 */
class webservices_WsService extends f_persistentdocument_DocumentService
{
	/**
	 * @return webservices_persistentdocument_ws
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_webservices/ws');
	}

	/**
	 * Create a query based on 'modules_webservices/ws' model.
	 * Return document that are instance of modules_webservices/ws,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_webservices/ws');
	}
	
	/**
	 * Create a query based on 'modules_webservices/ws' model.
	 * Only documents that are strictly instance of modules_webservices/ws
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_webservices/ws', false);
	}
	
	/**
	 * @param string $className
	 * @return webservices_persistentdocument_ws | null
	 * @throws Exception
	 */
	public function getSecureExcuteByClass($className)
	{
		$ws = $this->createQuery()
			->add(Restrictions::published())
			->add(Restrictions::eq('phpclass', $className))
			->setProjection(Projections::property('id'), Projections::property('secured'))
			->findUnique();
		if ($ws === null)
		{
			throw new Exception("Invalid or inactive $className service");
		}
		return ($ws['secured']) ? $ws['id'] : 0;
	}
	
	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preSave($document, $parentNodeId)
	{
		$className = $document->getPhpclass();
		if (!f_util_ClassUtils::classExists($className))
		{
			throw new Exception("Invalid class name: $className");
		}
		$class = new ReflectionClass($className);
		if (!$class->implementsInterface("webservices_WebService"))
		{
			throw new Exception($className . " does not implement webservices_WebService interface");
		}
		
	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId)
	{
		$className = $document->getPhpclass();
		webservices_ModuleService::getInstance()->compileWsdl($className);
	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$resume = parent::getResume($document, $forModuleName, $allowedSections);
		$class = $document->getPhpclass();
		list($moduleName , $serviceName) = explode('_', str_replace('WebService', '', $class));
		$serviceName = strtolower(substr($serviceName, 0, 1)) . substr($serviceName, 1);
		$resume['properties']['currenturl'] =  Framework::getUIBaseUrl() . '/webservices/'. $moduleName. '/' . $serviceName . '?wsdl';
		$resume['properties']['currenturljson'] =  Framework::getUIBaseUrl() . '/servicesjson/'. $moduleName. '/' . $serviceName . '?info';
		return $resume;
	}
}