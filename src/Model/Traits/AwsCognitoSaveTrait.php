<?php
namespace EvilCorp\AwsCognito\Model\Traits;

trait AwsCognitoSaveTrait {

	/* Overrides saveMany behavior ensuring the changes are reverted if the save operation fails */
	public function saveMany($entities, $options = [])
    {
    	try {
        	$result = parent::saveMany($entities, $options);
    	} catch (Exception $e) {
    		pr($e); die();
    		$result = false;
    	}

        if($result === false){
	        foreach ($entities as $entity) {
	            if($entity->isNew()){
	            	try {
	                	$this->deleteCognitoUser($entity);
	            	} catch (Exception $e) {
	            		pr($e); die();
	            		continue;
	            	}
	            }else{
	                //TODO: if it was edited, revert
	            }
	        }
        }


     	return $result;
    }

}