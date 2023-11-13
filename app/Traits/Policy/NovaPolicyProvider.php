<?php

namespace App\Traits\Policy;

use \Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

trait NovaPolicyProvider
{
    use Macroable;

    /**
     * Policy Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->register();
    }

    /**
     * Determine whether the user can view any resources or not?
     *
     * @param \App\Models\User $user
     * @return mixed
     * @throws \ReflectionException
     */
    public function viewAny(User $user)
    {
        return $user->hasPermissionTo($this->getPermission('view'))
            || $this->thereIsLimitedPermissionTo('viewNurse', $user);
    }

    /**
     * Register Macro Provider.
     *
     * @return void
     */
    protected function register()
    {
        $roleNames = Role::query()->pluck('name')->all();
        foreach ($this->policies() as $policy) {
            $limitedPolicy = false;
            $originalPolicy = $policy;
            foreach ($roleNames as $roleName) {
                if (preg_match("/.+$roleName$/", $policy)) {
                    $policy = preg_replace("/$roleName$/", '', $originalPolicy);
                    $limitedPolicy = true;
                    break;
                }
            }
            static::macro($policy, function (...$params) use ($policy, $originalPolicy, $limitedPolicy) {
                /** @var User $user */
                $user = $params[0];
                if ($limitedPolicy) {
                    /** @var Model|null $model */
                    $model = isset($params[1]) ? $params[1] : null;
                    if (!$user->checkPermissionTo($this->getPermission($policy))) {
                        return $this->thereIsLimitedPermissionTo($originalPolicy, $user, $model);
                    }
                }
                return $user->hasPermissionTo($this->getPermission($policy));
            });
        }
    }

    /**
     * The policies that we want to bootstrap.
     *
     * @return array
     */
    abstract protected function policies();

    /**
     * Get Permission name base on Policy Class.
     *
     * @param string $method
     * @return string
     */
    protected function getPermission(string $method)
    {
        return collect(func_get_args())
            ->values()
            ->prepend($this->getModel())
            ->map(function ($name) {
                $name = str_replace('-', ' ', Str::kebab($name));

                return ucwords($name);
            })
            ->implode(' ');
    }

    /**
     * Get Model name for the policy which is running.
     *
     * @return string
     */
    protected function getModel()
    {
        return Str::beforeLast(Str::afterLast(__CLASS__, '\\'), 'Policy');
    }

    /**
     * Get limited permission for current user.
     *
     * @param string $policy
     * @param User $user
     * @param Model|null $model
     * @return bool
     * @throws \ReflectionException
     */
    protected function thereIsLimitedPermissionTo(string $policy, User $user, ?Model $model = null)
    {
        $permission = $this->getPermission($policy);
        $permissionParts = explode(' ', $permission);
        $method = 'is' . last($permissionParts);
        $hasPermission = call_user_func([$user, $method]) && $user->checkPermissionTo($permission);
        if (!$model) {
            return $hasPermission;
        }
        if (!$hasPermission) {
            return false;
        }
        $className = $this->getModel();
        $classPath = '\\App\\Models\\' . $className;
        if ($className == 'User') {
            return $user->id == $model->id;
        }
        $reflectionClass = new \ReflectionClass($classPath);
        $relation = strtolower(last($permissionParts));
        if ($reflectionClass->hasMethod($relation)) {
            return $model->$relation && $user->id == $model->$relation->id;
        }
        return false;
    }

    protected function getActionRoles(string $action): array
    {
        $roleNames = Role::query()->pluck('name')->all();
        $actionRoles = [];
        foreach ($roleNames as $roleName) {
            $actionRoles[] = "{$action}{$roleName}";
        }
        return $actionRoles;
    }

    protected function hasLimitedAccessByRole(string $action, User $user): bool
    {
        $actionRoles = $this->getActionRoles($action);
        foreach ($actionRoles as $actionRole) {
            if ($this->hasLimitedAccessByRole($actionRole, $user)) {
                return true;
            }
        }
        return false;
    }
}
