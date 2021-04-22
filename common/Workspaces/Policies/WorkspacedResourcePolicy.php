<?php

namespace Common\Workspaces\Policies;

use App\ActiveWorkspace;
use App\User;
use App\WorkspaceMember;
use Common\Core\Policies\BasePolicy;
use Common\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class WorkspacedResourcePolicy extends BasePolicy
{
    /**
     * @var string
     */
    protected $resource;

    /**
     * @var ActiveWorkspace
     */
    private $activeWorkspace;

    public function __construct(Request $request, Settings $settings, ActiveWorkspace $activeWorkspace)
    {
        parent::__construct($request, $settings);
        $this->activeWorkspace = $activeWorkspace;
    }

    public function index(User $currentUser, int $userId = null)
    {
        [, $permission] = $this->parseNamespace($this->resource, 'view');

        // filtering resources by user id
        if ($userId) {
            return $currentUser->id === $userId;

        // if we're requesting resources for a particular workspace,let user view the resources
        // as long as they are a member, event without explicit "resource.view" permission
        } else if ( ! $this->activeWorkspace->personal() && $this->activeWorkspace->member($currentUser->id)) {
            return true;
        } else {
            return $this->hasPermission($currentUser, $permission);
        }
    }

    public function show(User $currentUser, Model $resource)
    {
        [, $permission] = $this->parseNamespace($this->resource, 'view');

        if ($resource->user_id === $currentUser->id) {
            return true;
        // if we're requesting resources for a particular workspace,let user view the resources
        // as long as they are a member, event without explicit "resource.view" permission
        } else if ( ! $this->activeWorkspace->personal() && $this->activeWorkspace->member($currentUser->id)) {
            return true;
        } else {
            return $this->hasPermission($currentUser, $permission);
        }
    }

    public function store(User $currentUser)
    {
        [, $permission, $singularName, $pluralName] = $this->parseNamespace($this->resource);

         // user has no permission to create this resource at all
        if ( ! $this->hasPermission($currentUser, $permission)) {
            return false;
        }

        // either get "count restriction" for workspace owner or current user
        $maxCount = $this->activeWorkspace->getRestrictionValue($permission, 'count');

        // user does not have any restriction on maximum resource count
        if ( ! $maxCount) {
            return true;
        }

        // either get current resource count for workspace or for current user
        $currentCount = $this->activeWorkspace->getResourceCount($this->resource);

        // check if user did not go over their or specified workspace max quota
        if ($currentCount >= $maxCount) {
            $messageKey = $this->activeWorkspace->personal() ? 'policies.quota_exceeded' : 'policies.workspace_quota_exceeded';
            return $this->denyWithAction(
                __($messageKey, ['resources' => $pluralName, 'resource' => $singularName]),
                $this->activeWorkspace->currentUserIsOwner() ? $this->upgradeAction() : null
            );
        }

        return true;
    }

    public function update(User $currentUser, Model $resource)
    {
        [, $permission] = $this->parseNamespace($this->resource, 'update');

        if ($resource->user_id === $currentUser->id) {
            return true;
        } else {
            return $this->hasPermission($currentUser, $permission);
        }
    }

    public function destroy(User $currentUser, $resourceIds = null)
    {
        [, $permission] = $this->parseNamespace($this->resource, 'delete');

        if ($this->hasPermission($currentUser, $permission)) {
            return true;
        } else if ($resourceIds) {
            $dbCount = app($this->resource)
                ->whereIn('id', $resourceIds)
                ->where('user_id', $currentUser->id)
                ->count();
            return $dbCount === count($resourceIds);
        } else {
            return false;
        }
    }

    protected function hasPermission(User $currentUser, string $permission)
    {
        if ($this->activeWorkspace->personal() && $currentUser->hasPermission($permission)) {
            return true;
        } else if ( ! $this->activeWorkspace->personal()) {
            if ($currentUser === $this->activeWorkspace->workspace()->owner_id) {
                return true;
            } else {
                $workspaceUser = $this->activeWorkspace->member($currentUser->id);
                return $workspaceUser->hasPermission($permission);
            }
        } else {
            return false;
        }
    }
}
