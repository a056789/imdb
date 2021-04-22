<?php

namespace Common\Workspaces\Controllers;

use Auth;
use Common\Core\BaseController;
use Common\Database\Paginator;
use Common\Workspaces\Actions\CrupdateWorkspace;
use Common\Workspaces\Requests\CrupdateWorkspaceRequest;
use Common\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkspaceController extends BaseController
{
    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param Workspace $workspace
     * @param Request $request
     */
    public function __construct(Workspace $workspace, Request $request)
    {
        $this->workspace = $workspace;
        $this->request = $request;
    }

    /**
     * @return Response
     */
    public function index()
    {
        $userId = $this->request->get('userId');
        $this->authorize('index', [Workspace::class, $userId]);

        $paginator = new Paginator($this->workspace, $this->request->all());
        $paginator->withCount('members');
        $paginator->with(['members' => function(HasMany $builder) {
            $builder->where(function(Builder $builder) {
                $builder->where('workspace_user.user_id', Auth::id())
                    ->orWhere('workspace_user.is_owner', true);
            });
        }]);

        if ($userId = $paginator->param('userId')) {
            $paginator->query()
                ->where('owner_id', $userId)
                ->orWhereHas('members', function(Builder $builder) use($userId) {
                    return $builder->where('workspace_user.user_id', $userId);
                });
        }

        $pagination = $paginator->paginate();

        $pagination->transform(function(Workspace $workspace) {
            $workspace->setRelation('owner', $workspace->members->where('is_owner', true)->first());
            $workspace->currentUser = $workspace->members->where('id', Auth::id())->first();
            $workspace->unsetRelation('members');
            return $workspace;
        });

        return $this->success(['pagination' => $pagination]);
    }

    /**
     * @param Workspace $workspace
     * @return Response
     */
    public function show(Workspace $workspace)
    {
        $this->authorize('show', $workspace);

        $workspace->load(['invites', 'members']);

        if ($workspace->currentUser = $workspace->members->where('id', Auth::id())->first()) {
            $workspace->currentUser->load('permissions');
        }

        return $this->success(['workspace' => $workspace]);
    }

    /**
     * @param CrupdateWorkspaceRequest $request
     * @return Response
     */
    public function store(CrupdateWorkspaceRequest $request)
    {
        $this->authorize('store', Workspace::class);

        $workspace = app(CrupdateWorkspace::class)->execute($request->all());

        return $this->success(['workspace' => $workspace]);
    }

    /**
     * @param Workspace $workspace
     * @param CrupdateWorkspaceRequest $request
     * @return Response
     */
    public function update(Workspace $workspace, CrupdateWorkspaceRequest $request)
    {
        $this->authorize('store', $workspace);

        $workspace = app(CrupdateWorkspace::class)->execute($request->all(), $workspace);

        return $this->success(['workspace' => $workspace]);
    }

    /**
     * @param string $ids
     * @return Response
     */
    public function destroy($ids)
    {
        $workspaceIds = explode(',', $ids);
        $this->authorize('destroy', [Workspace::class, $workspaceIds]);

        app(DeleteWorkspaces::class)->execute($workspaceIds);

        return $this->success();
    }
}
