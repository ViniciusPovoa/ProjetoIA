<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/config/bootstrap.php';
use App\services\MaintenanceService;
try{$result=(new MaintenanceService())->run();echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
