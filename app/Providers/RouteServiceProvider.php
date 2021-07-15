<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapWebRoutes();
        $this->mapAdminRoutes();
        $this->mapApiRoutes();
        $this->mapImporterRoutes();
        $this->mapInstallerRoutes();
        $this->mapUpdaterRoutes();
    }

    private function mapImporterRoutes()
    {
        Route::group([
            'as'         => 'importer.',
            'prefix'     => 'importer',
            'middleware' => ['web'],
            'namespace'  => 'App\Http\Controllers\System',
        ], function () {
            Route::get('/', 'ImporterController@index')->name('index');
            Route::post('/config', 'ImporterController@config')->name('config');
            Route::post('/dbtest', 'ImporterController@dbtest')->name('dbtest');

            // Run the actual importer process. Additional middleware
            Route::post('/run', 'ImporterController@run')->middleware('api')->name('run');
            Route::post('/complete', 'ImporterController@complete')->name('complete');
        });
    }

    private function mapInstallerRoutes()
    {
        Route::group([
            'as'         => 'installer.',
            'prefix'     => 'install',
            'middleware' => ['web'],
            'namespace'  => 'App\Http\Controllers\System',
        ], function () {
            Route::get('/', 'InstallerController@index')->name('index');
            Route::post('/dbtest', 'InstallerController@dbtest')->name('dbtest');

            Route::get('/step1', 'InstallerController@step1')->name('step1');
            Route::post('/step1', 'InstallerController@step1')->name('step1post');

            Route::get('/step2', 'InstallerController@step2')->name('step2');
            Route::post('/envsetup', 'InstallerController@envsetup')->name('envsetup');
            Route::get('/dbsetup', 'InstallerController@dbsetup')->name('dbsetup');

            Route::get('/step3', 'InstallerController@step3')->name('step3');
            Route::post('/usersetup', 'InstallerController@usersetup')->name('usersetup');

            Route::get('/complete', 'InstallerController@complete')->name('complete');
        });
    }

    protected function mapUpdaterRoutes()
    {
        Route::group([
            'as'         => 'update.',
            'prefix'     => 'update',
            'middleware' => ['web', 'auth', 'ability:admin,admin-access'],
            'namespace'  => 'App\Http\Controllers\System',
        ], function () {
            Route::get('/', 'UpdateController@index')->name('index');

            Route::get('/step1', 'UpdateController@step1')->name('step1');
            Route::post('/step1', 'UpdateController@step1')->name('step1post');

            Route::post('/run-migrations', 'UpdateController@run_migrations')->name('run_migrations');
            Route::get('/complete', 'UpdateController@complete')->name('complete');
        });
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    private function mapWebRoutes()
    {
        Route::group([
            'middleware' => ['web', 'language'],
            'namespace'  => $this->namespace,
        ], function () {
            Route::group([
                'namespace'  => 'Frontend',
                'prefix'     => '',
                'as'         => 'frontend.',
                'middleware' => ['auth'],
            ], function () {
                Route::resource('dashboard', 'DashboardController');

                Route::get('airports/{id}', 'AirportController@show')->name('airports.show');

                // Download a file
                Route::get('downloads', 'DownloadController@index')->name('downloads.index');
                Route::get('downloads/{id}', 'DownloadController@show')->name('downloads.download');

                Route::get('flights/bids', 'FlightController@bids')->name('flights.bids');
                Route::get('flights/search', 'FlightController@search')->name('flights.search');
                Route::resource('flights', 'FlightController');

                Route::get('pireps/fares', 'PirepController@fares');
                Route::post('pireps/{id}/submit', 'PirepController@submit')->name('pireps.submit');

                Route::resource('pireps', 'PirepController', [
                    'except' => ['show'],
                ]);

                Route::get('profile/acars', 'ProfileController@acars')->name('profile.acars');
                Route::get('profile/regen_apikey', 'ProfileController@regen_apikey')->name('profile.regen_apikey');

                Route::resource('profile', 'ProfileController');

                // SimBrief stuff
                Route::get('simbrief/generate', 'SimBriefController@generate')->name('simbrief.generate');
                Route::post('simbrief/apicode', 'SimBriefController@api_code')->name('simbrief.api_code');
                Route::get('simbrief/check_ofp', 'SimBriefController@check_ofp')->name('simbrief.check_ofp');
                Route::get('simbrief/update_ofp', 'SimBriefController@update_ofp')->name('simbrief.update_ofp');
                Route::get('simbrief/{id}', 'SimBriefController@briefing')->name('simbrief.briefing');
                Route::get('simbrief/{id}/prefile', 'SimBriefController@prefile')->name('simbrief.prefile');
                Route::get('simbrief/{id}/cancel', 'SimBriefController@cancel')->name('simbrief.cancel');
                Route::get('simbrief/{id}/generate_new', 'SimBriefController@generate_new')->name('simbrief.generate_new');
            });

            Route::group([
                'namespace' => 'Frontend',
                'prefix'    => '',
                'as'        => 'frontend.',
            ], function () {
                Route::get('/', 'HomeController@index')->name('home');
                Route::get('r/{id}', 'PirepController@show')->name('pirep.show.public');
                Route::get('pireps/{id}', 'PirepController@show')->name('pireps.show');

                Route::get('users/{id}', 'ProfileController@show')->name('users.show.public');
                Route::get('pilots/{id}', 'ProfileController@show')->name('pilots.show.public');

                Route::get('page/{slug}', 'PageController@show')->name('pages.show');

                Route::get('profile/{id}', 'ProfileController@show')->name('profile.show.public');

                Route::get('users', 'UserController@index')->name('users.index');
                Route::get('pilots', 'UserController@index')->name('pilots.index');

                Route::get('livemap', 'LiveMapController@index')->name('livemap.index');
            });

            Route::get('/logout', 'Auth\LoginController@logout')->name('auth.logout');
            Auth::routes(['verify' => true]);
        });
    }

    private function mapAdminRoutes()
    {
        Route::group([
            'namespace'  => $this->namespace.'\\Admin',
            'prefix'     => 'admin',
            'as'         => 'admin.',
            'middleware' => ['web', 'auth', 'ability:admin,admin-access'],
        ], static function () {
            // CRUD for airlines
            Route::resource('airlines', 'AirlinesController')
                ->middleware('ability:admin,airlines');

            // CRUD for roles
            Route::resource('roles', 'RolesController')
                ->middleware('role:admin');

            Route::get('airports/export', 'AirportController@export')
                ->name('airports.export')
                ->middleware('ability:admin,airports');

            Route::match([
                'get',
                'post',
                'put',
            ], 'airports/fuel', 'AirportController@fuel')
                ->middleware('ability:admin,airports');

            Route::match([
                'get',
                'post',
            ], 'airports/import', 'AirportController@import')
                ->name('airports.import')
                ->middleware('ability:admin,airports');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'airports/{id}/expenses', 'AirportController@expenses')
                ->middleware('ability:admin,airports');

            Route::resource('airports', 'AirportController')->middleware('ability:admin,airports');

            // Awards
            Route::resource('awards', 'AwardController')->middleware('ability:admin,awards');

            // aircraft and fare associations
            Route::get('aircraft/export', 'AircraftController@export')
                ->name('aircraft.export')
                ->middleware('ability:admin,aircraft');

            Route::match([
                'get',
                'post',
            ], 'aircraft/import', 'AircraftController@import')
                ->name('aircraft.import')
                ->middleware('ability:admin,aircraft');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'aircraft/{id}/expenses', 'AircraftController@expenses')
                ->middleware('ability:admin,aircraft');

            Route::resource('aircraft', 'AircraftController')
                ->middleware('ability:admin,aircraft');

            // expenses
            Route::get('expenses/export', 'ExpenseController@export')
                ->name('expenses.export')
                ->middleware('ability:admin,finances');

            Route::match([
                'get',
                'post',
            ], 'expenses/import', 'ExpenseController@import')
                ->name('expenses.import')
                ->middleware('ability:admin,finances');

            Route::resource('expenses', 'ExpenseController')
                ->middleware('ability:admin,finances');

            // fares
            Route::get('fares/export', 'FareController@export')
                ->name('fares.export')
                ->middleware('ability:admin,finances');

            Route::match([
                'get',
                'post',
            ], 'fares/import', 'FareController@import')
                ->name('fares.import')
                ->middleware('ability:admin,finances');

            Route::resource('fares', 'FareController')->middleware('ability:admin,finances');

            // files
            Route::post('files', 'FileController@store')
                ->name('files.store')
                ->middleware('ability:admin,files');

            Route::delete('files/{id}', 'FileController@destroy')
                ->name('files.delete')
                ->middleware('ability:admin,files');

            // finances
            Route::resource('finances', 'FinanceController')
                ->middleware('ability:admin,finances');

            // flights and aircraft associations
            Route::get('flights/export', 'FlightController@export')
                ->name('flights.export')
                ->middleware('ability:admin,flights');

            Route::match([
                'get',
                'post',
            ], 'flights/import', 'FlightController@import')
                ->name('flights.import')
                ->middleware('ability:admin,flights');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'flights/{id}/fares', 'FlightController@fares')
                ->middleware('ability:admin,flights');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'flights/{id}/fields', 'FlightController@field_values')
                ->middleware('ability:admin,flights');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'flights/{id}/subfleets', 'FlightController@subfleets')
                ->middleware('ability:admin,flights');

            Route::resource('flights', 'FlightController')
                ->middleware('ability:admin,flights');

            Route::resource('flightfields', 'FlightFieldController')
                ->middleware('ability:admin,flights');

            Route::resource('userfields', 'UserFieldController')->middleware('ability:admin,users');

            // pirep related routes
            Route::get('pireps/fares', 'PirepController@fares')->middleware('ability:admin,pireps');
            Route::get('pireps/pending', 'PirepController@pending')
                ->middleware('ability:admin,pireps');

            Route::resource('pireps', 'PirepController')
                ->middleware('ability:admin,pireps');

            Route::match([
                'get',
                'post',
                'delete',
            ], 'pireps/{id}/comments', 'PirepController@comments')
                ->middleware('ability:admin,pireps');

            Route::match([
                'post',
                'put',
            ], 'pireps/{id}/status', 'PirepController@status')
                ->name('pirep.status')
                ->middleware('ability:admin,pireps');

            Route::resource('pirepfields', 'PirepFieldController')
                ->middleware('ability:admin,pireps');

            // Pages
            Route::resource('pages', 'PagesController')->middleware('ability:admin,pages');

            // rankings
            Route::resource('ranks', 'RankController')->middleware('ability:admin,ranks');
            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'ranks/{id}/subfleets', 'RankController@subfleets')->middleware('ability:admin,ranks');

            // settings
            Route::match(['get'], 'settings', 'SettingsController@index')->middleware('ability:admin,settings');

            Route::match([
                'post',
                'put',
            ], 'settings', 'SettingsController@update')
                ->name('settings.update')->middleware('ability:admin,settings');

            // maintenance
            Route::match(['get'], 'maintenance', 'MaintenanceController@index')
                ->name('maintenance.index')->middleware('ability:admin,maintenance');

            Route::match(['post'], 'maintenance/cache', 'MaintenanceController@cache')
                ->name('maintenance.cache')->middleware('ability:admin,maintenance');

            Route::match(['post'], 'maintenance/update', 'MaintenanceController@update')
                ->name('maintenance.update')->middleware('ability:admin,maintenance');

            Route::match(['post'], 'maintenance/forcecheck', 'MaintenanceController@forcecheck')
                ->name('maintenance.forcecheck')->middleware('ability:admin,maintenance');

            Route::match(['post'], 'maintenance/cron_enable', 'MaintenanceController@cron_enable')
                ->name('maintenance.cron_enable')->middleware('ability:admin,maintenance');

            Route::match(['post'], 'maintenance/cron_disable', 'MaintenanceController@cron_disable')
                ->name('maintenance.cron_disable')->middleware('ability:admin,maintenance');

            // subfleet
            Route::get('subfleets/export', 'SubfleetController@export')
                ->name('subfleets.export')->middleware('ability:admin,fleet');

            Route::match([
                'get',
                'post',
            ], 'subfleets/import', 'SubfleetController@import')
                ->name('subfleets.import')->middleware('ability:admin,fleet');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'subfleets/{id}/expenses', 'SubfleetController@expenses')->middleware('ability:admin,fleet');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'subfleets/{id}/fares', 'SubfleetController@fares')->middleware('ability:admin,fleet');

            Route::match([
                'get',
                'post',
                'put',
                'delete',
            ], 'subfleets/{id}/ranks', 'SubfleetController@ranks')->middleware('ability:admin,fleet');

            Route::resource('subfleets', 'SubfleetController')->middleware('ability:admin,fleet');

            /**
             * USERS
             */
            Route::delete('users/{id}/award/{award_id}', 'UserController@destroy_user_award')
                ->name('users.destroy_user_award')->middleware('ability:admin,users');

            Route::get('users/{id}/regen_apikey', 'UserController@regen_apikey')
                ->name('users.regen_apikey')->middleware('ability:admin,users');

            Route::resource('users', 'UserController')->middleware('ability:admin,users');

            // defaults
            Route::get('', ['uses' => 'DashboardController@index'])
                ->middleware('update_pending', 'ability:admin,admin-access');

            Route::get('/', ['uses' => 'DashboardController@index'])
                ->middleware('update_pending', 'ability:admin,admin-access');

            Route::get('dashboard', [
                'uses' => 'DashboardController@index',
                'name' => 'dashboard',
            ])->middleware('update_pending', 'ability:admin,admin-access');

            Route::match([
                'get',
                'post',
                'delete',
            ], 'dashboard/news', ['uses' => 'DashboardController@news'])
                ->name('dashboard.news')->middleware('update_pending', 'ability:admin,admin-access');

            //Modules
            Route::group([
                'as'         => 'modules.',
                'prefix'     => 'modules',
                'middleware' => ['ability:admin,modules'],
            ], function () {

                //Modules Index
                Route::get('/', 'ModulesController@index')->name('index');

                //Add Module
                Route::get('/create', 'ModulesController@create')->name('create');

                //Store Module
                Route::post('/create', 'ModulesController@store')->name('store');

                //Enable Module
                Route::post('/enable', 'ModulesController@enable')->name('enable');

                //Edit Module
                Route::get('/{id}/edit', 'ModulesController@edit')->name('edit');

                //Update Module
                Route::post('/{id}', 'ModulesController@update')->name('update');

                //Delete Module
                Route::delete('/{id}', 'ModulesController@destroy')->name('destroy');
            });
        });
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    private function mapApiRoutes()
    {
        Route::group([
            'middleware' => ['api'],
            'namespace'  => $this->namespace.'\\Api',
            'prefix'     => 'api',
            'as'         => 'api.',
        ], function () {
            Route::group([], function () {
                Route::get('acars', 'AcarsController@live_flights');
                Route::get('acars/geojson', 'AcarsController@pireps_geojson');

                Route::get('pireps/{pirep_id}', 'PirepController@get');
                Route::get('pireps/{pirep_id}/acars/geojson', 'AcarsController@acars_geojson');

                Route::get('cron/{id}', 'MaintenanceController@cron')->name('maintenance.cron');

                Route::get('news', 'NewsController@index');
                Route::get('status', 'StatusController@status');
                Route::get('version', 'StatusController@status');
            });

            /*
             * These need to be authenticated with a user's API key
             */
            Route::group(['middleware' => ['api.auth']], function () {
                Route::get('airlines', 'AirlineController@index');
                Route::get('airlines/{id}', 'AirlineController@get');

                Route::get('airports', 'AirportController@index');
                Route::get('airports/hubs', 'AirportController@index_hubs');
                Route::get('airports/{id}', 'AirportController@get');
                Route::get('airports/{id}/lookup', 'AirportController@lookup');
                Route::get('airports/{id}/distance/{to}', 'AirportController@distance');

                Route::get('fleet', 'FleetController@index');
                Route::get('fleet/aircraft/{id}', 'FleetController@get_aircraft');

                Route::get('subfleet', 'FleetController@index');
                Route::get('subfleet/aircraft/{id}', 'FleetController@get_aircraft');

                Route::get('flights', 'FlightController@index');
                Route::get('flights/search', 'FlightController@search');
                Route::get('flights/{id}', 'FlightController@get');
                Route::get('flights/{id}/briefing', 'FlightController@briefing')->name('flights.briefing');
                Route::get('flights/{id}/route', 'FlightController@route');

                Route::get('pireps', 'UserController@pireps');
                Route::put('pireps/{pirep_id}', 'PirepController@update');

                /*
                 * ACARS related
                 */
                Route::post('pireps/prefile', 'PirepController@prefile');
                Route::post('pireps/{pirep_id}', 'PirepController@update');
                Route::patch('pireps/{pirep_id}', 'PirepController@update');
                Route::post('pireps/{pirep_id}/update', 'PirepController@update');
                Route::post('pireps/{pirep_id}/file', 'PirepController@file');
                Route::post('pireps/{pirep_id}/comments', 'PirepController@comments_post');
                Route::put('pireps/{pirep_id}/cancel', 'PirepController@cancel');
                Route::delete('pireps/{pirep_id}/cancel', 'PirepController@cancel');

                Route::get('pireps/{pirep_id}/fields', 'PirepController@fields_get');
                Route::post('pireps/{pirep_id}/fields', 'PirepController@fields_post');

                Route::get('pireps/{pirep_id}/finances', 'PirepController@finances_get');
                Route::post('pireps/{pirep_id}/finances/recalculate', 'PirepController@finances_recalculate');

                Route::get('pireps/{pirep_id}/route', 'PirepController@route_get');
                Route::post('pireps/{pirep_id}/route', 'PirepController@route_post');
                Route::delete('pireps/{pirep_id}/route', 'PirepController@route_delete');

                Route::get('pireps/{pirep_id}/comments', 'PirepController@comments_get');

                Route::get('pireps/{pirep_id}/acars/position', 'AcarsController@acars_get');
                Route::post('pireps/{pirep_id}/acars/position', 'AcarsController@acars_store');
                Route::post('pireps/{pirep_id}/acars/positions', 'AcarsController@acars_store');

                Route::post('pireps/{pirep_id}/acars/events', 'AcarsController@acars_events');
                Route::post('pireps/{pirep_id}/acars/logs', 'AcarsController@acars_logs');

                Route::get('settings', 'SettingsController@index');

                // This is the info of the user whose token is in use
                Route::get('user', 'UserController@index');
                Route::get('user/fleet', 'UserController@fleet');
                Route::get('user/pireps', 'UserController@pireps');

                Route::get('bids', 'UserController@bids');
                Route::get('bids/{id}', 'UserController@get_bid');
                Route::get('user/bids/{id}', 'UserController@get_bid');

                Route::get('user/bids', 'UserController@bids');
                Route::put('user/bids', 'UserController@bids');
                Route::post('user/bids', 'UserController@bids');
                Route::delete('user/bids', 'UserController@bids');

                Route::get('users/me', 'UserController@index');
                Route::get('users/{id}', 'UserController@get');
                Route::get('users/{id}/fleet', 'UserController@fleet');
                Route::get('users/{id}/pireps', 'UserController@pireps');

                Route::get('users/{id}/bids', 'UserController@bids');
                Route::put('users/{id}/bids', 'UserController@bids');
            });
        });
    }
}
