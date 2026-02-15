<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserPhotoRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function update(UpdateUserRequest $request)
    {
        $user = $request->user();

        $updatedUser = $this->userService->update(
            $user,
            $request->validated()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Profile berhasil diperbarui.',
            'user' => new UserResource($updatedUser),
        ]);
    }
    public function updatePhoto(UpdateUserPhotoRequest $request)
    {
        $user = $request->user();

        $updatedUser = $this->userService->updatePhoto(
            $user,
            $request->file('photo')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Foto profile berhasil diperbarui.',
            'user' => new UserResource($updatedUser),
        ]);
    }
}
