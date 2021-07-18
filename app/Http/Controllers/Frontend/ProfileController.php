<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Events\ProfileUpdated;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Repositories\AirlineRepository;
use App\Repositories\AirportRepository;
use App\Repositories\UserRepository;
use App\Support\Countries;
use App\Support\Discord;
use App\Support\Timezonelist;
use App\Support\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Laracasts\Flash\Flash;
use Nwidart\Modules\Facades\Module;

class ProfileController extends Controller
{
    private $airlineRepo;
    private $airportRepo;
    private $userRepo;

    /**
     * ProfileController constructor.
     *
     * @param AirlineRepository $airlineRepo
     * @param AirportRepository $airportRepo
     * @param UserRepository    $userRepo
     */
    public function __construct(
        AirlineRepository $airlineRepo,
        AirportRepository $airportRepo,
        UserRepository $userRepo
    ) {
        $this->airlineRepo = $airlineRepo;
        $this->airportRepo = $airportRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Return whether the vmsACARS module is enabled or not
     */
    private function acarsEnabled(): bool
    {
        // Is the ACARS module enabled?
        $acars_enabled = false;
        $acars = Module::find('VMSAcars');
        if ($acars) {
            $acars_enabled = $acars->isEnabled();
        }

        return $acars_enabled;
    }

    /**
     * Redirect to show() since only a single page gets shown and the template controls
     * the other items that are/aren't shown
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return $this->show(Auth::user()->id);
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function show($id)
    {
        /** @var \App\Models\User $user */
        $user = User::with(['awards', 'fields', 'fields.field'])
            ->where('id', $id)
            ->first();

        if (empty($user)) {
            Flash::error('User not found!');
            return redirect(route('frontend.dashboard.index'));
        }

        $airports = $this->airportRepo->all();
        $userFields = $this->userRepo->getUserFields($user, true);

        return view('profile.index', [
            'user'       => $user,
            'userFields' => $userFields,
            'airports'   => $airports,
            'acars'      => $this->acarsEnabled(),
        ]);
    }

    /**
     * Show the edit for form the user's profile
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function edit(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = User::with(['fields', 'fields.field'])
            ->where('id', Auth::user()->id)
            ->first();

        if (empty($user)) {
            Flash::error('User not found!');
            return redirect(route('frontend.dashboard.index'));
        }

        $airlines = $this->airlineRepo->selectBoxList();
        $airports = $this->airportRepo->selectBoxList(false, setting('pilots.home_hubs_only'));
        $userFields = $this->userRepo->getUserFields($user, true);

        return view('profile.edit', [
            'user'       => $user,
            'airlines'   => $airlines,
            'airports'   => $airports,
            'countries'  => Countries::getSelectList(),
            'timezones'  => Timezonelist::toArray(),
            'userFields' => $userFields,
        ]);
    }

    /**
     * @param Request $request
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return mixed
     */
    public function update(Request $request)
    {
        $id = Auth::user()->id;
        $user = $this->userRepo->findWithoutFail($id);

        $rules = [
            'name'       => 'required',
            'email'      => 'required|unique:users,email,'.$id,
            'airline_id' => 'required',
            'password'   => 'confirmed',
            'avatar'     => 'nullable|mimes:jpeg,png,jpg',
        ];

        $userFields = UserField::where(['show_on_registration' => true, 'required' => true])->get();
        foreach ($userFields as $field) {
            $rules['field_'.$field->slug] = 'required';
        }

        $validator = Validator::make($request->toArray(), $rules);

        if ($validator->fails()) {
            Log::info('validator failed for user '.$user->ident);
            Log::info($validator->errors()->toArray());

            return redirect(route('frontend.profile.edit', $id))
                ->withErrors($validator)
                ->withInput();
        }

        $req_data = $request->all();
        if (!$request->filled('password')) {
            unset($req_data['password']);
        } else {
            $req_data['password'] = Hash::make($req_data['password']);
        }

        if (isset($req_data['avatar']) !== null) {
            Storage::delete($user->avatar);
        }

        // Find out the user's private channel id
        /*
        // TODO: Uncomment when Discord API functionality is enabled
        if ($request->filled('discord_id')) {
            $discord_id = $request->post('discord_id');
            if ($discord_id !== $user->discord_id) {
                $req_data['discord_private_channel_id'] = Discord::getPrivateChannelId($discord_id);
            }
        }*/

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $file_name = $user->ident.'.'.$avatar->getClientOriginalExtension();
            $path = "avatars/{$file_name}";

            // Create the avatar, resizing it and keeping the aspect ratio.
            // https://stackoverflow.com/a/26892028
            $w = config('phpvms.avatar.width');
            $h = config('phpvms.avatar.height');

            $canvas = Image::canvas($w, $h);
            $image = Image::make($avatar)->resize($w, $h, static function ($constraint) {
                $constraint->aspectRatio();
            });

            $canvas->insert($image);
            Log::info('Uploading avatar into folder '.public_path('uploads/avatars'));
            $canvas->save(public_path('uploads/avatars/'.$file_name));

            $req_data['avatar'] = $path;
        }

        $this->userRepo->update($req_data, $id);

        // Save all of the user fields
        $userFields = UserField::all();
        foreach ($userFields as $field) {
            $field_name = 'field_'.$field->slug;
            UserFieldValue::updateOrCreate([
                'user_field_id' => $field->id,
                'user_id'       => $id,
            ], ['value' => $request->get($field_name)]);
        }

        // Dispatch event including whether an avatar has been updated
        ProfileUpdated::dispatch($user, $request->hasFile('avatar'));

        Flash::success('Profile updated successfully!');

        return redirect(route('frontend.profile.index'));
    }

    /**
     * Regenerate the user's API key
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function regen_apikey(Request $request)
    {
        $user = User::find(Auth::user()->id);
        Log::info('Regenerating API key "'.$user->ident.'"');

        $user->api_key = Utils::generateApiKey();
        $user->save();

        flash('New API key generated!')->success();

        return redirect(route('frontend.profile.index'));
    }

    /**
     * Generate the ACARS config and send it to download
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function acars(Request $request)
    {
        $user = Auth::user();
        $config = view('system.acars.config', ['user' => $user])->render();

        return response($config)->withHeaders([
            'Content-Type'        => 'text/xml',
            'Content-Length'      => strlen($config),
            'Cache-Control'       => 'no-store, no-cache',
            'Content-Disposition' => 'attachment; filename="settings.xml',
        ]);
    }
}
