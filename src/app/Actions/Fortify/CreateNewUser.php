<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Http\Requests\RegisterRequest;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input)
    {
        // Validator::make($input, [
        //     'name'     => ['required', 'string', 'max:255'],
        //     'email'    => ['required', 'email', 'unique:users,email'],
        //     'password' => ['required', 'string', 'confirmed', 'min:8'],
        // ], [
        //     'name.required'      => 'お名前を入力してください',
        //     'email.required'     => 'メールアドレスを入力してください',
        //     'email.email'        => '有効なメールアドレスを入力してください',
        //     'email.unique'       => 'このメールアドレスは既に登録されています',
        //     'password.required'  => 'パスワードを入力してください',
        //     'password.min'       => 'パスワードは８文字以上で入力してください',
        //     'password.confirmed' => 'パスワードと一致しません',
        // ])->validate();

         /** @var \App\Http\Requests\RegisterRequest $req */
        $req = app(\App\Http\Requests\RegisterRequest::class);

        Validator::make($input, $req->rules(), $req->messages())->validate();

        return User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
