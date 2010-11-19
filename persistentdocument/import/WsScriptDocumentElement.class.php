<?php
/**
 * webservices_WsScriptDocumentElement
 * @package modules.webservices.persistentdocument.import
 */
class webservices_WsScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return webservices_persistentdocument_ws
     */
    protected function initPersistentDocument()
    {
    	return webservices_WsService::getInstance()->getNewDocumentInstance();
    }
    
    /**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_webservices/ws');
	}
}