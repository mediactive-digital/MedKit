<?php
namespace App\Models;
use MediactiveDigital\MedKit\Models\Role as MedKitRole;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;


class Role extends MedKitRole {

    use Cachable;

    const SUPER_ADMIN_ID = 1;
    const ADMIN_ID = 2;

    const SUPER_ADMIN = 'Super admin';
    const ADMIN = 'Admin';
    const ROLES_ADMIN = [ self::SUPER_ADMIN, self::ADMIN ]; //roles étant considérés comme admin

    protected $cacheCooldownSeconds = 86400; // un jour
}