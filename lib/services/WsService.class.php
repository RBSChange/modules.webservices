<?php
/**
 * webservices_WsService
 * @package modules.webservices
 */
class webservices_WsService extends f_persistentdocument_DocumentService
{
	/**
	 * @var webservices_WsService
	 */
	private static $instance;

	/**
	 * @return webservices_WsService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

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
		return $this->pp->createQuery('modules_webservices/ws');
	}
	
	/**
	 * Create a query based on 'modules_webservices/ws' model.
	 * Only documents that are strictly instance of modules_webservices/ws
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_webservices/ws', false);
	}
	
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
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
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
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId)
	{
		$className = $document->getPhpclass();
		webservices_ModuleService::getInstance()->compileWsdl($className);
	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postInsert($document, $parentNodeId)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preUpdate($document, $parentNodeId)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postUpdate($document, $parentNodeId)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postSave($document, $parentNodeId)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return void
	 */
//	protected function preDelete($document)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return void
	 */
//	protected function preDeleteLocalized($document)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return void
	 */
//	protected function postDelete($document)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return void
	 */
//	protected function postDeleteLocalized($document)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return boolean true if the document is publishable, false if it is not.
	 */
//	public function isPublishable($document)
//	{
//		$result = parent::isPublishable($document);
//		return $result;
//	}


	/**
	 * Methode à surcharger pour effectuer des post traitement apres le changement de status du document
	 * utiliser $document->getPublicationstatus() pour retrouver le nouveau status du document.
	 * @param webservices_persistentdocument_ws $document
	 * @param String $oldPublicationStatus
	 * @param array<"cause" => String, "modifiedPropertyNames" => array, "oldPropertyValues" => array> $params
	 * @return void
	 */
//	protected function publicationStatusChanged($document, $oldPublicationStatus, $params)
//	{
//	}

	/**
	 * Correction document is available via $args['correction'].
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Array<String=>mixed> $args
	 */
//	protected function onCorrectionActivated($document, $args)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagAdded($document, $tag)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagRemoved($document, $tag)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $fromDocument
	 * @param f_persistentdocument_PersistentDocument $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedFrom($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * @param f_persistentdocument_PersistentDocument $fromDocument
	 * @param webservices_persistentdocument_ws $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedTo($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * Called before the moveToOperation starts. The method is executed INSIDE a
	 * transaction.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Integer $destId
	 */
//	protected function onMoveToStart($document, $destId)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param Integer $destId
	 * @return void
	 */
//	protected function onDocumentMoved($document, $destId)
//	{
//	}

	/**
	 * this method is call before saving the duplicate document.
	 * If this method not override in the document service, the document isn't duplicable.
	 * An IllegalOperationException is so launched.
	 *
	 * @param webservices_persistentdocument_ws $newDocument
	 * @param webservices_persistentdocument_ws $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function preDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//		throw new IllegalOperationException('This document cannot be duplicated.');
//	}

	/**
	 * this method is call after saving the duplicate document.
	 * $newDocument has an id affected.
	 * Traitment of the children of $originalDocument.
	 *
	 * @param webservices_persistentdocument_ws $newDocument
	 * @param webservices_persistentdocument_ws $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function postDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//	}

	/**
	 * Returns the URL of the document if has no URL Rewriting rule.
	 *
	 * @param webservices_persistentdocument_ws $document
	 * @param string $lang
	 * @param array $parameters
	 * @return string
	 */
//	public function generateUrl($document, $lang, $parameters)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return integer | null
	 */
//	public function getWebsiteId($document)
//	{
//	}

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @return website_persistentdocument_page | null
	 */
//	public function getDisplayPage($document)
//	{
//	}

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

	/**
	 * @param webservices_persistentdocument_ws $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrserachResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'webservices', 'template' => 'Webservices-Inc-WsResultDetail');
//	}
}