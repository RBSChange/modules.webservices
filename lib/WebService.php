<?php
/**
 * Base interface for web services. Just used as marker for now (and probably later ... :))
 */
interface webservices_WebService
{
	// empty
}

// Helper classes to use when returning PersistentDocuments

/**
 * Basic representation of a persistent document array
 */
class webservices_PersistentDocumentArray
{
	/**
	 * @var Integer
	 */
	private $count;

	/**
	 * @var webservices_PersistentDocument[]
	 */
	private $docs;

	/**
	 * @param webservices_PersistentDocument[] $documents
	 */
	function __construct($documents)
	{
		$this->count = count($documents);
		$this->docs = $documents;
	}

	/**
	 * @param f_persistentdocument_PersistentDocument[] $documents
	 * @param String[] $propNames
	 * @return webservices_PersistentDocumentArray
	 */
	static function fromDocumentArray($documents, $propNames)
	{
		return new self(webservices_PersistentDocument::fromDocumentArray($documents, $propNames));
	}
}

/**
 * Basic representation of a persistent document
 */
class webservices_PersistentDocument
{
	public $id;
	public $label;
	public $type;
	public $lang;

	function __construct($id, $label = null, $type = null)
	{
		$this->id = $id;
		$this->label = $label;
		$this->type = $type;
	}

	/**
	 * @return f_persistentdocument_PersistentDocument
	 */
	function toDocument()
	{
		$doc = DocumentHelper::getDocumentInstance($this->id, $this->type);
		/* TODO: something like the following
		BeanUtils::populate($doc, get_object_vars($this), null, array("id", "label", "lang", "type"));
		*/
		return $doc;
	}

	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param String[] $propNames properties to expose. N.B: id,
	 *  label, lang and type properties are always exposed
	 * @return webservices_PersistentDocument
	 */
	static function fromDocument($document, $propNames = null)
	{
		$doc = new self($document->getId(), $document->getLabel(), $document->getPersistentModel()->getName());
		$doc->lang = $document->getLang();
		if ($propNames !== null)
		{
			foreach ($propNames as $propName)
			{
				if (BeanUtils::hasProperty($document, $propName))
				{
					$value = BeanUtils::getProperty($document, $propName);
				}
				else
				{
					$getterName = "get".ucfirst($propName);
					if (!f_util_ClassUtils::methodExists($document, $getterName))
					{
						$value = null;
						//throw new Exception("Could not get $propName value on document ".get_class($document));
					}
					else
					{
						$value = $document->{$getterName}();
					}
				}

				if ($value instanceof f_persistentdocument_PersistentDocument)
				{
					$value = self::fromDocument($value);
				}
				elseif (is_array($value) && f_util_ArrayUtils::isNotEmpty($value) && $value[0] instanceof f_persistentdocument_PersistentDocument)
				{
					$value = self::fromDocumentArray($documents);
				}
				
				if ($value !== null)
				{
					$doc->$propName = $value;
				}
			}
		}
		return $doc; 
	}

	/**
	 * @param f_persistentdocument_PersistentDocument[] $documents
	 * @param String[] $propNames
	 * @return webservices_PersistentDocument[]
	 */
	static function fromDocumentArray($documents, $propNames)
	{
		/*
		if (f_util_ArrayUtils::isEmpty($documents))
		{
			return null;
		}
		*/
		$docs = array();
		foreach ($documents as $document)
		{
			$docs[] = self::fromDocument($document, $propNames);
		}
		return $docs;
	}
}