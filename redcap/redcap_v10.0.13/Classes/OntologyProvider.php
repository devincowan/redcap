<?php

/**
   An ontology provider is a class which allows a thrid party service such as bioportal
   or fhir to provide one or more ontolgies.

   This ontology is specified in the definition of a field as the enum_element and
   will be persisted using the form service:category

   The service name is used to determine which ontology provider to use.

   Choosing the ontology:

   Selecting an ontology for a field is not a two step process, firstly a service
   is selected from the list of available providers and this will allow the selection of
   a catergory from the service. The provider needs to produce a string which will
   live in a hidden div for the service that will be shown when the service is shown,
   this div will contain ui elements for selecting the category from the service. Once
   a selection if made, the ui needs to call a javascript function
   update_ontology_selection($service, $category), this will set a hidden form element used
   to set the value on the field. Additionally the provider may want to include a javascript
   function which will be called when the field is populated so that the ui can reflect the
   current selection. This function should take the form <service>_ontology_changed(service, catgeory).

   Searching the ontology:
   The provider needs to supply a mechanism which will be used by the autocomplete to
   search the ontology. This method would make any required ajax calls to return a set of
   values and a label to go with the label.
*/

interface OntologyProvider {

  /**
    * return the name of the ontology service as it will be display on the service selection
    * drop down.
    */
  public function getProviderName();

  /**
    return the prefex used to denote ontologies provided by this provider.
   */
  public function getServicePrefix();

  /**
    * Return a string which will be placed in the online designer for
    * selecting an ontology for the service.
    * When an ontology is selected it should make a javascript call to 
    * update_ontology_selection($service, $category)
    *
    * The provider may include a javascript function
    * <service>_ontology_changed(service, category)
    * which will be called when the ontology selection is changed. This function
    * would update any UI elements is the service matches or clear the UI elemements
    * if they do not.
    */
  public function getOnlineDesignerSection();

	/**
	 * Search API with a search term for a given ontology
	 * Returns array of results with Notation as key and PrefLabel as value.
	 */
	public function searchOntology($category, $search_term, $result_limit);


  /**
   *  Takes the value and gives back the label for the value.
   */
  public function getLabelForValue($category, $value);

}

