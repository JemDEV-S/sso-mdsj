<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'required_with' => 'El campo :attribute es obligatorio cuando se envía :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'Ingrese un correo electrónico válido.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'regex' => 'El formato de :attribute no es válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'different' => 'El campo :attribute debe ser diferente de :other.',
    'boolean' => 'El valor de :attribute no es válido.',
    'in' => 'El valor seleccionado de :attribute no es válido.',
    'alpha_dash' => 'El campo :attribute solo admite letras, números, guiones y guiones bajos.',
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.'],
    'password' => [
        'letters' => 'La contraseña debe incluir letras.',
        'mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
        'numbers' => 'La contraseña debe incluir números.',
        'symbols' => 'La contraseña debe incluir símbolos.',
    ],
    'attributes' => [
        'dni' => 'DNI', 'nombres' => 'nombres', 'apellidos' => 'apellidos', 'email' => 'correo',
        'password' => 'contraseña', 'current_password' => 'contraseña actual', 'active' => 'estado',
        'name' => 'nombre', 'code' => 'código', 'redirect_uris' => 'URL de retorno',
    ],
];
