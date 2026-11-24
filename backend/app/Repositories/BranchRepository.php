<?php

namespace App\Repositories;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;

class BranchRepository extends Repository
{
    public function model()
    {
        return 'App\Models\Branch';
    }
}
