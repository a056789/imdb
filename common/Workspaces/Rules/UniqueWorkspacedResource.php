<?php

namespace App\Rules;

use App\ActiveWorkspace;
use Auth;
use Illuminate\Validation\Rules\Unique;

class UniqueWorkspacedResource extends Unique
{
    public function __construct($table, $column = 'NULL', $userId = null)
    {
        parent::__construct($table, $column);
        if ( ! app(ActiveWorkspace::class)->personal()) {
            $this->where('workspace_id', app(ActiveWorkspace::class)->workspace()->id);
        } else {
            $this->where('user_id', $userId ?? Auth::id());
        }
    }
}
