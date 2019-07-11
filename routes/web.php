<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// API Group
$router->group(['prefix' => 'api', 'middleware' => 'cors'], function() use($router){

    // Master Group
    $router->group(['prefix'=>'mst'], function() use($router){
        // master status group
        $router->group(['prefix'=>'status'], function() use($router){
            $router->get('/','Master\StatusController@index');
        });

        // master city group
        $router->group(['prefix'=>'city'], function() use($router){
            $router->get('/','Master\CityController@index');
        });

        // master measure group
        $router->group(['prefix'=>'measure'], function() use($router){
            $router->get('/','Master\MeasureController@index');
            $router->get('find/{id}', 'Master\MeasureController@find');
            $router->post('grid','Master\MeasureController@grid');
            $router->post('add', 'Master\MeasureController@add');
            $router->post('edit', 'Master\MeasureController@edit');
            $router->post('delete', 'Master\MeasureController@delete');
        });

        // master Stock group
        $router->group(['prefix'=>'stock'], function() use($router){
            $router->get('/','Master\StockController@index');
            $router->get('brand','Master\StockController@brand');
            $router->get('find/{id}', 'Master\StockController@find');
            $router->post('autocomplete', 'Master\StockController@autocomplete');
            $router->post('grid','Master\StockController@grid');
            $router->post('add', 'Master\StockController@add');
            $router->post('edit', 'Master\StockController@edit');
            $router->post('delete', 'Master\StockController@delete');
        });

        // master cabinet group
        $router->group(['prefix'=>'cabinet'], function() use($router){
            $router->get('/','Master\CabinetController@index');
            $router->get('get','Master\CabinetController@get');
            $router->get('get/{ty}','Master\CabinetController@get');
            $router->post('add','Master\CabinetController@add');
            $router->post('delete','Master\CabinetController@delete');
        });

        // get Supplier data
        $router->group(['prefix' => 'supplier'], function() use($router){
            $router->get('/', 'Master\SupplierController@index');
            $router->get('find/{id}', 'Master\SupplierController@find');
            $router->post('grid', 'Master\SupplierController@grid');
            $router->post('add', 'Master\SupplierController@add');
            $router->post('edit', 'Master\SupplierController@edit');
            $router->post('delete', 'Master\SupplierController@delete');
        });
    });

    // Account Group
    $router->group(['prefix' => 'auth'], function() use($router){
        // Index of API
        $router->get('/', 'Account\AuthController@index');
        $router->post('login', 'Account\AuthController@login');
        $router->post('menu', 'Account\AuthController@menu');
    });






    // Warehouse Group
    $router->group(['prefix' => 'wh'], function() use($router){
        // Index of API
        $router->get('main', 'Warehouse\MainController@index');

        // get Supplier data
        $router->group(['prefix' => 'supplier'], function() use($router){

            $router->get('/', 'Master\SupplierController@index');
            $router->post('/grid', 'Master\SupplierController@grid');

        });

        // request group
        $router->group(['prefix' => 'req'], function() use($router){
            // request tools group
            $router->group(['prefix' => 'tools'], function() use($router){
                // add tools
                $router->get('find/{id}','Document\RequestController@find_tools');
                $router->post('add','Document\RequestController@add_tools');
                $router->post('delete','Document\RequestController@delete_tools');
                $router->post('grid','Document\RequestController@grid_tools');
                $router->post('send','Document\RequestController@send_tools');
            });
        });

        // Warehouse Stock group
        $router->group(['prefix' => 'stock'], function() use($router){
            // add stock 
            $router->get('find/{id}', 'Warehouse\StockController@find');
            $router->post('add', 'Warehouse\StockController@add');
            $router->post('edit', 'Warehouse\StockController@edit');
            $router->post('autocomplete', 'Warehouse\StockController@autocomplete');
            $router->post('grid','Warehouse\StockController@grid');
            $router->post('history','Warehouse\StockController@history');

            // warehouse stock cabinet 
            $router->group(['prefix' => 'cabinet'], function() use($router){
                $router->post('add', 'Warehouse\CabinetController@add');
                $router->post('delete', 'Warehouse\CabinetController@delete');
                $router->post('grid', 'Warehouse\CabinetController@grid');
            });

        }); 
    });

});