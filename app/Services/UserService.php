<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {

            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);

            return $user->fresh();
        });
    }
    public function updatePhoto(User $user, $file): User
    {
        // Hapus foto lama jika ada
        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        // Simpan foto baru
        $path = $file->store('profiles', 'public');

        $user->update([
            'photo' => $path
        ]);

        return $user->fresh();
    }
}
