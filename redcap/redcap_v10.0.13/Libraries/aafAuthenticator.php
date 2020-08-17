<?php

class aafAuthenticator{

	public function getAAFMap($aafIn,$aafSecret,$iss,$aud){
		$retObj = new StdClass;

                if(isset($aafIn) && strlen($aafIn)>0){
                        $jsonAAFArr=$this->getLegitAAFArr($aafIn,$aafSecret,$iss,$aud);
                        if(isset($jsonAAFArr)){
                                if(isset($jsonAAFArr['https://aaf.edu.au/attributes'])){
                                        $fields=array("edupersontargetedid","edupersonprincipalname","cn","displayname","surname","givenname","mail","edupersonscopedaffiliation");
                               		foreach($fields as $value){
						if($this->isStrCool($value,"cn") && (!isset($jsonAAFArr['https://aaf.edu.au/attributes']['surname']) && !isset($jsonAAFArr['https://aaf.edu.au/attributes']['givenname']))){
							if(isset($jsonAAFArr['https://aaf.edu.au/attributes'][$value]) ){
								$cnArr=explode(" ",$jsonAAFArr['https://aaf.edu.au/attributes'][$value]);
								$retObj->givenname=$cnArr[0];
								$retObj->surname=$cnArr[count($cnArr)-1];
							}
						}elseif($this->isStrCool($value,"displayname") && (!isset($jsonAAFArr['https://aaf.edu.au/attributes']['cn']) && !isset($jsonAAFArr['https://aaf.edu.au/attributes']['surname']) && !isset($jsonAAFArr['https://aaf.edu.au/attributes']['givenname']))){
							if(isset($jsonAAFArr['https://aaf.edu.au/attributes'][$value]) ){
								$cnArr=explode(" ",$jsonAAFArr['https://aaf.edu.au/attributes'][$value]);
                                                                $retObj->givenname=$cnArr[0];
                                                                $retObj->surname=$cnArr[count($cnArr)-1];
							}
						}else{
							if(isset($jsonAAFArr['https://aaf.edu.au/attributes'][$value]) && strlen($jsonAAFArr['https://aaf.edu.au/attributes'][$value])>0){
								$retObj->$value=$jsonAAFArr['https://aaf.edu.au/attributes'][$value];
							}	
						}
					}         
                                }else{
					exit('No user attributes were supplied from AAF.');
				}
                        }else{
				exit('AAF information could not be gotten from input.');
			}
                }else{
			exit('AAF input was null or zero length.');
		}       
		return $retObj;
        }


	private function getLegitAAFArr($aafIn,$key,$iss,$aud){
		require_once APP_PATH_LIBRARIES . "JWT.php";
		$JWT = new JWT;
		$jsonStr = $JWT->decode($aafIn, $key);
		$retArr=array();

		error_log($jsonStr);
		if(strlen($jsonStr)>0 and $jsonStr!='Invalid Signature' and $jsonStr!='RSA and ECDSA not implemented yet!' ){
			$jsonAAFArr=json_decode($jsonStr,true);
			if(isset($jsonAAFArr) and $this->isAAFJsonCool($jsonAAFArr,$iss,$aud)){			
				$retArr=$jsonAAFArr;	
			}
		}		
		return $retArr;
	}

	private function isAAFJsonCool($jsonAAFArr,$iss,$aud){
	        $result=false;
		
        	if(isset($jsonAAFArr['iss']) and $this->isStrCool($jsonAAFArr['iss'],$iss) and isset($jsonAAFArr['aud']) and $this->isStrCool($jsonAAFArr['aud'],$aud) and isset($jsonAAFArr['nbf']) and $this->isbeforeNow($jsonAAFArr['nbf']) and isset($jsonAAFArr['exp']) and $this->isAfterNow($jsonAAFArr['exp'])){
                	$result=true;
        	}
        	return $result;
	}

	private function isStrCool($aafStr,$target){
        	$result=true;

        	if(strcasecmp($aafStr,$target)!=0){
                	$result=false;
        	}
        	return $result;
	}

	private function isbeforeNow($inTime){
        	$result=true;

        	if($inTime>=time()){
                	$result=false;
        	}
        	return $result;
	}

	private function isAfterNow($inTime){
        	$result=true;

        	if($inTime<=time()){
                	$result=false;
        	}
        	return $result;
	}

}