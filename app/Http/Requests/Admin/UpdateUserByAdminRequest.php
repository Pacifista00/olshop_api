<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class UpdateUserByAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $authUser = $this->user();           // user yang login
        $targetUser = $this->route('user');  // user yang mau diupdate
        $newRole = $this->input('role');     // role baru dari request

        // ❗ Tidak boleh edit user dengan level sama atau lebih tinggi
        if (
            $this->roleLevel($authUser->role)
            <=
            $this->roleLevel($targetUser->role)
        ) {
            return false;
        }

        // ❗ Tidak boleh mengubah diri sendiri menjadi developer
        if (
            $authUser->id === $targetUser->id &&
            $newRole === 'developer'
        ) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'gender' => ['nullable', 'in:male,female'],
            'role' => ['required', 'in:developer,admin,customer'],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * Role hierarchy system
     */
    private function roleLevel(string $role): int
    {
        return match ($role) {
            'developer' => 3,
            'admin' => 2,
            'customer' => 1,
            default => 0,
        };
    }

    public function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.'
            ], 403)
        );
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422)
        );
    }


}
