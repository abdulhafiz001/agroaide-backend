<?php

namespace App\Policies;

use App\Models\FarmImageAnalysis;
use App\Models\User;

class FarmImageAnalysisPolicy
{
    public function view(User $user, FarmImageAnalysis $scan): bool
    {
        return $user->id === $scan->user_id || $user->isStaff();
    }

    public function delete(User $user, FarmImageAnalysis $scan): bool
    {
        return $user->id === $scan->user_id;
    }

    public function review(User $user, FarmImageAnalysis $scan): bool
    {
        return $user->isStaff();
    }
}
