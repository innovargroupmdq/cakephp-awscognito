## AwsCognito plugin para CakePHP

Este plugin permite administrar los usuarios de AWS Cognito desde el panel de la aplicación, asegurándose de que queden sincronizados con su copia local.

Este plugin asume un canal de comunicación unidireccional con AWS Cognito en el que los cambios en campos sincronizados ocurren en la aplicación primero para luego ser propagados por el plugin en AWS Cognito.

----------

## Changelog

Fecha: 2018-10-11

* Se reescribió toda la documentación para reflejar los cambios recientes


----------

## Documentación

- [Funcionalidades](#markdown-header-funcionalidades)
- [Notas](#markdown-header-notas)
- [Instalación](#markdown-header-instalaci%C3%B3n)
- [Estructura](#markdown-header-estructura)
    - [EvilCorp\AwsCognito\Model\Entity\ApiUser](#markdown-header-evilcorpawscognitomodelentityapiuser)
    - [EvilCorp\AwsCognito\Model\Table\ApiUsersTable](#markdown-header-evilcorpawscognitomodeltableapiuserstable)
    - [EvilCorp\AwsCognito\Model\Traits\AwsCognitoSaveTrait](#markdown-header-evilcorpawscognitomodeltraitsawscognitosavetrait)
    - [EvilCorp\AwsCognito\Model\Behavior\AwsCognitoBehavior](#markdown-header-evilcorpawscognitomodelbehaviorawscognitobehavior)
    - [EvilCorp\AwsCognito\Model\Behavior\ImportApiUsersBehavior](#markdown-header-evilcorpawscognitomodelbehaviorimportapiusersbehavior)
    - [EvilCorp\AwsCognito\Controller\ApiUsersController](#markdown-header-evilcorpawscognitocontrollerapiuserscontroller)
    - [EvilCorp\AwsCognito\Controller\Api\ApiUsersController](#markdown-header-evilcorpawscognitocontrollerapiapiuserscontroller)
    - [EvilCorp\AwsCognito\Controller\Traits\BaseApiEndpointsTrait](#markdown-header-evilcorpawscognitocontrollertraitsbaseapiendpointstrait)
- [Variables de configuracion](#markdown-header-variables-de-configuracion)
- [Testing](#markdown-header-testing)
- [Extendiendo el plugin](#markdown-header-extendiendo-el-plugin)
    - [Cambiando rutas](#markdown-header-cambiando-rutas)
    - [Sobreescribiendo vistas](#markdown-header-sobreescribiendo-vistas)
    - [Extendiendo el controller/model](#markdown-header-extendiendo-el-controllermodel)

### Funcionalidades

**Implementadas:**

* Alta de usuarios. Se le envía un email de invitación al usuario con su contraseña temporal
* Baja de usuarios, asegurándose que también se remuevan de Cognito
* Deshabilitar/Habilitar usuarios por medio del checkbox de "activo" (impide login)
* Blanqueo de contraseñas
* Cambio de email, con la opción de elegir si se enviará un código de verificación
* Reenvío de email de invitación (creación de cuenta)
* Perfil de usuario indica si el usuario está correctamente sincronizado con Cognito
* Subir avatar del usuario a AWS S3
* Autenticador de JSON Web Tokens (AwsCognitoJwtAuthenticate) para autenticar los usuarios de Cognito
* API endpoints para los usuarios no administradores
* Importación de usuarios en bloque en formato CSV, con validación previa

**Pendientes:**

- Configuracion para usar teléfono, email o ambos para el registro/acceso de los usuarios (actualmente solo soporta email)
- La importación de usuarios aún no puede revertir ediciones si algo falla. Para evitar conflictos, usela solo para crear nuevos usuarios.
- Permitir al administrador cambiar el avatar del usuario

### Notas

- Si al crear un usuario, este es creado "desactivado" (`active = 0`), el plugin intentará deshabilitarlo en Cognito luego de crearlo (ya que no se puede crear un usuario deshabilitado en Cognito). Si esto falla, el plugin prioritizará la consistencia y el usuario será creado activado.

### Instalación

Para obtener el plugin con **composer** se requiere agregar a `composer.json` lo siguiente:

1. Al objeto `"require"`, agregar el plugin: `"evil-corp/cakephp-awscognito": "dev-master"`
2. Al arreglo de `"repositories"` agregar el objeto: ```{"type": "vcs", "url": "git@bitbucket.org:evil-corp/cakephp-awscognito.git"}```
3. correr `composer update`

NOTA: asegurarse de tener los permisos de acceso/deploy correctos en el repositorio.

Una vez instalado el plugin en el repositorio:

1. agregar a **config/bootstrap.php** la siguiente línea: `Plugin::load('EvilCorp/AwsCognito', ['bootstrap' => true, 'routes' => true]);` para cargar el plugin.
2. asegurarse de que exista la tabla **api_users**. El plugin viene con una migración para ésto, si es requerida: `bin/cake migrations migrate -p EvilCorp/AwsCognito`
3. Crear una User Pool en Cognito. Tener en cuenta:
    * La User Pool debe contener *solamente* los datos requeridos para el acceso. Los campos extras deberían solamente estar en la tabla local.
    * Asegurarse que la validación de los campos requeridos para la creación de usuarios en Cognito sea igual o más permisiva que la validación de la tabla local, de lo contrario podrían ocurrir errores al crear los usuarios que no se mostrarían correctamente al usuario.
    * No permitir a los usuarios cambiar su email o teléfono mediante/desde Cognito, esto podría causar una desincronización de datos. Con excepción de los cambios de contraseña, todos los cambios a los usuarios deberían ocurrir a través de la API prevista por el plugin.
4. agregar a **config/bootstrap.php** la siguiente línea, antes de cargar el plugin: `Configure::write('AwsCognito.config', ['awscognito']);`
5. Copie el archivo `vendor/evil-corp/cakephp-awscognito/config/awscognito.php` a `app/config/awscognito.php`.
6. Modifique el archivo para configurar el plugin. (ver sección "Variables de configuracion")
7. Las rutas por defecto son las siguientes (para cambiar esto, ver sección "Extendiendo el plugin". Para desactivar las rutas por defecto use `'routes' => false` en el `Plugin::load`)
    * El controller de usuarios del plugin debería ser accesible desde `/aws-cognito/api-users`
    * Los API endpoints son accesibles desde `/aws-cognito/api/api-users/:action`


### Estructura

#### EvilCorp\AwsCognito\Model\Entity\ApiUser
**propiedades:**

- aws_cognito_id (char 36) (oculto, inmutable)
- aws_cognito_username (varchar 255) (oculto, inmutable)
- email (varchar 255)
- active (tinyint 1)
- role (varchar 50)
- first_name (varchar 50)
- last_name (varchar 50)
- created (datetime)
- modified (datetime)

**propiedades virtuales:**

- full_name (`"${first_name} ${last_name}"`)

#### EvilCorp\AwsCognito\Model\Table\ApiUsersTable

- *protected* **$searchQueryFields**
    + Arreglo de campos usados en la configuracion de búsqueda de la Table.
    + Abstraído a una propiedad ṕrotegida para facilitar su extensión.
- *public* **initialize**()
    + Configura las asociaciones `Creators` con la Table `Users` (created_by) y `Modifiers` con la Table `Users` (modified_by). Para cambiar las tablas usadas debe extender la Table, ver sección "Extendiendo el plugin".
    + Agrega el Behavior `Timestamp` para llenar los campos `created_at` y `modified_at`
    + Agrega el Behavior `Muffin/Footprint.Footprint` para llenar los campos `created_by` y `modified_by`
    + Agrega el Behavior `EvilCorp/AwsCognito.AwsCognito` para habilitar la funcionalidad de Cognito
    + Agrega el Behavior `EvilCorp/AwsCognito.ImportApiUsers` para agregar la funcionalidad de importación en bloque
    + Agrega el Behavior `EvilCorp/AwsS3Upload.AwsS3Upload` para subir los avatar de usuario a AWS S3
    + Agrega el Behavior `Search.Search` para habilitar la búsqueda
- *public* **validationDefault**(Validator $validator)
    + Validacíón básica para campos base (id, first_name, last_name)
    + Asegura que el campo `role` esté presente y pertenezca a la lista configurada (Para cambiar esto debe extender la validación, ver sección "Extendiendo el plugin").
- *public* **getRoles**()
    + Devuelve el arreglo de roles configurados.
    + Arroja una excepción si la configuración no existe o es inválida.

#### EvilCorp\AwsCognito\Model\Traits\AwsCognitoSaveTrait

Este trait es usado por ApiUsersTable. Sus función es:

- Sobreescribir el método saveMany para capturar cualquier excepción de Cognito, o error de guardado, que suceda al intentar guardar muchos usuarios a la vez.
- En cuyo caso, se encarga de revertir los cambios que se hayan ocasionado en Cognito, borrando usuarios nuevos donde sea necesario.
- En el futuro debería también encargarse de revertir ediciones.

#### EvilCorp\AwsCognito\Model\Behavior\AwsCognitoBehavior

Este Behavior se encarga de habilitar toda la funcionalidad relacionada con Cognito. Sus métodos son los siguientes:

- *public* **validationResendInvitationEmail**(Validator $validator)
    + Validador incluído para la acción de reenviar email de invitación
- *public* **validationChangeEmail**(Validator $validator)
    + Validador incluído para la acción de cambio de email
- *public* **buildValidator**(Event $event, Validator $validator, $name)
    + Callback del validador
    + Se encarga de que los campos de congito estén requeridos y validados como sea correspondiente
- *public* **buildRules**(Event $event, RulesChecker $rules)
    + Agrega las reglas de la aplicación
    + Asegura que el nombre de usuario de cognito sea único
    + Asegura que el email sea único
    + Impide la edición del nombre de usuario cognito
    + Impide la edición del ID de cognito
    + Impide la edición del email por medios tradicionales (tiene una opción para que los métodos changeEmail y resendInvitationEmail puedan cambiarlo)
- *public* **beforeSave**(Event $event, EntityInterface $entity, ArrayObject $options)
    + Antes de guardar el usuario, este Callback realiza lo siguiente:
        * Si el usuario es nuevo, llama a `createCognitoUser`
        * Si el usuario se está editando y el campo `active` cambió llama a `disableCognitoUser` o a `enableCognitoUser` según corresponda
- *public* **beforeDelete**(Event $event, EntityInterface $entity, ArrayObject $options)
    + Antes de eliminar un usuario de la base de datos, este Callback llama a `deleteCognitoUser` para borrarlo en Cognito.
- *public* **changeEmail**(EntityInterface $entity, $new_email, bool $require_verification = true)
    + Éste método encapsula el cambio de email del usuario para poder llamarlo cómodamente desde el Controller.
    + Cambia el email en Cognito y en caso de éxito, guarda el cambio en la base de datos.
    + Permite elegir si el email estará verificado o no mediante el parámetro `$require_verification`.
    + En caso de requerir verificación, Cognito enviará un email al usuario con el Código de verificación. El desarrollador del cliente puede luego usar esto para verificar el email.
- *public* **resendInvitationEmail**(EntityInterface $entity, $new_email)
    + Reenvía el email de invitación en caso de que la cuenta haya expirado (y la refrezca), o en caso de que la dirección de email sea incorrecta (y permite cambiarla).
- *public* **getWithCognitoData**($id, $options = [])
    + Busca el usuario en la base de datos y le agrega la información de Cognito
    + Llama a Cognito para agregar los campos por lo que no es de alta performance.
    + Utilizado en el detalle del cliente en el Controller.
    + agrega los siguientes campos:
        * bool `aws_cognito_synced`: Indica si el usuario tiene todos los campos pertinentes correctamente sincronizados con Cognito. Normalmente nunca debería estar en falso.
        * array `aws_cognito_attributes`: Listado de atributos extras de Cognito
        * array `aws_cognito_status`: Contiene los campos que muestran el estado de Cognito, parseados para ser más legibles
            - `aws_cognito_status['code']`: El código del estado
            - `aws_cognito_status['title']`: El título del estado
            - `aws_cognito_status['description']`: La descripción del estado
    + En caso de no encontrar el usuario en Cognito, no arroja una excepción, si no que los campos están vacíos y `aws_cognito_synced` estará en falso.
- *public* **resetCognitoPassword**(EntityInterface $entity)
    + Blanquea la contraseña.
    + Iniciará el proceso de cambio de contraseña la próxima vez que el usuario inicie sesión.
    + La contraseña no puede ser blanqueada si el usuario nunca inició sesión, o si el email/teléfono no está verificado para enviar el mensaje de verificación.
- *public* **deleteCognitoUser**(EntityInterface $entity)
    + Elimina el usuario de cognito.
    + Usado automáticamente al eliminar un usuario de la tabla local.
    + El método es público por que es útil para procesos en lotes y transacciones.
- *protected* **createCognitoClient**()
    + Método internado usado para inicializar la conección con Cognito.
- *protected* **processCognitoUser**(Result $cognito_user)
    + Procesa las datos del usuario regresados por la SDK de Cognito y los convierte en datos más CakePHP-friendly.
- *protected* **titleForUserStatus**($status)
    + Devuelve el título del estado de Cognito del usuario
- *protected* **descriptionForUserStatus**($status)
    + Devuelve la descripción del estado de Cognito del usuario
- *protected* **createCognitoUser**(EntityInterface $entity, $message_action = null)
    + Usado para crear nuevos usuarios en Cognito, o para reenviar el email de invitación
- *protected* **awsExceptionToEntityErrors**(AwsException $exception, EntityInterface $entity)
    + Parsea ciertas excepciones de Cognito y las convierte en errores de validación para la entidad
    + Cubre casos como emails y usuarios invalidos o repetidos
    + Usado por otros métodos para hacer el proceso más CakePHP-friendly
- *protected* **disableCognitoUser**(EntityInterface $entity)
    + Deshabilita el usuario en Cognito, impidiendo su login
- *protected* **enableCognitoUser**(EntityInterface $entity)
    + Habilita el usuario en Cognito, rehabilitando su login

#### EvilCorp\AwsCognito\Model\Behavior\ImportApiUsersBehavior

Este Behavior se encarga de agregar la funcionalidad de importación de usuarios. Por ahora, esto incluye la habilidad de importar usuarios en bloque, en formato CSV. Sus métodos son:

- *public* **validateMany**(array $rows, $max_errors = false, array $options = []): array
    + `$rows` es un arreglo de usuarios y puede contener entidades de CakePHP ó arreglos con los datos del usuario, formateados para CakePHP
    + Éste método convierte todos los usuarios ingresados en el arreglo `$rows` a entidades de CakePHP
    + Usa el nombre de usuario para buscar las entidades preexistentes, parcheandolas con los cambios ingresados. Las demás serán creadas.
    + Valida todas las entidades tanto con la validación de entidad como con las reglas de la aplicación
    + Ademas las valida entre ellas buscando repetidas
    + Devuelve un arreglo de entidades con todas las validaciones aplicadas. Puede ver los errores llamando a `$entity->getErrors()`
- *public* **csvDataToAssociativeArray**(string $csv_data, array $fields = []): array
    + Convierte los datos en formato CSV ingresados (`$csv_data`) a un arreglo de usuarios formateado para CakePHP.
    + Los campos son esperados en un orden determinado
    + Por defecto espera los campos básicos (`aws_cognito_username`, `email`, `first_name`, `last_name`)
    + Para cambiar el orden o cantidad de los campos, utilice el parámetro $fields.

#### EvilCorp\AwsCognito\Controller\ApiUsersController

Utiliza los siguientes Traits:

- `BaseCrudTrait`
- `ImportApiUsersTrait`
- `AwsCognitoTrait`

#### EvilCorp\AwsCognito\Controller\Traits\BaseCrudTrait

Provee las siguientes acciones básicas para los usuarios administradores:

- /aws-cognito/api-users/index (GET)
    + Listado de usuarios
- /aws-cognito/api-users/view/:id (GET)
    + Detalle del usuario
    + Contiene los datos de Cognito
- /aws-cognito/api-users/add (GET, POST)
    + Agregar usuarios
- /aws-cognito/api-users/edit/:id (GET, POST)
    + Editar campos básicos del usuario (first_name, last_name, etc)
- /aws-cognito/api-users/change-email/:id (GET, POST)
    + Cambiar el email del usuario
    + Permite decidir si el email requiere verificación (en cuyo caso, se enviará un email de verificación)
- /aws-cognito/api-users/delete/:id (POST, DELETE)
    + Eliminar usuario

#### EvilCorp\AwsCognito\Controller\Traits\ImportApiUsersTrait

Provee las acciones para la importación de usuarios:

- /aws-cognito/api-users/import (GET, POST)
    + Permite importar un bloque de usuarios mediate un campo de texto en formato CSV

#### EvilCorp\AwsCognito\Controller\Traits\AwsCognitoTrait

Provee las acciones relevantes para la funcionalidad exclusiva de Cognito:

- /aws-cognito/api-users/activate/:id (POST)
    + Activar usuario (permite login)
- /aws-cognito/api-users/deactivate/:id (POST)
    + Desactivar usuario (impide login)
- /aws-cognito/api-users/reset-password/:id (POST)
    + Blanquea la contraseña del usuario
    + Iniciará el proceso de cambio de contraseña la próxima vez que el usuario inicie sesión.
    + La contraseña no puede ser blanqueada si el usuario nunca inició sesión, o si el email/teléfono no está verificado para enviar el mensaje de verificación.
- /aws-cognito/api-users/resend-invitation-email/:id (GET, POST)
    + Reenvía el email de invitación
    + Refresca el tiempo de expiración de la cuenta
    + Permite cambiar el email

#### EvilCorp\AwsCognito\Controller\Api\ApiUsersController

Utiliza el `BaseApiEndpointsTrait`.

#### EvilCorp\AwsCognito\Controller\Traits\BaseApiEndpointsTrait

Provee los siguientes endpoints para los desarrolladores de los clientes:

- /aws-cognito/api/api-users/profile (GET)
    + Detalle del perfil de usuario
- /aws-cognito/api/api-users/profile (POST)
    + Permite editar el perfil de usuario (solo los campos básicos, como first_name y last_name)
- /aws-cognito/api/api-users/change-email (POST)
    + Permite cambiar el email del usuario
    + Enviará un email con el código de verificación a la nueva dirección. El desarrollador deberá utilizarlo para comunicarse con cognito y verificar el email.
- /aws-cognito/api/api-users/upload-avatar (POST)
    + Permite cambiar el avatar de usuario
    + Espera que se envie el binario crudo de la imagen en el cuerpo de la petición HTTP
    + subirá el avatar a S3

### Variables de configuracion

Las variables de configuración se guardan en el arreglo de configuración de la aplicación al igual que el resto de las configuraciones (`config/app.php` por defecto).

Las configuraciones disponibles son:

```php
'AwsCognito' => [
    'AccessKeys' => [
        'id' => 'NSWPXE30F49XAOF',
        'secret' => 'QIQNxRO2425bb040e4adc8cc02fae05063c3c'
    ],
    'UserPool' => [
        'id' => 'us-east-2_rjaob1HaR',
    ],
    'IdentityProviderClient' => [
        'settings' => [], //https://docs.aws.amazon.com/sdkforruby/api/Aws/CognitoIdentityProvider/Client.html#initialize-instance_method
    ],
],
'ApiUsers' => [
    /* available user roles */
    'roles' => [
        'user' => __d('EvilCorp/AwsCognito', 'API User'),
    ],
    /* the max amount of errors alloweds before the validation process of the imported data is halted */
    'import_max_errors' => 10,

    /* the limit of accepted rows in the importing CSV data */
    'import_max_rows' => 500,
]
```

### Testing

Actualmente el plugin tiene aprox. un 90% de cobertura de lineas y un 70% de cobertura de métodos y funciones.

Para correr los tests, debe descargarse el repo por separado (para que composer registre el modo desarrollo). Correr composer para instalar las dependencias con `composer install`, y luego, correr los tests con `vendor/bin/phpunit`.

### Extendiendo el plugin

#### Cambiando rutas

para cambiar las rutas por defecto del plugin (/aws-cognito/api-users), se puede agregar lo siguiente a `config/routes.php`, debe primero settear `'routes' => false` al momento de cargar el plugin con `Plugin::load` en `bootstrap.php`.

Luego, puede utilizar lo siguiente dentro del scope base (`/`) en su `config/routes.php`.

* reemplazar rutas `/aws-cognito/api-users` por `/api-users`:
    ```php
    $routes->connect('/api-users/*', [
        'plugin' => 'EvilCorp/AwsCognito', 'controller' => 'ApiUsers'
    ]);
    $routes->connect('/api-users/:action', [
        'plugin' => 'EvilCorp/AwsCognito', 'controller' => 'ApiUsers', 'action' => ':action'
    ]);
    $routes->connect('/api-users/:action/:id', [
        'plugin' => 'EvilCorp/AwsCognito', 'controller' => 'ApiUsers', 'action' => ':action'
    ],  ['id' => '\d+', 'pass' => ['id']]);

    ```
* para la REST API, puede reemplazarlas de la siguiente manera:
    ```php
    Router::prefix('api', function ($routes) {
        $routes->extensions(['json']);

         /* Api Users */
         $routes->connect('/me', [
            'plugin'     => 'EvilCorp/AwsCognito',
            'prefix'     => 'Api',
            'controller' => 'ApiUsers',
            'action'     => 'profile',
            '_method'    => 'GET'
        ]);

        $routes->connect('/me', [
            'plugin'     => 'EvilCorp/AwsCognito',
            'prefix'     => 'Api',
            'controller' => 'ApiUsers',
            'action'     => 'editProfile',
            '_method'    => 'PATCH'
        ]);

        $routes->connect('/me/avatar', [
            'plugin'     => 'EvilCorp/AwsCognito',
            'prefix'     => 'Api',
            'controller' => 'ApiUsers',
            'action'     => 'uploadAvatar',
            '_method'    => 'PUT'
        ]);

        $routes->connect('/me/email', [
            'plugin'     => 'EvilCorp/AwsCognito',
            'prefix'     => 'Api',
            'controller' => 'ApiUsers',
            'action'     => 'changeEmail',
            '_method'    => 'PUT'
        ]);

    });
    ```

#### Sobreescribiendo vistas

para sobreescribir las templates sin tener que extender el controller, se deben colocar las nuevas templates en:

`src/Template/Plugin/EvilCorp/AwsCognito/ApiUsers/*`

por ejempla para reemplazar el template del index:

`src/Template/Plugin/EvilCorp/AwsCognito/ApiUsers/index.ctp`


#### Extendiendo el controller/model

Para extender el controller y model en caso de ser necesario (por ejemplo para agregar una nueva asociación al modelo ApiUsers), la mejor forma de hacer es la siguiente:

Primero, extender el **ApiUsersTable**:

```php
//archivo: src/Model/Table/ApiUsersTable.php
namespace App\Model\Table;

use EvilCorp\AwsCognito\Model\Entity\ApiUser;
use EvilCorp\AwsCognito\Model\Table\ApiUsersTable as AwsApiUsersTable;

class ApiUsersTable extends AwsApiUsersTable
{
    //esto solo es necesario si no se va a extender la Entidad
    protected $_entityClass = 'EvilCorp\AwsCognito\Model\Entity\ApiUser';

    public function initialize(array $config)
    {
        parent::initialize($config);

        //cambiando la tabla de usuarioa administradores
        $this->association('Creators')->className('AppUsers');
        $this->association('Modifiers')->className('AppUsers');

        //agregar nuevas asociaciones aca
         $this->belongsToMany('FunctionalUnits', [
            'through' => 'ApiUsersFunctionalUnits',
            'saveStrategy' =>'append',
        ]);

        //y podemos facilmente reconfigurar el search cambiando estos valores
        $this->searchQueryFields = [
            'ApiUsers.aws_cognito_username',
            'ApiUsers.email',
            'ApiUsers.phone',
            'ApiUsers.first_name',
            'ApiUsers.last_name',
        ];
    }

    //extendiendo la validación por defecto
    public function validationDefault(Validator $validator)
    {
        $validator = parent::validationDefault($validator);

        //se puede remover la validacion de un campo si no se usa
        $validator->remove('role');

        //y agregar campos nuevos
        $validator
            ->scalar('phone')
            ->allowEmpty('phone');

        return $validator;
    }


}
```

Luego extender el **ApiUsersController**:

```php
//archivo: src/Controller/ApiUsersController.php
namespace App\Controller;

use EvilCorp\AwsCognito\Controller\ApiUsersController as AwsApiUsersController;
use App\Model\Table\ApiUsersTable;

class ApiUsersController extends AwsApiUsersController
{
    //aca se pueden agregar nuevas acciones o reemplazar las existentes

    //por ejemplo el index:
    public function index()
    {
        $this->paginate['contain'] = ['PointsOfSale'];

        $this->set('api_users', $this->paginate('ApiUsers'));
        $this->set('_serialize', ['api_users']);
    }
}
```

Opcionalmente se puede extender la entidad **ApiUser**:

```php
//archivo: src/Model/Entity/ApiUser.php
namespace App\Model\Entity;

use EvilCorp\AwsCognito\Model\Entity\ApiUser as AwsApiUser;

class ApiUser extends AwsApiUser
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
        'role' => false,

        //cognito fields:
        'aws_cognito_username' => false,
        'aws_cognito_id' => false,
        'email' => false,

        //dejando lo demás como viene, podemos agregar nuevos campos
        'phone' => false
    ];

    //podemos agregar nuevas virtual properties aca
}
```

Ahora, para poder preservar las templates del plugin y solo tener que agregar las nuevas (o las que se quieran reemplazar), es posible modificar las rutas para que las nuevas acciones usen este controller y las demás, lleven al controller dentro del plugin:

```php
//el index lleva al nuevo controller
$routes->connect('/api-users', ['controller' => 'ApiUsers', 'action' => 'index']);
$routes->connect('/api-users/index', ['controller' => 'ApiUsers', 'action' => 'index']);

//las demas rutas llevan al controller del plugin
$routes->connect('/api-users/*', [
    'plugin' => 'EvilCorp/AwsCognito', 'controller' => 'ApiUsers'
]);
$routes->connect('/api-users/:action', [
    'plugin' => 'EvilCorp/AwsCognito', 'controller' => 'ApiUsers', 'action' => ':action'
]);
$routes->connect('/api-users/:action/:id', [
    'plugin' => 'EvilCorp/AwsCognito', 'controller' => 'ApiUsers', 'action' => ':action'
],  ['id' => '\d+', 'pass' => ['id']]);

```