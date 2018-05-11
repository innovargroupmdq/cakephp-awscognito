## AwsCognito plugin para CakePHP

Este plugin permite administrar los usuarios de AWS Cognito desde el panel de la aplicación, asegurándose de que queden sincronizados con su copia local.

Este plugin asume un canal de comunicación unidireccional con AWS Cognito en el que los cambios en campos sincronizados ocurren en la aplicación primero para luego ser propagados por el plugin en AWS Cognito.

----------

## Documentación

### Funcionalidades

**Implementadas:**

* Alta de usuarios. Se le envía un email de invitación al usuario con su contraseña temporal
* Baja de usuarios, asegurándose que también se remuevan de Cognito
* Modificaciones de los campos sincronizados se propagan en Cognito
* Deshabilitar/Habilitar usuarios por medio del checkbox de "activo"
* Blanqueo de contraseñas
* Reenvío de email de invitación (creación de cuenta)
* Autenticador de JSON Web Tokens (AwsCognitoJwtAuthenticate) para autenticar los usuarios de Cognito

**Pendientes:**

- configurable para usar teléfono, email o ambos para los usuarios (actualmente solo soporta email)
- métodos para los usuarios no administradores a través de una API (ver perfil, editar perfil, etc)


### Notas

- Si al crear un usuario, este es creado "desactivado" (`active = 0`), el plugin intentará deshabilitarlo en Cognito luego de crearlo (ya que no se puede crear un usuario deshabilitado en Cognito). Si esto falla, el plugin prioritizará la consistencia y el usuario será creado activado.

### Instalación

Para obtener el plugin con **composer** se requiere agregar a `composer.json` lo siguiente:

1. Al objeto `"require"`, agregar el plugin: `"evil-corp/cakephp-awscognito": "dev-master"`
2. Al arreglo de `"repositories"` agregar el objeto: ```{"type": "vcs", "url": "git@bitbucket.org:evil-corp/cakephp-awscognito.git"}```
3. correr `composer update`

NOTA: asegurarse de tener los permisos de acceso/deploy correctos en el repositorio.

Una vez instalado el plugin en el repositorio:

1. agregar a **bootstrap.php** la siguiente línea: `Plugin::load('EvilCorp/AwsCognito', ['bootstrap' => false, 'routes' => true]);` para cargar el plugin.
2. asegurarse de que exista la tabla **api_users**. El plugin viene con una migración para ésto, si es requerida: `bin/cake migrations migrate -p EvilCorp/AwsCognito`
3. Crear una User Pool en Cognito. Tener en cuenta:
	- La User Pool debe contener *solamente* los datos requeridos para el acceso. Los campos extras deberían solamente estar en la tabla local.
	- Asegurarse que los campos requeridos para la creación de usuarios en Cognito sea igual o más permisiva que la validación de la tabla local, de lo contrario podrían ocurrir errores al crear los usuarios que no se mostrarían correctamente al usuario.
	- No permitir a los usuarios cambiar su email o teléfono mediante/desde Cognito, esto podría causar una desincronización de datos. Con excepción de los cambios de contraseña, todos los cambios a los usuarios deberían ocurrir a través de la API prevista por el plugin.
4. Agregar los campos requeridos a la configuracion en `app.php` (ver sección "Variables de configuracion")
5. El controller de usuarios del plugin debería ser accesible desde `/aws-cognito/api-users` (para cambiar esto, ver sección "Extendiendo el plugin")


### Estructura

#### EvilCorp\AwsCognito\Model\Entity\ApiUser
**propiedades:**

- aws_cognito_id (char 36) (oculto)
- aws_cognito_username (varchar 255) (oculto)
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

**métodos públicos:**

- **getRoles()**: devuelve el arreglo de roles configurados. Arroja una excepción si la configuración no existe o es inválida.
- **resendInvitationEmail($entity)**: Reenvía el email de invitación en caso de que la cuenta haya expirado (y la refrezca), o en caso de que la dirección de email sea incorrecta (y permite cambiarla).
- **getCognitoUser($entity)**: Devuelve la información de AWS Cognito para un usuario local determinado.
- **resetCognitoPassword($entity)**: Blanquea la contraseña. Iniciará el proceso de cambio de contraseña la próxima vez que el usuario inicie sesión. La contraseña no puede ser blanqueada si el usuario nunca inició sesión, o si el email/teléfono no está verificado para enviar el mensaje de verificación.
- **deleteCognitoUser($entity)**: elimina el usuario de cognito. Usado automáticamente al eliminar un usuario de la tabla local. Útil para procesos en lotes.


#### EvilCorp\AwsCognito\Controller\ApiUsersController

**acciones para usuarios adminstradores:**

- /aws-cognito/api-users/index (GET)
- /aws-cognito/api-users/view/:id (GET)
- /aws-cognito/api-users/add (GET, POST)
- /aws-cognito/api-users/edit/:id (GET, POST)
- /aws-cognito/api-users/delete/:id (POST, DELETE)
- /aws-cognito/api-users/reset-password/:id (POST)
- /aws-cognito/api-users/resend-invitation-email/:id (GET, POST)

### Variables de configuracion

Las variables de configuración se guardan en el arreglo de configuración de la aplicación al igual que el resto de las configuraciones (`config/app.php` por defecto).

Las configuraciones disponibles son:

```php
'AwsCognito' => [
	'AccessKeys' => [
		'id'     => 'AKIAIBSMKNVHSJQL5E4Q', //requerido
		'secret' => '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' //requerido
	],
	'UserPool' => [
		'id' => 'us-east-1_rjoz1HOaR' //requerido
	],
	'IdentityProviderClient' => [
        'settings' => [], //https://docs.aws.amazon.com/sdkforruby/api/Aws/CognitoIdentityProvider/Client.html#initialize-instance_method
    ],
	'ApiUsers' => [
		'roles' => [ //requerido
            'agent'     => 'Agente',
            'dashboard' => 'Panel'
        ],
	]
],
```

### Testing

//TODO


### Extendiendo el plugin

#### Cambiando rutas

para cambiar las rutas por defecto del plugin (/aws-cognito/api-users), se puede agregar lo siguiente a `config/routes.php` dentro del scope base (`/`):

reemplazar rutas `/aws-cognito/api-users` por `/api-users`:

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


#### Sobreescribiendo vistas

para sobreescribir las templates sin tener que extender el controller, se deben colocar las nuevas templates en:

`src/Template/Plugin/EvilCorp/AwsCognito/ApiUsers/*`

por ejempla para reemplazar el template del index:

`src/Template/Plugin/EvilCorp/AwsCognito/ApiUsers/index.ctp`


#### Extendiendo el controller/model

Para extender el controller y model en caso de ser necesario (por ejemplo para agregar una nueva asociación al modelo ApiUsers), la mejor forma de hacer es la siguiente:

Primero, extender el ApiUsersTable:

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

        //agregar nuevas asociaciones aca
    }
}
```

Luego extender el ApiUsersController:

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
        $this->set('_serialize', ['ApiUsers']);
    }
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


----------

## Changelog


Fecha: xxx

* Se agrego la función tal
* Fix el error de xxx
* ...
