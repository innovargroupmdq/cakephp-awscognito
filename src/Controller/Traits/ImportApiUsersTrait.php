<?php
namespace EvilCorp\AwsCognito\Controller\Traits;
use Cake\Core\Configure;

trait ImportApiUsersTrait
{

	public function import()
    {
        if($this->request->is(['post', 'put', 'patch'])){
            $this->_import();
        }
    }

    protected function _import($headers = [])
    {
        $csv_data = $this->request->getData('csv_data', null);

        if(empty($csv_data)){
            $this->Flash->warning(__d('EvilCorp/AwsCognito', 'Nothing to import.'));
            return;
        }

        //check for max rows
        $max_rows  = Configure::read('ApiUsers.import_max_rows');
        $data_rows = count(explode("\n", $csv_data));
        if($data_rows > $max_rows){
            $this->Flash->error(__d('EvilCorp/AwsCognito',
                'The max amount of rows allowed is {0}. The data sent has {1} rows. Please review this and try again.',
                $max_rows, $data_rows)
            );
            return;
        }

        //set max errors
        $max_errors = $this->request->getData('max_errors')
            ? Configure::read('ApiUsers.import_max_errors')
            : false;

        //validate data
        $rows = $this->ApiUsers->csvDataToAssociativeArray($csv_data, $headers);
        $api_users = $this->ApiUsers->validateMany($rows, $max_errors);

        $this->set([
            'csv_data'              => $csv_data,
            'api_users'             => $api_users,
            'rows_count'            => count($rows),
            'analyzed_rows_count'   => count($api_users),
            'stopped_at_max_errors' => ($max_errors && count($api_users) < count($rows)),
            'max_errors'            => $max_errors,
        ]);

        if(!$this->request->getData('save_rows')){
            $this->render('import_validated');
            return;
        }

        //save rows
        $validated_api_users = array_filter($api_users, function($u){
            return !$u->getErrors();
        });

        $result = $this->ApiUsers->saveMany($validated_api_users, [
            'accessibleFields' => [
                'first_name'           => true,
                'last_name'            => true,
                'aws_cognito_username' => true,
                'email'                => true,
            ]
        ]);

        if($result){
            $this->Flash->success(__d('EvilCorp/AwsCognito', '{0} API Users imported successfully', count($result)));
        }else{
            $this->Flash->error(__d('EvilCorp/AwsCognito', 'An error occurred while trying to import the API Users. Please try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }

}