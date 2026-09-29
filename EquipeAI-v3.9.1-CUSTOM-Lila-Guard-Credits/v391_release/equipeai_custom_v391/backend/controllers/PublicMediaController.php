<?php
namespace App\controllers;
use App\services\GeneratedMediaService;
final class PublicMediaController {public function show(string $token):never{(new GeneratedMediaService())->stream($token);}}
