<?php

namespace App\Http\Requests;

use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'rut' => [
                'required', 'string', 'max:12',
                Rule::unique('usuarios', 'rut')->ignore($usuario),
                function ($attribute, $value, $fail) {
                    if (! Rut::validar($value)) {
                        $fail('El RUT ingresado no es válido.');
                    }
                },
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tipo' => ['required', 'in:estudiante,docente,funcionario'],
            'carrera' => ['nullable', 'string', 'max:255'],
            'anio_ingreso' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'sexo' => ['nullable', 'string', 'max:50'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rut.unique' => 'Ya existe un usuario registrado con ese RUT.',
        ];
    }
}
