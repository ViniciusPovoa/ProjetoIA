<?php
namespace App\controllers;

use App\services\ProposalService;

final class PublicProposalController {
    public function show(string $token):never{
        try{$svc=new ProposalService();$proposal=$svc->publicByToken($token);$path=$svc->absolutePath($proposal);if(!is_file($path))throw new \RuntimeException('Arquivo não encontrado.');$download=($_GET['download']??'')==='1';header('Content-Type: application/pdf');header('Content-Length: '.filesize($path));header('Cache-Control: private, max-age=600');header('X-Content-Type-Options: nosniff');header('Content-Disposition: '.($download?'attachment':'inline').'; filename="'.preg_replace('/[^a-zA-Z0-9._-]/','-',(string)$proposal['filename']).'"');readfile($path);exit;}
        catch(\Throwable){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Proposta não encontrada ou expirada.';exit;}
    }
}
