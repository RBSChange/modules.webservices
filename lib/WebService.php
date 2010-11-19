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
}