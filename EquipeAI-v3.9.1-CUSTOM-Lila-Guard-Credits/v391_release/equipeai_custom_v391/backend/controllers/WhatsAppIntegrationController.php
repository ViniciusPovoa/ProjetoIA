<?php
namespace App\controllers;

use App\services\WhatsAppIntegrationService;
use App\utils\Request;

final class WhatsAppIntegrationController {
    public function status(array $u):array{return (new WhatsAppIntegrationService())->status((int)$u['company_id']);}
    public function complete(array $u):array{return (new WhatsAppIntegrationService())->complete($u,Request::json());}
    public function register(array $u):array{$d=Request::json();return (new WhatsAppIntegrationService())->register($u,(string)($d['pin']??''));}
    public function disconnect(array $u):array{(new WhatsAppIntegrationService())->disconnect((int)$u['company_id']);return ['message'=>'WhatsApp desconectado.'];}
}
