<?php

namespace App\Traits\Policy;

use App\Models\User;

trait HandlesBeforePolicyGate
{
    /**
     * For certain users, you may wish to authorize all actions within a given policy.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ability
     * @return mixed
     */
    public function before(User $user, $ability)
    {
        $bypassAbilities = property_exists($this, 'bypassAbilities')
            ? $this->bypassAbilities
            : [];

        if ($user->isSuperAdmin() && ! in_array($ability, $bypassAbilities)) {
            return true;
        }
    }
}
