<?php

if (!defined(__DIR__)){
  define(__DIR__, dirname(__FILE__));
  }

require_once __DIR__ . "/OntologyProvider.php";

class OntologyManager {

  public $providers = array();

  public static function getOntologyManager(){
    if (!isset($GLOBALS['ontology_manager'])){
      //error_log('Initiate Ontology Manager');
      $manager = new OntologyManager();
      // Add provider to ontology manager
      global $enable_ontology_auto_suggest;
      if ($enable_ontology_auto_suggest){
        $manager->addProvider(new BioPortalOntologyProvider());
      }
      $GLOBALS['ontology_manager'] = $manager;
    }
    return $GLOBALS['ontology_manager'];
  }

  public static function hasOntologyProviders(){
    $m = OntologyManager::getOntologyManager();
    return !empty($m->providers);
  }


  public function addProvider(OntologyProvider $p){
    $old = $this->providers[$p->getServicePrefix()];
    if ($old !== $p){
      $this->providers[$p->getServicePrefix()] = $p;
      return $old;
    }
    return null;
  }

  public function removeProvider(OntologyProvider $p){
    if (array_key_exists($p->getServicePrefix(), $this->providers)){
      unset($this->providers[$p->getServicePrefix()]);
      return true;
    }
    return false;
  }

  public function removeProviderByKey(string $key){
    if (array_key_exists($key, $this->providers)){
      $old = $this->providers[$key];
      unset($this->providers[$key]);
      return $old;
    }
    return null;
  }

  public function getProviderForService(string $prefix){
    if (substr($prefix, -1) == ':') {
      // still has trailing ':'
      return $this->providers[substr($prefix, 0, -1)];
    }
    return $this->providers[$prefix];
  }

  public function getProviderList(){
    $result = array();
    foreach ($this->providers as $p){
      $result[$p->getServicePrefix()] = $p->getProviderName();
    }
    return $result;
  }

  public function generateDesignForm(){
    global $lang;
    $providerList = "[";
    $first = true;
    foreach ($this->providers as $p){
      if ($first){
        $first = false;
      }
      else {
        $providerList .= ", ";
      }
      $providerList .= "'".$p->getServicePrefix()."'";
    }
    $providerList .= "]";

    $callChildCallbacks = "";
    foreach ($this->providers as $p){
      $functionName = $p->getServicePrefix()."_ontology_changed";
      $callChildCallbacks .= "if (typeof {$functionName} === 'function') {$functionName}(service, category);\n"; 
    }
    
    $ontologySelectionJS = <<<EOD
<script type="text/javascript">

  function showSelectedOntologyProvider(service){
    var providerPrefixes = {$providerList};

    for (var i = 0; i < providerPrefixes.length; i++){ 
	   const p = providerPrefixes[i];
       var providerDiv = 'div_ontology_provider_' + p;
       if (service == p){
         $('#'+providerDiv).show();
       }
       else {
         $('#'+providerDiv).hide();
       }
    }
  }

  function notifyOntologyProviders(service, category){
    {$callChildCallbacks}
  }

  function update_ontology_selection(service, category){

    if (service && category){
      // selected category
      $('#val_type').val(''); // clear validation
      hide_val_minmax(); // deal with min max
      $('#ontology_auto_suggest').val(service + ":" + category);
    }
    else {
      $('#ontology_auto_suggest').val('');
    }
    $('#ontology_service_select').val(service); // make sure have right selection on ontology provider
    showSelectedOntologyProvider(service);
    notifyOntologyProviders(service, category);
  }
</script>

EOD;

    $providerOptions = "";
    $providerDivs = "";
    foreach ($this->providers as $p){
      $prefix = $p->getServicePrefix();
      $name = $p->getProviderName();
      $providerOptions .= "<option value='{$prefix}'>{$name}</option>\n";
      $providerDivName = 'div_ontology_provider_' . $prefix;
      $providerDivs .= "<div id='{$providerDivName}' style='display:none'>\n";
      $providerDivs .= $p->getOnlineDesignerSection();
      $providerDivs .= "</div>\n";
    }
  

  $ontologyHtml = <<<EOD
<div id='div_ontology_autosuggest'>
  <div style='margin:3px 0 8px;color:#888;'>&ndash; {$lang['global_47']} &ndash;</div>
  <div id='div_ontology_service_select'>
    <input type="hidden" name="ontology_auto_suggest" id="ontology_auto_suggest"></input>
    <select id='ontology_service_select' name='ontology_service_select'
            onchange='update_ontology_selection(this.options[this.selectedIndex].value, "")'
            class='x-form-text x-form-field' style='width:330px;max-width:330px;'>
      <option value=''>{$lang['design_771']}</option>
      {$providerOptions}
    </select>
  </div>
  {$providerDivs}
</div>
EOD;

  //print $ontologySelectionJS;
  //print $ontologyHtml;
  return $ontologySelectionJS.$ontologyHtml;
  }

    public static function buildOntologySelection()
    {
        // Do not do anything if server cannot make outbound HTTP calls
        global $allow_outbound_http;
        if (!$allow_outbound_http) return '';

        $result = '';
        try {
            $manager = OntologyManager::getOntologyManager();
            $result = $manager->generateDesignForm();
        }
        catch (Exception $e){ }
        return $result;
    }

	public static function searchOntology($service, $category, $search_term='', $result_limit=20){
    $manager = OntologyManager::getOntologyManager();
    $provider = $manager->providers[$service];
    if ($provider){
      $s=$service.':';
      $length = strlen($s);
      if (substr($category, 0, $length) === $s){
        // seems to have prefix twice
        // error_log("Stripping leading {$s}");
        $category = substr($category, $length);
      }
      // error_log("Searching ontology {$service}, {$category}");
      $result = $provider->searchOntology($category, $search_term, $result_limit);
      // error_log("Results : " . print_r($result, TRUE));
      return $result;
    }
    
    // error_log("No provider for service {$service}");
        return array();
  }
}
