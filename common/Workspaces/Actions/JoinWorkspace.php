<?php

namespace App\Actions\Workspace;

use App\Notifications\WorkspaceInvitation;
use App\User;
use App\WorkspaceInvite;
use Session;

class JoinWorkspace
{
    public function execute(WorkspaceInvite $invite, User $user)
    {
        if ($invite->email === $user->email) {
            $invite->workspace->members()
                ->firstOrCreate(
                    ['user_id' => $user->id],
                    ['role_id' => $invite->role_id]
                );
            $invite->delete();
            $user->notifications()->where('type', WorkspaceInvitation::class)->delete();
            $this->setCookie($invite);
        }
        Session::remove('activeWorkspace');
    }

    private function setCookie(WorkspaceInvite $invite)
    {
        $cookieName = slugify(config('app.name')).'_activeWorkspace';
        cookie()->queue($cookieName, $invite->workspace->id, 0, null, null, null, false);
    }
}
