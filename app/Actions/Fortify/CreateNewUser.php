<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;

use App\Models\Client;
use App\Models\ClientUser;
use Laravel\Jetstream\Jetstream;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array  $input
     * @return \App\Models\User
     */
    public function create(array $input)
    {


        Validator::make($input, [
            'company' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
        ])->validate();

        $client= new Client();
        $client->name = $input['company'];
        $client->save();

        $user = DB::transaction(function () use ($input, $client) {

            return tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]), function (User $user) use ($client) {

                $this->createUserClientRelation($user, $client);
                $this->createTeam($user, $client);
            });
        });


        $client  = DB::transaction(function () use ($input) {

            return tap(Client::create([
                'name' => $input['company']]));

            });



        return $user;

    }

    /**
     * Create a personal team for the user.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    protected function createTeam(User $user, Client $client)
    {
        $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $client->name, 2)[0]."'s Team",
            'client_id' => $client->id,
            'personal_team' => true,
        ]));
    }

    protected function createUserClientRelation(User $user, Client $client)
    {




        $rel = new ClientUser();
        $rel->client_id = $client->id;
        $rel->user_id = $user->id;
        $rel->save();

            dd($rel);

        //ClientUser::create(['user_id'=>$user->id, 'client_id'=>$client->id]);
    }
}
