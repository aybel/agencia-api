<?php

namespace App\Repositories;

use App\Models\Branch;
use Illuminate\Support\Collection;

class BranchRepository extends Repository
{
    public function model()
    {
        return Branch::class;
    }
}
