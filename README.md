# laravel-crud-package
This bundle provides crud operations for laravel projects.

## Requirements
* PHP 7+
* Laravel 5+
* [Laravel Doctrine](https://packagist.org/packages/laravel-doctrine/orm)
* [Pagerfanta](https://packagist.org/packages/pagerfanta/pagerfanta)
* [Guzzle](https://packagist.org/packages/guzzlehttp/guzzle)
* [Laravel Form Bridge](https://packagist.org/packages/barryvdh/laravel-form-bridge)

## Package Installation
**To install package in laravel project, configure composer.json file**

```
"require": {
   "wolfmatrix/laravelcrud" : "dev-master"
},
"repositories": [
   {
       "type": "git",
       "url": "git@github.com:Wolfmatrix/laravel-crud-package.git",
       "options": {
           "symlink": true
       }
   }
],
"autoload": {
   "psr-4": {
       "Wolfmatrix\\LaravelCrud\\": "package/wolfmatrix/laravelcrud/src/"
   },
}
```
**After configuring composer.json file, run**
``` 
composer update 
```
**Configure config/app.php for package’s provider class**
```
'providers' => [
  Wolfmatrix\LaravelCrud\LaravelCrudServiceProvider::class,
]
```
### Doctrine Configuration
Add DoctrineServiceProvider class to the service provider list in **config/app.php**
```
'providers' => [
   LaravelDoctrine\ORM\DoctrineServiceProvider::class,
],
'aliases' => [
    'Doctrine' => LaravelDoctrine\ORM\Facades\Doctrine::class,
],
```
**publish the config file by running command**
```
php artisan vendor:publish --tag="config"
```
**configure the config/doctrine.php file**
```
'managers'                   => [
  'default' => [
      'dev'           => env('APP_DEBUG', false),
      'meta'          => env('DOCTRINE_METADATA', 'annotations'),
      'connection'    => env('DB_CONNECTION', 'mysql'),
      'namespaces'    => ['App\Entities'],
      'paths'         => [
          base_path('app/Entities')
      ],
   ]
],
```
## Package Usage
In order to perform crud operations, follow three steps
* Create Entity
* Create FormType
* Create routes

### Create Entity
 For example, create user entity User.php inside app/Entities and generate getters & setters.
 
 For search, sort and filter, create UserRepository.php at **app/Repository**. eg:
 ```
 <?php
namespace App\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Wolfmatrix\LaravelCrud\Entities\BaseEntity;

class UserRepository extends BaseEntity
{
    public $searchable = [
        'id'
    ];

    public $filterable = [
        'id'
    ];

    public $sortable = [
        'id'
    ];

    public $alias = [];
}
```
 
 ### Create FormType
 Create UserType.php form at **app/Forms**
 
 ### Create Routes
 Create routes for crud operations in **routes/api.php**. Before creating routes, 
 make namespace group of **app/Providers/RouteServiceProvider.php** to null or empty i,e.
 ```
 protected $namespace = '';
 ```
 Then, create routes eg:
 ```
 Route::post('users', 'Wolfmatrix\LaravelCrud\Controllers\BaseApiController@saveResource');
 Route::put('users/{user}', 'Wolfmatrix\LaravelCrud\Controllers\BaseApiController@saveResource');
 Route::get('users', 'Wolfmatrix\LaravelCrud\Controllers\BaseApiController@listResource');
 Route::get('users/{user}', 'Wolfmatrix\LaravelCrud\Controllers\BaseApiController@detailResource');
 Route::delete('users/{user}', 'Wolfmatrix\LaravelCrud\Controllers\BaseApiController@deleteResource');
 ```
 If you want to write route for the default controller, use leading slash '/' before starting url. i,e.
 ```
 Route::get('/users', 'App\Http\Controllers\ControllerName@ActionName');
 ```
