<?php

namespace App\Models;

class XmlModel extends Base
{
	protected static $dataPath = '/Var';
	protected static $xmlNameSpace = null;
	protected static $schemes = array();
	protected $xml;
	protected $autoInit = FALSE;
	public static function GetDataPath() {
		return static::$dataPath;
	}
	public static function GetByPath ($path = '') {
		$path = static::sanitizePath($path);
		// if request path is "/" or "/any-directory/any-subdirectory/" - fix path to "/index" or "/any-directory/any-subdirectory/index"
		$xmlPath = (mb_substr($path, mb_strlen($path) - 1, 1) === '/') ? $path . 'index' : $path ;
		$xmlFullPath = \MvcCore::GetInstance()->GetRequest()->AppRoot . static::$dataPath;
		$fileFullPath = str_replace('\\', '/', $xmlFullPath . $xmlPath . '.xml');
		if (!file_exists($fileFullPath)) {
			return FALSE;
		} else {
			return static::xmlLoadAndSetupModel($fileFullPath, $path);
		};
    }
	protected static function xmlLoadAndSetupModel ($fileFullPath, $path) {
		$lastSlashPos = mb_strrpos($fileFullPath, '/');
		$dirFullPath = mb_substr($fileFullPath, 0, $lastSlashPos === FALSE ? mb_strlen($fileFullPath) : $lastSlashPos + 1);
		$content = file_get_contents($fileFullPath);
		static::loadXmlNamespaceAndSchema($content, $fileFullPath);
		static::processReplacementsBeforeParsing($content);
		$xml = static::xmlLoadXmlFromString($content, $fileFullPath);
		$nameSpaces = $xml->getNamespaces();
		$counter = 0;
		foreach ($nameSpaces as $nameSpace => $schemePath) {
			if ($counter === 0) static::$xmlNameSpace = $nameSpace;
			$xml->registerXPathNamespace($nameSpace, realpath($dirFullPath . $schemePath));
			$counter++;
		}
		$result = new static();
		$result->xmlSetUp($xml);
		$result->Path = $path;
		return $result;
	}
	protected static function loadXmlNamespaceAndSchema (& $xmlStr, & $fileFullPath) {
		preg_match("# xmlns\:([a-z0-9]*)=\"([^\"]*)\"#", $xmlStr, $matches);
		if (!isset($matches[1]) || !isset($matches[2])) {
			throw new \Exception(
				"[".get_called_class()."] No XML namespace and schema defined in file: '$fileFullPath'. "
					."Define namespace and scheme file in root node: "
					."'<schemeName:rootNodeName xmlns:schemeName=\"../Path/To/Scheme.xsd\">'"
			);
		}
		$ns = $matches[1];
		static::$xmlNameSpace = $ns;
		$xmlScheme = NULL;
		if (!isset(static::$schemes[$ns])) {
			$scheme = (object) array(
				'columnTypes'	=> array(),
				'replacements'	=> array(),
			);
			$schemeFileFullPath = \MvcCore::GetInstance()->GetRequest()->AppRoot . self::$dataPath . $matches[2];
			$xmlScheme = static::loadXmlScheme($schemeFileFullPath);
			$rootNodeDescriptorBase = $xmlScheme->children('xs', TRUE);
			$rootNodeDescriptorType = $rootNodeDescriptorBase->children('xs', TRUE);
			$rootNodeDescriptorSequence = $rootNodeDescriptorType->children('xs', TRUE);
			foreach ($rootNodeDescriptorSequence->children('xs', TRUE) as $dataNode) {
				$attrs = $dataNode->attributes();
				$nodeName = trim((string)$attrs['name']);
				if (!isset($attrs['type'])) {
					// do any common thing with structured nodes...
				} else {
					$nodeType = substr(trim((string)$attrs['type']), 3);
					$scheme->columnTypes[$nodeName] = $nodeType;
					if ($nodeType == 'html') {
						$scheme->replacements[] = array(
							array("<$ns:$nodeName>",			"</$ns:$nodeName>",), 
							array("<$ns:$nodeName><![CDATA[",	"]]></$ns:$nodeName>",), 
						);
					}
				}
			}
			static::$schemes[$ns] = $scheme;
		}
	}
	protected static function loadXmlScheme ($schemeFileFullPath) {
		$schemeFileRawContent = file_get_contents($schemeFileFullPath);
		return static::xmlLoadXmlFromString($schemeFileRawContent, $schemeFileFullPath);
	}
	protected static function processReplacementsBeforeParsing (& $content) {
		$scheme = static::$schemes[static::$xmlNameSpace];
		foreach ($scheme->replacements as $replacementItem) {
			$content = str_replace(
				$replacementItem[0],
				$replacementItem[1],
				$content
			);
		}
	}
	protected static function xmlLoadXmlFromString (& $xmlStr, & $fileFullPath) {
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($xmlStr);
		$xmlPossibleErrors = libxml_get_errors();
		if (count($xmlPossibleErrors)) {
			$msgs = array();
			$clsName = get_called_class();
			foreach ($xmlPossibleErrors as $e) {
				$msg = $e->message;
				$line = $e->line;
				$column = $e->column;
				$msgs[] = "[$clsName] $msg (file: $fileFullPath, line: $line, column: $column)";
			}
			throw new \Exception (implode('<br />', $msgs));
		}
		return $xml;
	}
	protected static function sanitizePath ($path) {
		return preg_replace("#[^a-zA-Z0-9_\-/\.]#", '', str_replace('..', '', $path));
	}
	protected function xmlSetUp ($xml) {
		$this->xml = $xml;
		$columnTypes = static::$schemes[static::$xmlNameSpace]->columnTypes;
		foreach ($xml->children(static::$xmlNameSpace, TRUE) as $dataNode) {
			$nodeName = $dataNode->getName();
			$rawNodeValue = trim((string)$dataNode);
			$propertyName = \MvcCore\Tool::GetPascalCaseFromDashed($nodeName);
			$dataType = 'string';
			if (isset($columnTypes[$nodeName])) {
				$dataType = $columnTypes[$nodeName];
			}
			$this->setUpXmlValueByXsd($rawNodeValue, $propertyName, $dataType);
		}
	}
	protected function setUpXmlValueByXsd ($rawNodeValue, $propertyName, $dataType) {
		if ($dataType == 'integer') {
			$this->$propertyName = intval($rawNodeValue);
		} else if ($dataType == 'float') {
			$this->$propertyName = floatval($rawNodeValue);
		} else if ($dataType == 'boolean') {
			$this->$propertyName = boolval($rawNodeValue);
		} else if ($dataType == 'html') {
			$this->$propertyName = str_replace(
				array('%basePath'),
				array(\MvcCore::GetInstance()->GetRequest()->BasePath,), 
				$rawNodeValue
			);
		} else {
			$this->$propertyName = (string)$rawNodeValue;
		}
	}
	protected function xmlGetNode ($name) {
		$nodes = $this->xml->xpath(static::$xmlNameSpace . ':' . $name); 
		if (count($nodes)) return (string) $nodes[0];
		return '';
	}
	protected function xmlGetNodes ($nodeNamesPath) {
		$namespacedPath = ltrim(str_replace('/', '/'.static::$xmlNameSpace.':', '/' . trim($nodeNamesPath, '/')), '/');
		$nodes = $this->xml->xpath($namespacedPath);
		if (count($nodes)) return $nodes;
		return array();
	}
	public function __toString() {
		return $this->xml->asXML();
	}
	// for serialize() method:
	public function __sleep() {
		$this->xml = $this->xml->asXML();
		return array('xml');
	}
}