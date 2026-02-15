<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateUserByAdminRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserManagementController extends Controller
{
    public function update(UpdateUserByAdminRequest $request, User $user)
    {
        $user->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diperbarui.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
    public function updatePhoto(Request $request, User $user, UserService $userService)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $updatedUser = $userService->updatePhoto($user, $request->file('photo'));

        return response()->json([
            'status' => 'success',
            'message' => 'Foto berhasil diperbarui.',
            'user' => new UserResource($updatedUser),
        ]);
    }
}
