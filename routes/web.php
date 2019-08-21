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

        // master Company group
        $router->group(['prefix'=>'company'], function() use($router){
            $router->get('/','Master\CompanyController@index');
        });

        // master Department group
        $router->group(['prefix'=>'department'], function() use($router){
            $router->post('/','Master\DepartmentController@index');
        });

        // master Division group
        $router->group(['prefix'=>'division'], function() use($router){
            $router->post('/','Master\DivisionController@index');
        });

        // master page group
        $router->group(['prefix'=>'page'], function() use($router){
            $router->get('/','Master\PageController@index');
        });

        // master menu group
        $router->group(['prefix'=>'menu'], function() use($router){
            $router->get('/','Master\MenuController@index');
            $router->post('parent','Master\MenuController@parent');
            $router->post('add','Master\MenuController@add');
            $router->post('delete','Master\MenuController@delete');
            $router->post('grid','Master\MenuController@grid');
        });

        // master icon group
        $router->group(['prefix'=>'icon'], function() use($router){
            $router->get('/','Master\IconController@index');
            $router->post('grid','Master\IconController@grid');
            $router->post('add','Master\IconController@add');
            $router->post('delete','Master\IconController@delete');
        });

        // master measure group
        $router->group(['prefix'=>'measure'], function() use($router){
            $router->get('/','Master\MeasureController@index');
            $router->get('find/{id}', 'Master\MeasureController@find');
            $router->post('get','Master\MeasureController@get');
            $router->post('grid','Master\MeasureController@grid');
            $router->post('add', 'Master\MeasureController@add');
            $router->post('edit', 'Master\MeasureController@edit');
            $router->post('delete', 'Master\MeasureController@delete');
        });

        // master category group
        $router->group(['prefix'=>'category'], function() use($router){
            $router->get('/','Master\CategoryController@index');
            $router->get('find/{id}', 'Master\CategoryController@find');
            $router->post('get','Master\CategoryController@get');
            $router->post('grid','Master\CategoryController@grid');
            $router->post('add', 'Master\CategoryController@add');
            $router->post('edit', 'Master\CategoryController@edit');
            $router->post('delete', 'Master\CategoryController@delete');
        });

        // master Stock group
        $router->group(['prefix'=>'stock'], function() use($router){
            $router->get('/','Master\StockController@index');
            $router->get('brand','Master\StockController@brand');
            $router->get('find/{id}', 'Master\StockController@find');
            $router->post('autocomplete', 'Master\StockController@autocomplete');
            $router->post('get','Master\StockController@get');
            $router->post('grid','Master\StockController@grid');
            $router->post('add', 'Master\StockController@add');
            $router->post('edit', 'Master\StockController@edit');
            $router->post('delete', 'Master\StockController@delete');
        });

        // master cabinet group
        $router->group(['prefix'=>'cabinet'], function() use($router){
            $router->get('/','Master\CabinetController@index');
            $router->get('get','Master\CabinetController@get');
            $router->get('get/{p}','Master\CabinetController@get');
            $router->post('add','Master\CabinetController@add');
            $router->post('delete','Master\CabinetController@delete');
            $router->get('tree','Master\CabinetController@tree');
            $router->get('tree_child','Master\CabinetController@tree_child');
        });

        // get Supplier data
        $router->group(['prefix' => 'supplier'], function() use($router){
            $router->get('/', 'Master\SupplierController@index');
            $router->get('find/{id}', 'Master\SupplierController@find');
            $router->post('get', 'Master\SupplierController@get');
            $router->post('grid', 'Master\SupplierController@grid');
            $router->post('add', 'Master\SupplierController@add');
            $router->post('edit', 'Master\SupplierController@edit');
            $router->post('delete', 'Master\SupplierController@delete');
            $router->post('autocomplete', 'Master\SupplierController@autocomplete');
        });
    });

    // Auth Group
    $router->group(['prefix' => 'auth'], function() use($router){
        // Index of API
        $router->get('/', 'Account\AuthController@index');
        $router->post('login', 'Account\AuthController@login');
        $router->post('menu', 'Account\AuthController@menu');
    });

    // Account Group
    $router->group(['prefix' => 'account'], function() use($router){
        // for user account
        $router->group(['prefix' => 'user'], function() use($router){
            $router->get('find/{id}', 'Account\UserController@find');
            // check group
            $router->group(['prefix' => 'check'], function() use($router){
              $router->post('/', 'Account\UserController@check');
              $router->post('group', 'Account\UserController@check_group');
            });
            $router->post('grid', 'Account\UserController@grid');
            $router->post('add', 'Account\UserController@add');
            // edit user group
            $router->group(['prefix' => 'edit'], function() use($router){
              $router->post('/', 'Account\UserController@edit');
              $router->post('field', 'Account\UserController@edit_field');
            });
            $router->post('delete', 'Account\UserController@delete');
            $router->post('autocomplete', 'Account\UserController@autocomplete');

            // biodata group
            $router->group(['prefix' => 'biodata'], function() use($router){
              $router->post('/', 'Account\UserController@biodata');
            });

            // change password
            $router->post('change_password', 'Account\UserController@change_password');
        });
    });



    // Management Group
    $router->group(['prefix' => 'mng'], function() use($router){
        // for user account
        $router->group(['prefix' => 'user'], function() use($router){
            // complaint group
            $router->group(['prefix' => 'complaint'], function() use($router){
                $router->post('add', 'Account\ComplaintController@add');
                $router->post('infinite/{id}', 'Account\ComplaintController@infinite');
            });

            // notification group
            $router->group(['prefix' => 'notification'], function() use($router){
                $router->post('/', 'Account\NotificationController@user');
                $router->post('read', 'Account\NotificationController@read');
                $router->post('add', 'Account\NotificationController@add');
                $router->post('infinite/{id}', 'Account\NotificationController@infinite');
            });

            // user group
            $router->group(['prefix' => 'group'], function() use($router){
                $router->get("/", 'Account\UserGroupController@index');
                $router->get("find/{id}", 'Account\UserGroupController@find');
                $router->post("menu", 'Account\UserGroupController@menu');
                $router->post("add", 'Account\UserGroupController@add');
                $router->post("edit", 'Account\UserGroupController@edit');
                $router->post("delete", 'Account\UserGroupController@delete');
                $router->post("role", 'Account\UserGroupController@role');
                $router->post("genRole", 'Account\UserGroupController@genRole');
                $router->post("grid", 'Account\UserGroupController@grid');
            });

            // photo group

            // user group
            $router->group(['prefix' => 'photo'], function() use($router){
                $router->post("update", 'Account\UserController@photo');
            });
        });
    });




    // Purchasing Group
    $router->group(['prefix' => 'pur'], function() use($router){

        // request group
        $router->group(['prefix' => 'req'], function() use($router){
            // Purchase Order group
            $router->group(['prefix' => 'po'], function() use($router){
                $router->get('find/{id}','Purchasing\PoController@find');
                $router->post('check_price', 'Purchasing\PoController@check_price');
                $router->post('cancel', 'Purchasing\PoController@cancel');
                $router->post('process', 'Purchasing\PoController@process');
                $router->post('print_data', 'Purchasing\PoController@print_data');
                $router->post('get', 'Purchasing\PoController@get');
                $router->post('grid', 'Purchasing\PoController@grid');
                // history group
                $router->group(['prefix' => 'history'], function() use($router){
                  $router->post('get', 'Purchasing\PoController@history_get');
                  $router->post('grid', 'Purchasing\PoController@history_grid');
                });
            });
        });
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
                $router->post('get','Document\RequestController@get_tools');
                $router->post('grid','Document\RequestController@grid_tools');
                $router->post('send','Document\RequestController@send_tools');
            });

            // request Purchase Order Group
            $router->group(['prefix' => 'po'], function() use($router){
                $router->get('find/{id}','Document\RequestController@find_po');
                $router->post('add','Document\RequestController@add_po');
                $router->post('delete','Document\RequestController@delete_po');
                $router->post('get','Document\RequestController@get_po');
                $router->post('grid','Document\RequestController@grid_po');
            });

            // request Delivery Order Group
            $router->group(['prefix' => 'do'], function() use($router){
                $router->get('find/{id}','Document\RequestController@find_do');
                $router->post('check','Document\RequestController@check_do');
                $router->post('add','Document\RequestController@add_do');
                $router->post('delete','Document\RequestController@delete_do');
                $router->post('get','Document\RequestController@get_do');
                $router->post('grid','Document\RequestController@grid_do');
            });

            // Borrow group
            $router->group(['prefix' => 'borrow'], function() use($router){
                $router->get('find/{id}','Document\BorrowController@find');
                $router->post('add','Document\BorrowController@add');
                $router->post('edit','Document\BorrowController@edit');
                $router->post('delete','Document\BorrowController@delete');
                // $router->post('get','Document\RequestController@get_tools');
                $router->post('grid','Document\BorrowController@grid');
                // $router->post('send','Document\RequestController@send_tools');
            });
        });

        // Warehouse Stock group
        $router->group(['prefix' => 'stock'], function() use($router){
            // add stock
            $router->get('find/{id}', 'Warehouse\StockController@find');
            $router->post('find_by_stock', 'Warehouse\StockController@find_by_stock');
            $router->post('add', 'Warehouse\StockController@add');
            $router->post('edit', 'Warehouse\StockController@edit');
            $router->post('delete', 'Warehouse\StockController@delete');
            $router->post('autocomplete', 'Warehouse\StockController@autocomplete');
            $router->post('get','Warehouse\StockController@get');
            $router->post('grid','Warehouse\StockController@grid');
            $router->post('qty','Warehouse\StockController@qty');
            $router->post('history','Warehouse\StockController@history');
            $router->post('history_out','Warehouse\StockController@history_out');

            // warehouse stock cabinet
            $router->group(['prefix' => 'cabinet'], function() use($router){
                $router->post('add', 'Warehouse\CabinetController@add');
                $router->post('delete', 'Warehouse\CabinetController@delete');
                $router->post('grid', 'Warehouse\CabinetController@grid');
            });

            // warehouse list buy stock
            $router->group(['prefix' => 'list_buy'], function() use($router){
                $router->post('get', 'Warehouse\ListBuyController@get');
                $router->post('grid', 'Warehouse\ListBuyController@grid');
            });

            // warehouse stock opname
            $router->group(['prefix' => 'opname'], function() use($router){
                $router->get('find/{id}', 'Warehouse\OpnameController@find');
                $router->get('date', 'Warehouse\OpnameController@date');
                $router->post('add', 'Warehouse\OpnameController@add');
                $router->post('delete', 'Warehouse\OpnameController@delete');
                $router->post('get', 'Warehouse\OpnameController@get');
                $router->post('grid', 'Warehouse\OpnameController@grid');
                $router->post('approve', 'Warehouse\OpnameController@approve');
                $router->post('reject', 'Warehouse\OpnameController@reject');
            });

            // change first stock
            $router->group(['prefix' => 'qty'], function() use($router){
                $router->post('add', 'Warehouse\QtyController@add');
            });
        });
    });

});
