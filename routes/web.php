<?php

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['middleware' => 'access-key', 'prefix' => 'business'], function () use ($router) {
    $router->get('search', 'BusinessController@search');
    $router->get('categories', 'BusinessController@categories');
    $router->post('', 'BusinessController@add');
    $router->post('{id}', 'BusinessController@edit');
    $router->delete('{id}', 'BusinessController@delete');
});
