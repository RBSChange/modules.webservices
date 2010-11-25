<?php
/**
 * Base interface for web services. Just used as marker for now (and probably later ... :))
 */
interface webservices_WebService
{
	// empty
}

class webservices_WebServiceBase implements webservices_WebService
{
	/**
	 * @var webservices_WsdlTypes
	 */
	protected $wsdlTypes = null;
	
	/**
	 * @return webservices_WsdlTypes
	 */
	public function getWsdlTypes()
	{
		if ($this->wsdlTypes === null)
		{
			$className = get_class($this);
			$this->wsdlTypes = webservices_ModuleService::getInstance()->getServiceTypeDefinitions($className);
		}
		return $this->wsdlTypes;
	}
	
	/**
	 * @return f_persistentdocument_TransactionManager
	 */
	protected function getTransactionManager()
	{
		return f_persistentdocument_TransactionManager::getInstance();
	}
	
	/**
	 * @param string $lang
	 * @throws Exception
	 */
	protected function setLang($lang)
	{
		if ($lang !== null)
		{
			if (!in_array($lang, RequestContext::getInstance()->getSupportedLanguages()))
			{
				throw new Exception('Invalid lang parameter:' . $lang);
			}
			RequestContext::getInstance()->setLang($lang);
		}
	}
	
	/**
	 * 
	 * @param f_persistentdocument_PersistentDocumentImpl $document
	 */
	protected function deleteDocument($document)
	{
		$tm = $this->getTransactionManager();
		$langs = array_reverse($document->getI18nInfo()->getLangs());
		try 
		{
			$tm->beginTransaction();
			foreach ($langs as $lang) 
			{
				$this->setLang($lang);
				$document->getDocumentService()->delete($document);
			}
			$tm->commit();	
		}
		catch (Exception $e)
		{
			$tm->rollBack($e);
			throw $e;
		}
	}
}