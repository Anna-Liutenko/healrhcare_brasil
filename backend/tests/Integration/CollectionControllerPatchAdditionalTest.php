<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CollectionControllerPatchAdditionalTest extends TestCase
{
    public function testUnauthorizedReturns401(): void
    {
        $sessionRepo = new class { public function isValid($t){ return false; } public function findByToken($t){ return null; } };
        $userRepo = new class { public function findById($id){ return null; } };
        $pageRepo = new class { public function findById($id){ return null; } };
        $usecase = null;

        $controller = new Presentation\Controller\CollectionController($pageRepo, null, $sessionRepo, $userRepo, $usecase);

        $_SERVER['HTTP_AUTHORIZATION'] = '';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Missing Authorization header|Invalid or expired token/');

        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('authenticate');
        $method->setAccessible(true);
        $method->invoke($controller);
    }

    public function testForbiddenRoleReturns403(): void
    {
        $sessionRepo = new class { public function isValid($t){ return true; } public function findByToken($t){ return ['user_id' => 'u']; } };
        $userRepo = new class { public function findById($id){ return new class { public function getRole(){ return (object)['value'=>'viewer']; } }; } };
        $pageRepo = new class { public function findById($id){ return null; } };

        $controller = new Presentation\Controller\CollectionController($pageRepo, null, $sessionRepo, $userRepo, null);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tok';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Forbidden|User not found/');

        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('authorize');
        $method->setAccessible(true);

        $method->invoke($controller, 'u');
    }
}
