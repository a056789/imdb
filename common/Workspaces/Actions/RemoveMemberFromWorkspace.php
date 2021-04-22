<?php

namespace Common\Workspaces\Actions;

use Common\Workspaces\Workspace;
use Common\Workspaces\WorkspaceMember;
use const App\Providers\WORKSPACED_RESOURCES;

class RemoveMemberFromWorkspace
{
    public function execute(Workspace $workspace, int $userId)
    {
        // transfer workspace resources to owner
        if ($workspace->owner_id !== $userId) {
            foreach (WORKSPACED_RESOURCES as $model) {
                app($model)
                    ->where('workspace_id', $workspace->id)
                    ->where('user_id', $userId)
                    ->update(['user_id' => $workspace->owner_id]);
            }
        }

        app(WorkspaceMember::class)
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $userId)
            ->delete();
    }
}
