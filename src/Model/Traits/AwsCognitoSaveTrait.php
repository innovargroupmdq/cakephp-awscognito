<?php
namespace EvilCorp\AwsCognito\Model\Traits;
use Aws\Exception\AwsException;

trait AwsCognitoSaveTrait {

	/* Overrides saveMany behavior ensuring the changes are reverted if the save operation fails */
	public function saveMany($entities, $options = [])
    {
    	try {
        	$result = parent::saveMany($entities, $options);
    	} catch (AwsCognito $e) {
    		$result = false;
    	}

        if($result === false){
	        foreach ($entities as $entity) {
	            if($entity->isNew()){
	            	try {
	                	$this->deleteCognitoUser($entity);
	            	} catch (AwsCognito $e) {
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