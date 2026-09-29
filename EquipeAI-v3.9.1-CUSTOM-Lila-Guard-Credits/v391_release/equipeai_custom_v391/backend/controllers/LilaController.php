<?php
namespace App\controllers;

use App\services\LilaService;
use App\utils\Request;

final class LilaController {
    public function history(array $user): array {
        return (new LilaService())->history($user);
    }

    public function chat(array $user): array {
        return (new LilaService())->chat($user, Request::json());
    }

    public function newConversation(array $user): array {
        return (new LilaService())->newConversation($user);
    }
}
