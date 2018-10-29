<?php
namespace EvilCorp\AwsCognito\Test\TestCase\Controller\Traits;
use EvilCorp\AwsCognito\Test\TestCase\Controller\Traits\BaseTraitTest;
use EvilCorp\AwsCognito\Controller\Traits\ImportApiUsersTrait;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable;
use Cake\Core\Configure;

class ImportApiUsersTraitTest extends BaseTraitTest
{
    public $viewVars;

    public function setUp()
    {
        $this->traitClassName = ImportApiUsersTrait::class;
        $this->traitMockMethods = ['dispatchEvent', 'isStopped', 'redirect', 'set', 'paginate', 'render'];
        parent::setUp();
        $viewVarsContainer = $this;
        $this->Trait->expects($this->any())
            ->method('set')
            ->will($this->returnCallback(function ($param1, $param2 = null) use ($viewVarsContainer) {
                if(is_array($param1)){
                    foreach ($param1 as $key => $value) {
                        $viewVarsContainer->viewVars[$key] = $value;
                    }
                }else{
                    $viewVarsContainer->viewVars[$param1] = $param2;
                }
            }));
    }

    public function tearDown()
    {
        $this->viewVars = null;
        parent::tearDown();
    }

    public function testImportValidateFailNoCsvData()
    {
        $this->_mockRequestPost(['post', 'put', 'patch']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('csv_data')
            ->will($this->returnValue(null));

        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('warning')
            ->with('Nothing to import.');

        $this->Trait->import();
    }

    public function testImportValidateFailMaxRowsAllowed()
    {
        $csv_data = [
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"robert.tito", "robert@tito.com", "Robert", "Tito", "user"',
        ];

        Configure::write('ApiUsers.import_max_rows', 2);

        $this->_mockRequestPost(['post', 'put', 'patch']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('csv_data')
            ->will($this->returnValue(implode("\n", $csv_data)));

        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('The max amount of rows allowed is 2. The data sent has 3 rows. Please review this and try again.');

        $this->Trait->import();
    }

    public function testImportValidateSuccessNoSave()
    {
        $csv_data = [
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"robert.tito", "robert@tito.com", "Robert", "Tito", "user"',
        ];
        $csv_data = implode("\n", $csv_data);
        $api_users = $this->ApiUsers->validateMany(
            $this->ApiUsers->csvDataToAssociativeArray($csv_data, [
                'aws_cognito_username', 'email', 'first_name', 'last_name', 'role'
            ])
        );

        $this->_mockRequestPost(['post', 'put', 'patch']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('csv_data')
            ->will($this->returnValue($csv_data));
        $this->Trait->request->expects($this->at(2))
            ->method('getData')
            ->with('max_errors')
            ->will($this->returnValue(null));
        $this->Trait->request->expects($this->at(3))
            ->method('getData')
            ->with('save_rows')
            ->will($this->returnValue(false));

        $this->Trait->expects($this->once())
            ->method('render')
            ->with('import_validated');

        $this->Trait->import();

        $expected = [
            'csv_data'              => $csv_data,
            'api_users'             => $api_users,
            'rows_count'            => 2,
            'analyzed_rows_count'   => 2,
            'stopped_at_max_errors' => false,
            'max_errors'            => false,
        ];

        $this->assertEquals($expected, $this->viewVars);
    }

    public function testImportValidateSuccessSaveSuccess()
    {
        $csv_data = [
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"robert.tito", "robert@tito.com", "Robert", "Tito", "user"',
        ];
        $csv_data   = implode("\n", $csv_data);
        $headers    = [];
        $parsed_csv = $this->ApiUsers->csvDataToAssociativeArray($csv_data, $headers);
        $api_users  = $this->ApiUsers->validateMany($parsed_csv);

        $this->_mockRequestPost(['post', 'put', 'patch']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('csv_data')
            ->will($this->returnValue($csv_data));
        $this->Trait->request->expects($this->at(2))
            ->method('getData')
            ->with('max_errors')
            ->will($this->returnValue(null));
        $this->Trait->request->expects($this->at(3))
            ->method('getData')
            ->with('save_rows')
            ->will($this->returnValue(true));

        $this->Trait->expects($this->never())
            ->method('render')
            ->with('import_validated');

        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'saveMany', 'validateMany', 'csvDataToAssociativeArray'
        ], ['alias' => 'ApiUsers']);

        $this->Trait->ApiUsers->expects($this->once())
            ->method('csvDataToAssociativeArray')
            ->with($csv_data, $headers)
            ->will($this->returnValue($parsed_csv));

        $this->Trait->ApiUsers->expects($this->once())
            ->method('validateMany', false)
            ->with($parsed_csv)
            ->will($this->returnValue($api_users));

        $this->Trait->ApiUsers->expects($this->once())
            ->method('saveMany')
            ->with($api_users, [
                'accessibleFields' => [
                    'first_name'           => true,
                    'last_name'            => true,
                    'aws_cognito_username' => true,
                    'email'                => true,
                ]
            ])
            ->will($this->returnValue($api_users));

        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('success')
            ->with('2 API Users imported successfully');

        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(['action' => 'index']);

        $this->Trait->import();

        $expected = [
            'csv_data'              => $csv_data,
            'api_users'             => $api_users,
            'rows_count'            => 2,
            'analyzed_rows_count'   => 2,
            'stopped_at_max_errors' => false,
            'max_errors'            => false,
        ];

        $this->assertEquals($expected, $this->viewVars);
    }

    public function testImportValidateSuccessSaveFail()
    {
        $csv_data = [
            '"pepe.pepez", "pepe@pepez.com", "Pepito", "Pepez", "user"',
            '"robert.tito", "robert@tito.com", "Robert", "Tito", "user"',
        ];
        $csv_data   = implode("\n", $csv_data);
        $headers    = [];
        $parsed_csv = $this->ApiUsers->csvDataToAssociativeArray($csv_data, $headers);
        $api_users  = $this->ApiUsers->validateMany($parsed_csv);

        $this->_mockRequestPost(['post', 'put', 'patch']);
        $this->Trait->request->expects($this->at(1))
            ->method('getData')
            ->with('csv_data')
            ->will($this->returnValue($csv_data));
        $this->Trait->request->expects($this->at(2))
            ->method('getData')
            ->with('max_errors')
            ->will($this->returnValue(null));
        $this->Trait->request->expects($this->at(3))
            ->method('getData')
            ->with('save_rows')
            ->will($this->returnValue(true));

        $this->Trait->expects($this->never())
            ->method('render')
            ->with('import_validated');

        $this->Trait->ApiUsers = $this->getMockForModel(ApiUsersTable::class, [
            'saveMany', 'validateMany', 'csvDataToAssociativeArray'
        ], ['alias' => 'ApiUsers']);

        $this->Trait->ApiUsers->expects($this->once())
            ->method('csvDataToAssociativeArray')
            ->with($csv_data, $headers)
            ->will($this->returnValue($parsed_csv));

        $this->Trait->ApiUsers->expects($this->once())
            ->method('validateMany', false)
            ->with($parsed_csv)
            ->will($this->returnValue($api_users));

        $this->Trait->ApiUsers->expects($this->once())
            ->method('saveMany')
            ->with($api_users, [
                'accessibleFields' => [
                    'first_name'           => true,
                    'last_name'            => true,
                    'aws_cognito_username' => true,
                    'email'                => true,
                ]
            ])
            ->will($this->returnValue(false));

        $this->_mockFlash();
        $this->Trait->Flash->expects($this->once())
            ->method('error')
            ->with('An error occurred while trying to import the API Users. Please try again.');

        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(['action' => 'index']);
        $this->Trait->import();
    }

}
