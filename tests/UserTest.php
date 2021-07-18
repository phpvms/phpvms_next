<?php

namespace Tests;

use App\Events\ProfileUpdated;
use App\Exceptions\PilotIdNotFound;
use App\Exceptions\UserPilotIdExists;
use App\Models\Airline;
use App\Models\Enums\UserState;
use App\Models\Fare;
use App\Models\Pirep;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\FareService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

class UserTest extends TestCase
{
    /** @var SettingRepository */
    protected $settingsRepo;

    /** @var UserService */
    protected $userSvc;

    public function setUp(): void
    {
        parent::setUp();
        $this->userSvc = app(UserService::class);
        $this->settingsRepo = app(SettingRepository::class);
    }

    /**
     * Makes sure that the subfleet/aircraft returned are allowable
     * by the users rank.
     */
    public function testRankSubfleets()
    {
        // Add subfleets and aircraft, but also add another
        // set of subfleets
        $subfleetA = $this->createSubfleetWithAircraft();
        $this->createSubfleetWithAircraft();

        $rank = $this->createRank(10, [$subfleetA['subfleet']->id]);

        $user = factory(User::class)->create([
            'rank_id' => $rank->id,
        ]);

        $added_aircraft = $subfleetA['aircraft']->pluck('id');

        $subfleets = $this->userSvc->getAllowableSubfleets($user);
        $this->assertEquals(1, $subfleets->count());

        $subfleet = $subfleets[0];
        $all_aircraft = $subfleet->aircraft->pluck('id');
        $this->assertEquals($added_aircraft, $all_aircraft);

        /**
         * Check via API
         */
        $resp = $this->get('/api/user/fleet', [], $user)->assertStatus(200);
        $body = $resp->json()['data'];

        // Get the subfleet that's been added in
        $subfleet_from_api = $body[0];
        $this->assertEquals($subfleet->id, $subfleet_from_api['id']);

        // Get all the aircraft from that subfleet
        $aircraft_from_api = collect($subfleet_from_api['aircraft'])->pluck('id');
        $this->assertEquals($added_aircraft, $aircraft_from_api);

        /**
         * Check the user ID call
         */
        $resp = $this->get('/api/users/'.$user->id.'/fleet', [], $user)->assertStatus(200);
        $body = $resp->json()['data'];

        // Get the subfleet that's been added in
        $subfleet_from_api = $body[0];
        $this->assertEquals($subfleet->id, $subfleet_from_api['id']);

        // Get all the aircraft from that subfleet
        $aircraft_from_api = collect($subfleet_from_api['aircraft'])->pluck('id');
        $this->assertEquals($added_aircraft, $aircraft_from_api);
    }

    /**
     * Flip the setting for getting all of the user's aircraft restricted
     * by rank. Make sure that they're all returned
     *
     * @throws \Exception
     */
    public function testGetAllAircraft()
    {
        $fare_svc = app(FareService::class);

        // Add subfleets and aircraft, but also add another
        // set of subfleets
        $subfleetA = $this->createSubfleetWithAircraft();
        $subfleetB = $this->createSubfleetWithAircraft();

        $fare = factory(Fare::class)->create([
            'price'    => 20,
            'capacity' => 200,
        ]);

        $overrides = [
            'price'    => 50,
            'capacity' => 400,
        ];

        $fare_svc->setForSubfleet($subfleetA['subfleet'], $fare, $overrides);

        $added_aircraft = array_merge(
            $subfleetA['aircraft']->pluck('id')->toArray(),
            $subfleetB['aircraft']->pluck('id')->toArray()
        );

        $rank = $this->createRank(10, [$subfleetA['subfleet']->id]);

        $user = factory(User::class)->create([
            'rank_id' => $rank->id,
        ]);

        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);

        $subfleets = $this->userSvc->getAllowableSubfleets($user);
        $this->assertEquals(2, $subfleets->count());

        $all_aircraft = array_merge(
            $subfleets[0]->aircraft->pluck('id')->toArray(),
            $subfleets[1]->aircraft->pluck('id')->toArray()
        );

        $this->assertEquals($added_aircraft, $all_aircraft);

        $subfleetACalled = collect($subfleets)->firstWhere('id', $subfleetA['subfleet']->id);
        $this->assertEquals($subfleetACalled->fares[0]['price'], $overrides['price']);
        $this->assertEquals($subfleetACalled->fares[0]['capacity'], $overrides['capacity']);

        /**
         * Check via API, but should only show the single subfleet being returned
         */
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', true);

        $resp = $this->get('/api/user/fleet', [], $user)->assertStatus(200);

        // Get all the aircraft from that subfleet, check the fares
        $body = $resp->json()['data'];
        $subfleetAFromApi = collect($body)->firstWhere('id', $subfleetA['subfleet']->id);
        $this->assertEquals($subfleetAFromApi['fares'][0]['price'], $overrides['price']);
        $this->assertEquals($subfleetAFromApi['fares'][0]['capacity'], $overrides['capacity']);

