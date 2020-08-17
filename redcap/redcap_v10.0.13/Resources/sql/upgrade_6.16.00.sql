-- Set all sliders with '' alignment to 'RH' for backwards compatibility
update redcap_metadata set custom_alignment = if (custom_alignment = 'LV', 'LH', 'RH') 
	where element_type = 'slider' and (custom_alignment = 'LV' or custom_alignment = 'RV' or custom_alignment is null);
update redcap_metadata_temp set custom_alignment = if (custom_alignment = 'LV', 'LH', 'RH') 
	where element_type = 'slider' and (custom_alignment = 'LV' or custom_alignment = 'RV' or custom_alignment is null);