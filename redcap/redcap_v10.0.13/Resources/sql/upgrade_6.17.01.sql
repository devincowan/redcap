-- Allow customizability to the BioPortal web service
INSERT INTO redcap_config (field_name, value) VALUES
('bioportal_api_url', 'http://data.bioontology.org/');
update redcap_config set value = '' where field_name in ('bioportal_ontology_list', 'bioportal_ontology_list_cache_time');