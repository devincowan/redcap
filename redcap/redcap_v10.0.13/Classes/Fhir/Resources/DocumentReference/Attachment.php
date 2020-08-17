<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\DocumentReference;

use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResource;

/**
 * @property string $url
 */
class Attachment extends FhirResource
{
    private $binary_data;
    /**
     * path to the default stylesheet path
     * the stylesheet can transform a ClinicalDocument to HTML
     *
     * @var string
     */
    private $default_stylesheetPath = APP_PATH_DOCROOT.'Resources/misc/clinical_documents/'.'CDA.xsl';

    public function getData()
    {
        $data = array(
            // 'binary_data' => $this->binary_data,
            'download_url' => $this->getDownloadUrl(),
            'text' => $this->parseText($this->binary_data),
        );
        return $data;
    }

    public function getDownloadUrl()
    {
        $url = $this->url;
        $url_parts = explode('/', $url);
        $document_ID = end($url_parts);
        return sprintf("/download?document_id=%s", $document_ID);
    }

    public function setBinaryData($binary_data)
    {
        $this->binary_data = $binary_data;
    }

    public function stripHTML()
    {
        // https://regex101.com/r/78S1wx/1
    }

    private function toHTML($clinicalDocumentXML)
    {
        $html = array();
        $title = sprintf("<h3>%s</h3>", $clinicalDocumentXML->title);
        $html[] = $title;
        $components = $clinicalDocumentXML->component->structuredBody->component;
        foreach ($components as $component) {
            $section = $component->section;
            $title = sprintf("<h4>%s</h4>", $section->title);
            $html[] = $title;
            $textChildren = $section->text->children();
            foreach($textChildren as $textXML)
            {
                $html[] = $textXML->asXML();
            }
            $html[] = sprintf("<p>%s</p>", str_repeat("-",50)).PHP_EOL;
        }
        return implode(PHP_EOL, $html);
    }

    /**
     * use a XSLT stylesheet to transform an XML string
     *
     * @param string $stylesheetPath path to XSLT document
     * @return void
     */
    public function transformXML($stylesheetPath)
    {
        $xmlString = $this->binary_data;
        $proc = new \XsltProcessor;
        $clinicalDocumentStylesheet = \DOMDocument::load($stylesheetPath);
        $proc->importStylesheet($clinicalDocumentStylesheet);  
        // echo $proc->transformToXML(DOMDocument::load("script.xml"));
        $domDocument = \DOMDocument::loadXML($xmlString);
        /* $domDocument = \DOMDocument::loadHTML($string);
        $body = $domDocument->getElementsByTagName('body'); */
        $result = $proc->transformToXML($domDocument);
        // file_put_contents(EDOC_PATH.'result.html', $result); //save HTML document locally
        return $result;
    }

    public function parseText($html_string)
    {
        if(!class_exists('\Soundasleep\Html2Text'))
        {
            require_once(APP_PATH_DOCROOT.'../custom_libraries/vendor/autoload.php');
        }
        $options = array(
            'ignore_errors' => true,
            // other options go here
        );
        // $text = \Soundasleep\Html2Text::convert($html_string, $options);
        return $text;
    }

    /**
     * Undocumented function
     *
     * @param object|array $object
     * @param array $flattened
     * @param array $path
     * @param string $path_separator character that separates the elements of the path
     * @return array
     */
    private function flatten_object($object, $flattened=array(), $path=array(), $path_separator = ':')
    {
        foreach($object as $key => $value)
        {
            $path[] = $key;
            if(is_array($value) || is_object($value))
            {
                $flattened = $this->flatten_object($value, $flattened, $path);
                array_pop($path);
                continue;
            }
            $string_path = implode($path_separator, $path);
            $flattened[$string_path] = $value; // the complete path
            // $flattened[$key] = $value; // just the final key
            array_pop($path);
        }
        return $flattened;
    }


    public function download($access_token)
    {
        // \FileManager::forceDownload('file', $this->url);
    }

}