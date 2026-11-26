<?php

namespace App\Policies;

use App\Models\ReportBatch;
use App\Models\User;

class ReportBatchPolicy
{
    public function view(User $user, ReportBatch $batch): bool
    {
        return (int) $batch->user_id === (int) $user->id;
    }

    public function cancel(User $user, ReportBatch $batch): bool
    {
        return $this->view($user, $batch);
    }
}
