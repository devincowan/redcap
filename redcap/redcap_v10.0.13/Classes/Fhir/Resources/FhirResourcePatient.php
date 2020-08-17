<?php

namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * class for a FHIR resource boudle
 */
class FhirResourcePatient extends FhirResource
{
    /**
     * name of the endpoint
     */
    const RESOURCE_NAME = 'Patient';

    /**
     * get the usual or official name object
     *
     * @param string $type
     * @return object|false
     */
    private function getDefaultName()
    {
        $names = $this->name;
        $reg_exp = "/usual|official/i";
        foreach ($names as $name) {
            // return usual or official name if found
            $use = $name->use ?: '';
            if(preg_match($reg_exp, $use)) return $name;
        }
        // return the first found name otherwise
        return reset($name);
    }

    /**
     * get given name
     *
     * @return string
     */
    public function getNameGiven()
    {
        $name = $this->getDefaultName();
        if(!$name) return '';
        $given = $name->given ?: array();
        if(is_array($given)) return implode(' ', $given);
        return $name->given ?: '';
    }

    /**
     * get family name
     *
     * @return string
     */
    public function getNameFamily()
    {
        $name = $this->getDefaultName();
        if(!$name) return '';
        $family = $name->family ?: array();
        if(is_array($family)) return implode(' ', $family);
        return $name->family ?: '';
    }

    /**
     * get birthdate
     *
     * @return string
     */
    public function getBirthDate()
    {
        return $this->birthDate;
    }

    /**
     * get all codes from an extension object
     *
     * @param object $extension
     * @return array
     */
    private function getExtensionCode($root_extension)
    {
        // look for the code
        $codes = array();
        // could be a valueCode
        if($root_extension->valueCode) $codes[] = $root_extension->valueCode;
        // could be a codeable concept
        if($valueCodeableConcept = $root_extension->valueCodeableConcept) {
            if($codings = $valueCodeableConcept->coding) {
                foreach ($codings as $coding) {
                    $codes[] = $coding->code;
                }
            }
        }
        // or multiple extensions with value coding
        if($extensions = $root_extension->extension) {
            foreach ($extensions as $extension) {
                // we are looking for valueCoding or valueCodeableConcept
                if($valueCoding = $extension->valueCoding) {
                    if($valueCoding->code) $codes[] = $valueCoding->code;
                }
            }
        }
        return $codes;
    }

    /**
     * get all text values for an extension
     *
     * @param object $root_extension
     * @return array
     */
    private function getExtensionText($root_extension)
    {
        $values = array();
        if($valueCodeableConcept = $root_extension->valueCodeableConcept) {
            $codings = $valueCodeableConcept->coding ?: array();
            foreach ($codings as $coding) {
                if($coding->display) $values[] = $coding->display;
            }
            // if($valueCodeableConcept->text) $values = $valueCodeableConcept->text;
        }
        // or multiple extensions with value coding
        if($extensions = $root_extension->extension) {
            foreach ($extensions as $extension) {
                // we are looking for valueCoding or valueCodeableConcept
                if($valueCoding = $extension->valueCoding) {
                    if($valueCoding->display) $values[] = $valueCoding->display;
                }
                if($extension->valueString) $values[] = $extension->valueString;
            }
        }
        return $values;
    }

    /**
     * find an extension (race, ethnicity or gender)
     * using a regular expression
     * @param string $reg_exp
     * @return object|false the extension object if found
     */
    private function findExtension($reg_exp)
    {
        $extensions = $this->extension;
        $filtered_extensions = array_filter($extensions, function($extension) use($reg_exp) {
            $url = $extension->url;
            return preg_match($reg_exp, $url);
        });
        return reset($filtered_extensions);
    }

    /**
     * get the race from the extension
     *
     * @param boolean $code
     * @return string
     */
    public function getRace($code=true)
    {
        $reg_exp = "/-race\$/";
        $extension = $this->findExtension($reg_exp);
        if(!$extension) return ''; // exit if no race extension
        if($code) {
            // look for the code
            $codes = $this->getExtensionCode($extension);
            // return the first code found or an empty string
            return reset($codes) ?: '';
        }
        $text_values = $this->getExtensionText($extension);
        return reset($text_values) ?: '';
    }

    /**
     * get the gender (code or text)
     * gender could be in extension or in the gender attribute
     *
     * @param boolean $code
     * @return string
     */
    public function getGender($code=true)
    {
        $reg_exp = "/-birth-?sex\$/";
        $extension = $this->findExtension($reg_exp);
        if($code) {
            if(!$extension) {
                // in rare cases no extension is available so we use a fallback (mostly Cerner)
                $gender_mapping = array(
                    'F' => 'female',
                    'M' => 'male',
                    'UNK' => 'unknown',
                );
                $gender = $this->gender; // get the gender as text
                $gender_reg_exp = "/^$gender\$/i";
                foreach ($gender_mapping as $key => $text) {
                    if(preg_match($gender_reg_exp, $text)) return $key;
                }
                return 'UNK'; //if no match is found return UNK by default
            }
            // look for the code
            $codes = $this->getExtensionCode($extension);
            // return the first code found or an empty string
            return reset($codes) ?: '';
        }
        if($extension) {
            $text_values = $this->getExtensionText($extension);
            // return the gender found in extension if available
            if($gender = reset($text_values)) return $gender;
        }
        return $this->gender;
    }