        // Read the user's profile and make sure that subfleet C is not part of this
        // Should only return a single subfleet (subfleet A)
        $resp = $this->get('/api/user', [], $user);
        $resp->assertStatus(200);

        $body = $resp->json('data');
        $subfleets = $body['rank']['subfleets'];

        $this->assertEquals(1, count($subfleets));
        $this->assertEquals($subfleets[0]['fares'][0]['price'], $overrides['price']);
        $this->assertEquals($subfleets[0]['fares'][0]['capacity'], $overrides['capacity']);
    }

    /**
     * Flip the setting for getting all of the user's aircraft restricted
     * by rank. Make sure that they're all returned. Create two subfleets,
     * assign only one of them to the user's rank. When calling the api
     * to retrieve the flight, only subfleetA should be showing
     */
    public function testGetAircraftAllowedFromFlight()
    {
        // Add subfleets and aircraft, but also add another
        // set of subfleets
        $airport = factory(\App\Models\Airport::class)->create();
        $subfleetA = $this->createSubfleetWithAircraft(2, $airport->id);
        $subfleetB = $this->createSubfleetWithAircraft(2);

        $rank = $this->createRank(10, [$subfleetA['subfleet']->id]);
        $user = factory(User::class)->create([
            'curr_airport_id' => $airport->id,
            'rank_id'         => $rank->id,
        ]);

        $flight = factory(\App\Models\Flight::class)->create([
            'airline_id'     => $user->airline_id,
            'dpt_airport_id' => $airport->id,
        ]);

        $flight->subfleets()->syncWithoutDetaching([
            $subfleetA['subfleet']->id,
            $subfleetB['subfleet']->id,
        ]);

        // Make sure no flights are filtered out
        $this->settingsRepo->store('pilots.only_flights_from_current', false);

        // And restrict the aircraft
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', false);

        $response = $this->get('/api/flights/'.$flight->id, [], $user);
        $response->assertStatus(200);
        $this->assertCount(2, $response->json()['data']['subfleets']);

        /*
         * Now make sure it's filtered out
         */
        $this->settingsRepo->store('pireps.restrict_aircraft_to_rank', true);

        /**
         * Make sure it's filtered out from the single flight call
         */
        $response = $this->get('/api/flights/'.$flight->id, [], $user);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['data']['subfleets']);

        /**
         * Make sure it's filtered out from the flight list
         */
        $response = $this->get('/api/flights', [], $user);
        $body = $response->json()['data'];
        $response->assertStatus(200);
        $this->assertCount(1, $body[0]['subfleets']);

        /**
         * Filtered from search?
         */
        $response = $this->get('/api/flights/search?flight_id='.$flight->id, [], $user);
        $response->assertStatus(200);
        $body = $response->json()['data'];
        $this->assertCount(1, $body[0]['subfleets']);
    }

    /**
     * Test the pilot ID being added when a new user is created
     */
    public function testUserPilotIdChangeAlreadyExists()
    {
        $this->expectException(UserPilotIdExists::class);
        $user1 = factory(User::class)->create(['id' => 1]);
        $user2 = factory(User::class)->create(['id' => 2]);

        // Now try to change the original user's pilot_id to 2 (should conflict)
        $this->userSvc->changePilotId($user1, 2);
    }

    /**
     * Make sure that the splitting of the user ID works
     */
    public function testUserPilotIdSplit(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $found_user = $this->userSvc->findUserByPilotId($user->ident);
        $this->assertEquals($user->id, $found_user->id);

        // Look for them with the IATA code
        $found_user = $this->userSvc->findUserByPilotId($user->airline->iata.$user->id);
        $this->assertEquals($user->id, $found_user->id);
    }

    /**
     * Pilot ID not found
     */
    public function testUserPilotIdSplitInvalidId(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        $this->expectException(PilotIdNotFound::class);
        $this->userSvc->findUserByPilotId($user->airline->iata);
    }

    public function testUserPilotIdInvalidIATA(): void
    {
        /** @var Airline $airline */
        $airline = factory(Airline::class)->create(['icao' => 'ABC', 'iata' => null]);

        /** @var User $user */
        $user = factory(User::class)->create(['airline_id' => $airline->id]);

        $this->expectException(PilotIdNotFound::class);
        $this->userSvc->findUserByPilotId('123');
    }

    /**
     * Test the pilot ID being added when a new user is created
     */
    public function testUserPilotIdAdded()
    {
        $new_user = factory(User::class)->make()->toArray();
        $new_user['password'] = Hash::make('secret');
        $user = $this->userSvc->createUser($new_user);
        $this->assertEquals($user->id, $user->pilot_id);

        // Add a second user
        $new_user = factory(User::class)->make()->toArray();
        $new_user['password'] = Hash::make('secret');
        $user2 = $this->userSvc->createUser($new_user);
        $this->assertEquals($user2->id, $user2->pilot_id);

        // Now try to change the original user's pilot_id to 3
        $user = $this->userSvc->changePilotId($user, 3);
        $this->assertEquals(3, $user->pilot_id);

        // Create a new user and the pilot_id should be 4
        $user3 = factory(User::class)->create();
        $this->assertEquals(4, $user3->pilot_id);
    }

    public function testUserPilotDeleted()
    {
        $new_user = factory(User::class)->make()->toArray();
        $new_user['password'] = Hash::make('secret');
        $admin_user = $this->userSvc->createUser($new_user);

        $new_user = factory(User::class)->make()->toArray();
        $new_user['password'] = Hash::make('secret');
        $user = $this->userSvc->createUser($new_user);
        $this->assertEquals($user->id, $user->pilot_id);

        // Delete the user
        $this->userSvc->removeUser($user);

        $response = $this->get('/api/user/'.$user->id, [], $admin_user);
        $response->assertStatus(404);

        // Get from the DB
        $user = User::find($user->id);
        $this->assertNull($user);
    }

    public function testUserPilotDeletedWithPireps()
    {
        $new_user = factory(User::class)->make()->toArray();
        $new_user['password'] = Hash::make('secret');
        $admin_user = $this->userSvc->createUser($new_user);

        $new_user = factory(User::class)->make()->toArray();
        $new_user['password'] = Hash::make('secret');
        $user = $this->userSvc->createUser($new_user);
        $this->assertEquals($user->id, $user->pilot_id);

        /** @var Pirep $pirep */
        factory(Pirep::class)->create([
            'user_id' => $user->id,
        ]);

        // Delete the user
        $this->userSvc->removeUser($user);

        $response = $this->get('/api/user/'.$user->id, [], $admin_user);
        $response->assertStatus(404);

        // Get from the DB
        $user = User::find($user->id);
        $this->assertEquals('Deleted User', $user->name);
        $this->assertNotEquals($new_user['password'], $user->password);
    }

    /**
     * Test that a user's name is private
     */
    public function testUserNamePrivate()
    {
        $vals = [
            'Firstname'                     => 'Firstname',
            'Firstname Lastname'            => 'Firstname L',
            'Firstname Middlename Lastname' => 'Firstname L',
        ];

        foreach ($vals as $input => $expected) {
            $user = new User(['name' => $input]);
            $this->assertEquals($expected, $user->name_private);
        }
    }

    /**
     * @throws \Exception
     */
    public function testUserLeave(): void
    {
        $this->createUser(['status' => UserState::ACTIVE]);

        $users_on_leave = $this->userSvc->findUsersOnLeave();
        $this->assertCount(0, $users_on_leave);

        $this->updateSetting('pilots.auto_leave_days', 1);
        $user = $this->createUser([
            'state'      => UserState::ACTIVE,
            'status'     => UserState::ACTIVE,
            'created_at' => Carbon::now('UTC')->subDays(5),
        ]);

        $users_on_leave = $this->userSvc->findUsersOnLeave();
        $this->assertCount(1, $users_on_leave);
        $this->assertEquals($user->id, $users_on_leave->first()->id);

        // Give that user a new PIREP, still old
        /** @var Pirep $pirep */
        $pirep = factory(Pirep::class)->create([
            'user_id'      => $user->id,
            'created_at'   => Carbon::now('UTC')->subDays(5),
            'submitted_at' => Carbon::now('UTC')->subDays(5),
        ]);

        $user->last_pirep_id = $pirep->id;
        $user->save();
        $user->refresh();

        $users_on_leave = $this->userSvc->findUsersOnLeave();
        $this->assertCount(1, $users_on_leave);
        $this->assertEquals($user->id, $users_on_leave->first()->id);

        // Create a new PIREP
        /** @var Pirep $pirep */
        $pirep = factory(Pirep::class)->create([
            'user_id'      => $user->id,
            'created_at'   => Carbon::now('UTC'),
            'submitted_at' => Carbon::now('UTC'),
        ]);

        $user->last_pirep_id = $pirep->id;
        $user->save();
        $user->refresh();

        $users_on_leave = $this->userSvc->findUsersOnLeave();
        $this->assertCount(0, $users_on_leave);

        // Check disable_activity_checks
        $user = $this->createUser([
            'status'     => UserState::ACTIVE,
            'created_at' => Carbon::now('UTC')->subDays(5),
        ]);

        $role = $this->createRole([
            'disable_activity_checks' => true,
        ]);

        $user->attachRole($role);
        $user->save();

        $users_on_leave = $this->userSvc->findUsersOnLeave();
        $this->assertCount(0, $users_on_leave);
    }

    public function testEventCalledWhenProfileUpdated()
    {
        Event::fake();
        $user = factory(User::class)->create();

        $body = [
            'name'       => 'Test User',
            'email'      => $user->email,
            'airline_id' => 1,
        ];

        $resp = $this->actingAs($user)->put('/profile/'.$user->id, $body);

        Event::assertDispatched(ProfileUpdated::class);
    }
}
