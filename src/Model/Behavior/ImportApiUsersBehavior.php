<?php
namespace EvilCorp\AwsCognito\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;

class ImportApiUsersBehavior extends Behavior
{

	public function importMany(array $rows, array $options = [])
    {
        //this method creates/edits an array of entities/arrays

        $_options = [
            'accessibleFields' => [
                'first_name'           => true,
                'last_name'            => true,
                'aws_cognito_username' => true,
                'email'                => true,
            ]
        ];

        $opts = array_merge($_options, $options);

        $entities = [];

        foreach ($rows as $key => $row) {

            if(is_a($row, EntityInterface::class, true)){
                $entities[] = $row;
            }

            $entity = empty($row['id'])
                ? $this->getTable()->newEntity($row, $opts)
                : $this->getTable()->patchEntity(
                	$this->getTable()->get($row['id']), $row, $opts
                );

            $entities[] = $entity;
        }

        return $this->getTable()->saveMany($entities);
    }

    public function validateMany(array $rows, $max_errors = false, array $options = []): array
    {
        $_options = [
            'accessibleFields' => [
                'first_name'           => true,
                'last_name'            => true,
                'aws_cognito_username' => true,
                'email'                => true,
            ]
        ];

        $opts = array_merge($_options, $options);

        $entities = [];

        $duplicated_check = [
            'email'                => Hash::extract($rows, '{n}.email'),
            'aws_cognito_username' => Hash::extract($rows, '{n}.aws_cognito_username'),
        ];

        $errors_count = 0;

        foreach ($rows as $key => $row) {
            if($max_errors && $errors_count >= $max_errors) break;

            $find_conditions = [
                'aws_cognito_username' => $row['aws_cognito_username']
            ];

            if(is_a($row, EntityInterface::class, true)){
                $entity = $row;
            }else{
                $is_new = !$this->getTable()->exists($find_conditions);
                $entity = $is_new
                    ? $this->getTable()->newEntity($row, $opts)
                    : $this->getTable()->patchEntity(
                        $this->getTable()->find()->where($find_conditions)->firstOrFail(),
                        $row, $opts);
            }

            //check rules in db
            $this->getTable()->checkRules($entity);

            //check that the unique fields are also unique within the file
            foreach (['email', 'aws_cognito_username'] as $field) {
                $duplicated_key = array_search($row[$field], $duplicated_check[$field]);
                if($duplicated_key && $duplicated_key !== $key){
                    $entity->setError($field, __d('EvilCorp/AwsCognito', 'This field is duplicated'));
                }
            }

            if($entity->getErrors()) $errors_count++;
            $entities[] = $entity;
        }

        return $entities;
    }

    public function csvDataToAssociativeArray(string $csv_data, array $fields = []): array
    {
        $fields = !empty($fields) ? $fields : [
            'aws_cognito_username',
            'email',
            'first_name',
            'last_name',
        ];

        $rows = explode("\n", $csv_data);
        $parsed_rows = array_map('str_getcsv', $rows);

        array_walk($parsed_rows, function(&$row) use ($fields) {
            $filled_row  = $row + array_fill(0, count($fields), null);
            $cropped_row = array_slice($filled_row, 0, count($fields));
            $row         = array_combine($fields, $cropped_row);
        });

        return $parsed_rows;
    }
}