    /**
     * get the ethnicity from the extension
     *
     * @param boolean $code
     * @return string
     */
    public function getEthnicity($code=true)
    {
        $reg_exp = "/-ethnicity\$/";
        $extension = $this->findExtension($reg_exp);
        if(!$extension) return ''; // exit if no race extension
        if($code) {
            // look for the code
            $codes = $this->getExtensionCode($extension);
            // return the first code found or an empty string
            return reset($codes) ?: '';
        }
        $text_values = $this->getExtensionText($extension);
        return reset($text_values) ?: '';
    }

    public function isDeceased()
    {
        $deceasedDateTime = isset($this->deceasedDateTime) ? true : false;
        return $deceasedDateTime || $this->deceasedBoolean;
    }

    /**
     * get the address marked as home
     * or the first one of the list
     *
     * @return object|false
     */
    private function getDefaultAddress()
    {
        $address_list = $this->address ?: array();
        foreach ($address_list as $address) {
            // return the home address if found
            if(isset($address->use) && preg_match("/home/i", $address->use) )
                return $address;
        }
        // return the first address if no home address is available
        return reset($address_list);

    }

    public function getAddressLine()
    {
        $address = $this->getDefaultAddress();
        if(!$address) return '';
        if(is_array($address->line)) return implode(" ", $address->line);
        return $address->line ?: '';
    }

    public function getAddressCity()
    {
        $address = $this->getDefaultAddress();
        return $address->city;
    }

    public function getAddressState()
    {
        $address = $this->getDefaultAddress();
        return $address->state;
    }

    public function getAddressPostalCode()
    {
        $address = $this->getDefaultAddress();
        return $address->postalCode;
    }

    public function getAddressCountry()
    {
        $address = $this->getDefaultAddress();
        return $address->country;
    }


    /**
     * return a list of telecoms matching a system
     *
     * @param string $reg_exp
     * @return array
     */
    private function getTelecomSystem($reg_exp)
    {
        $telecoms = $this->telecom;
        $list = array();
        foreach ($telecoms as $telecom) {
            if(empty($telecom->system)) continue;
            $system = $telecom->system;
            if(preg_match($reg_exp, $system)) $list[] = $telecom;
        }
        return $list;
    }

    public function getEmail()
    {
        $telecoms = $this->getTelecomSystem('/email/');
        $emails = array();
        foreach ($telecoms as $telecom) {
            if($telecom->value) $emails[] = $telecom->value;
        }
        return implode('; ', $emails);
    }

    public function getPhoneHome()
    {
        $telecoms = $this->getTelecomSystem('/phone/');
        $values = array();
        foreach ($telecoms as $telecom) {
            if(!preg_match("/home/i", $telecom->use)) continue;
            if($telecom->value) $values[] = $telecom->value;
        }
        return implode(', ', $values);
    }

    public function getPhoneMobile()
    {
        $telecoms = $this->getTelecomSystem('/phone/');
        $values = array();
        foreach ($telecoms as $telecom) {
            if(!preg_match("/mobile/i", $telecom->use)) continue;
            if($telecom->value) $values[] = $telecom->value;
        }
        return implode(', ', $values);
    }

    /**
     * get the preferred language of a patient.
     * return the preferred language or the first language found.
     * the language found in "coding" has precedence against the one in "text"
     * only the first coding is considered
     *
     * @return string
     */
    public function getPreferredLanguage()
    {
        $communication = $this->communication;
        if(empty($communication)) return '';

        $languages = array(); // collect found languages as language => preferred
        foreach($communication as $concept) {
            $language_concept = $concept->language; // reference to the language concept
            $preferred = $concept->preferred ?: false;
            $language = $text = $language_concept->text;
            $first_coding = reset($language_concept->coding);
            if($first_coding) $language = $first_coding->display;
            if(empty($language)) continue; //skip empty language
            if($preferred) return $language; // return the preferred language if found
            // collect all found languages so we can return the first if no preferred is found
            $languages[$language ] = $preferred;
        }
        $first_language =  reset(array_keys($languages));
        if(empty($first_language)) return '';
        return $first_language;
    }

    /**
     * get all data available for this resource
     *
     * @return array
     */
    public function getData()
    {
        $data = array(
            'first_name' => $this->getNameGiven(),
            'last_name' => $this->getNameFamily(),
            'gender' => $this->getGender(false),
            'gender_code' => $this->getGender(),
            'ethnicity' => $this->getEthnicity(false),
            'ethnicity_code' => $this->getEthnicity(),
            'race' => $this->getRace(false),
            'race_code' => $this->getRace(),
            'birthdate' => $this->getBirthDate(),
            'address_city' => $this->getAddressCity(),
            'address_country' => $this->getAddressCountry(),
            'address_postal_code' => $this->getAddressPostalCode(),
            'address_state' => $this->getAddressState(),
            'address_line' => $this->getAddressLine(),
            'phone_home' => $this->getPhoneHome(),
            'phone_mobile' => $this->getPhoneMobile(),
            'email' => $this->getEmail(),
            'is_deceased' => $this->isDeceased(),
            'preferred_language' => $this->getPreferredLanguage(),
        );
        return $data;
    }
}