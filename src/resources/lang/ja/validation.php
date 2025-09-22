<?php
return [
    'required' => ':attribute を入力してください',
    'email'    => '有効な :attribute を入力してください',
    'min'      => [
        'string' => ':attribute は :min 文字以上で入力してください',
    ],
    'confirmed' => ':attribute が一致しません',

    'attributes' => [
        'name'                  => 'お名前',
        'email'                 => 'メールアドレス',
        'password'              => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
    ],
];
