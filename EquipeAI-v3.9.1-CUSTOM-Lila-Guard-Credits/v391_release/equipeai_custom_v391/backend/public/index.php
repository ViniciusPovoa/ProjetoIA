<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
use App\controllers\{AdminController,AgentController,AuthController,BillingController,CompanyController,DashboardController,FileController,HistoryController,ProposalController,PublicProposalController,InstagramController,PublicMediaController,WhatsAppIntegrationController,LilaController};
use App\middleware\{AuthMiddleware,CsrfMiddleware,RateLimitMiddleware,SecurityMiddleware};
use App\utils\{ApiException,Logger,Request,Response,Router,SystemException};

SecurityMiddleware::cors();
if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}
$method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$path=preg_replace('#^/api#','',$path)?:'/';
$router=new Router();
$auth=new AuthController();$dash=new DashboardController();$agents=new AgentController();$company=new CompanyController();$history=new HistoryController();$files=new FileController();$billing=new BillingController();$adminCtrl=new AdminController();$proposals=new ProposalController();$publicProposal=new PublicProposalController();$instagram=new InstagramController();$publicMedia=new PublicMediaController();$whatsappIntegration=new WhatsAppIntegrationController();$lila=new LilaController();
$secure=function(callable $fn,bool $csrf=false){return function(array $p=[])use($fn,$csrf){$u=AuthMiddleware::user(true);if($csrf)CsrfMiddleware::verify($u);return $fn($u,$p);};};
$admin=function(callable $fn,bool $csrf=false){return function(array $p=[])use($fn,$csrf){$u=AuthMiddleware::admin();if($csrf)CsrfMiddleware::verify($u);return $fn($u,$p);};};

$router->get('/health',fn()=>['ok'=>true,'time'=>date('c'),'version'=>'3.9.1-custom']);
$router->post('/auth/register',function()use($auth){RateLimitMiddleware::check('register',8,300);return $auth->register();});
$router->post('/auth/login',function()use($auth){RateLimitMiddleware::check('login',10,300);return $auth->login();});
$router->post('/auth/verify-email',function()use($auth){RateLimitMiddleware::check('verify_email',20,300);return $auth->verify();});
$router->post('/auth/resend-verification',function()use($auth){RateLimitMiddleware::check('resend_verification',3,600);return $auth->resend();});
$router->post('/auth/forgot-password',function()use($auth){RateLimitMiddleware::check('forgot',5,600);return $auth->forgot();});
$router->post('/auth/reset-password',fn()=>$auth->reset());
$router->get('/me',fn()=>$auth->me());
$router->get('/auth/csrf',fn()=>$auth->csrf());
$router->post('/auth/logout',$secure(fn($u)=>$auth->logout($u),true));
$router->post('/auth/change-password',$secure(fn($u)=>$auth->change($u),true));
$router->delete('/account',$secure(fn($u)=>$auth->delete($u),true));

$router->get('/dashboard',$secure(fn($u)=>$dash->show($u)));
$router->get('/assistant/lila',$secure(fn($u)=>$lila->history($u)));
$router->post('/assistant/lila/chat',$secure(function($u)use($lila){RateLimitMiddleware::check('lila_chat',30,300,(int)$u['id']);return $lila->chat($u);},true));
$router->post('/assistant/lila/new',$secure(function($u)use($lila){RateLimitMiddleware::check('lila_new',10,300,(int)$u['id']);return $lila->newConversation($u);},true));
$router->get('/agents',$secure(fn($u)=>$agents->index()));
$router->get('/agents/{id}',$secure(fn($u,$p)=>$agents->show((int)$p['id'])));
$router->post('/agents/{id}/execute',$secure(function($u,$p)use($agents){RateLimitMiddleware::check('ai_execute',20,60,(int)$u['id']);return $agents->execute($u,(int)$p['id']);},true));
$router->post('/agents/{id}/generate-image',$secure(function($u,$p)use($agents){RateLimitMiddleware::check('image_generate',8,300,(int)$u['id']);return $agents->generateImage($u,(int)$p['id']);},true));


$router->get('/proposals/templates',$secure(fn($u)=>$proposals->templates()));
$router->post('/proposals',$secure(function($u)use($proposals){RateLimitMiddleware::check('proposal_generate',20,300,(int)$u['id']);return $proposals->create($u);},true));
$router->post('/proposals/{id}/email',$secure(function($u,$p)use($proposals){RateLimitMiddleware::check('proposal_email',12,3600,(int)$u['id']);return $proposals->email($u,(int)$p['id']);},true));
$router->post('/proposals/{id}/whatsapp',$secure(function($u,$p)use($proposals){RateLimitMiddleware::check('proposal_whatsapp',12,3600,(int)$u['id']);return $proposals->whatsapp($u,(int)$p['id']);},true));
$router->get('/public-proposals/{token}',fn($p)=>$publicProposal->show((string)$p['token']));

$router->get('/integrations/whatsapp',$secure(fn($u)=>$whatsappIntegration->status($u)));
$router->post('/integrations/whatsapp/complete',$secure(function($u)use($whatsappIntegration){RateLimitMiddleware::check('whatsapp_connect',8,600,(int)$u['id']);return $whatsappIntegration->complete($u);},true));
$router->post('/integrations/whatsapp/register',$secure(function($u)use($whatsappIntegration){RateLimitMiddleware::check('whatsapp_register',5,600,(int)$u['id']);return $whatsappIntegration->register($u);},true));
$router->delete('/integrations/whatsapp',$secure(fn($u)=>$whatsappIntegration->disconnect($u),true));

$router->get('/integrations/instagram',$secure(fn($u)=>$instagram->status($u)));
$router->post('/integrations/instagram/connect',$secure(fn($u)=>$instagram->connect($u),true));
$router->get('/integrations/instagram/callback',fn()=>$instagram->callback());
$router->delete('/integrations/instagram',$secure(fn($u)=>$instagram->disconnect($u),true));
$router->get('/integrations/instagram/content-publishing-limit',$secure(function($u)use($instagram){RateLimitMiddleware::check('instagram_publish_limit',20,3600,(int)$u['id']);return $instagram->contentPublishingLimit($u);}));
$router->post('/integrations/instagram/test-media',$secure(function($u)use($instagram){RateLimitMiddleware::check('instagram_test_media',20,3600,(int)$u['id']);return $instagram->testMedia($u);},true));
$router->post('/integrations/instagram/publish',$secure(function($u)use($instagram){RateLimitMiddleware::check('instagram_publish',12,3600,(int)$u['id']);return $instagram->publish($u);},true));
$router->get('/public-media/{token}',fn($p)=>$publicMedia->show((string)$p['token']));

$router->get('/company',$secure(fn($u)=>$company->show($u)));
$router->put('/company',$secure(fn($u)=>$company->update($u),true));
$router->post('/company/context/improve',$secure(function($u)use($company){RateLimitMiddleware::check('context_improve',8,300,(int)$u['id']);return $company->improveContext($u);},true));
$router->get('/company/services',$secure(fn($u)=>$company->services($u)));
$router->post('/company/services',$secure(fn($u)=>$company->createService($u),true));
$router->put('/company/services/{id}',$secure(fn($u,$p)=>$company->updateService($u,(int)$p['id']),true));
$router->delete('/company/services/{id}',$secure(fn($u,$p)=>$company->deleteService($u,(int)$p['id']),true));

$router->get('/history',$secure(fn($u)=>$history->index($u)));
$router->get('/history/{id}',$secure(fn($u,$p)=>$history->show($u,(int)$p['id'])));
$router->patch('/history/{id}',$secure(fn($u,$p)=>$history->favorite($u,(int)$p['id']),true));
$router->delete('/history/{id}',$secure(fn($u,$p)=>$history->delete($u,(int)$p['id']),true));

$router->post('/files/upload',$secure(function($u)use($files){RateLimitMiddleware::check('upload',10,300,(int)$u['id']);return $files->upload($u);},true));
$router->get('/files',$secure(fn($u)=>$files->index($u)));
$router->get('/files/{id}/content',$secure(fn($u,$p)=>$files->content($u,(int)$p['id'])));
$router->get('/files/{id}/download',$secure(fn($u,$p)=>$files->download($u,(int)$p['id'])));
$router->delete('/files/{id}',$secure(fn($u,$p)=>$files->delete($u,(int)$p['id']),true));

$router->get('/credits',$secure(fn($u)=>$billing->credits($u)));
$router->get('/transactions',$secure(fn($u)=>$billing->transactions($u)));
$router->get('/plans',fn()=>$billing->plans());
$router->get('/subscription',$secure(fn($u)=>$billing->status($u)));
$router->post('/subscriptions',$secure(fn($u)=>$billing->create($u),true));
$router->post('/subscriptions/cancel',$secure(fn($u)=>$billing->cancel($u),true));
$router->post('/payments/create',$secure(fn($u)=>$billing->create($u),true));
$router->post('/webhooks/mercadopago',fn()=>$billing->webhook());

$router->get('/admin/dashboard',$admin(fn($u)=>$adminCtrl->dashboard()));
$router->get('/admin/users',$admin(fn($u)=>$adminCtrl->users()));
$router->patch('/admin/users/{id}',$admin(fn($u,$p)=>$adminCtrl->updateUser($u,(int)$p['id']),true));
$router->post('/admin/users/{id}/credits',$admin(fn($u,$p)=>$adminCtrl->adjustCredits($u,(int)$p['id']),true));
$router->delete('/admin/users/{id}',$admin(fn($u,$p)=>$adminCtrl->deleteUser($u,(int)$p['id']),true));
$router->get('/admin/agents',$admin(fn($u)=>$adminCtrl->agents()));
$router->post('/admin/agents',$admin(fn($u)=>$adminCtrl->createAgent($u),true));
$router->patch('/admin/agents/{id}',$admin(fn($u,$p)=>$adminCtrl->updateAgent($u,(int)$p['id']),true));
$router->delete('/admin/agents/{id}',$admin(fn($u,$p)=>$adminCtrl->deleteAgent($u,(int)$p['id']),true));
$router->get('/admin/providers',$admin(fn($u)=>$adminCtrl->providers()));
$router->patch('/admin/providers/{id}',$admin(fn($u,$p)=>$adminCtrl->updateProvider($u,(int)$p['id']),true));
$router->post('/admin/models',$admin(fn($u)=>$adminCtrl->createModel($u),true));
$router->patch('/admin/models/{id}',$admin(fn($u,$p)=>$adminCtrl->updateModel($u,(int)$p['id']),true));
$router->get('/admin/plans',$admin(fn($u)=>$adminCtrl->plans()));
$router->post('/admin/plans',$admin(fn($u)=>$adminCtrl->createPlan($u),true));
$router->patch('/admin/plans/{id}',$admin(fn($u,$p)=>$adminCtrl->updatePlan($u,(int)$p['id']),true));
$router->get('/admin/finance',$admin(fn($u)=>$adminCtrl->finance()));
$router->post('/admin/costs',$admin(fn($u)=>$adminCtrl->addCost($u),true));
$router->delete('/admin/costs/{id}',$admin(fn($u,$p)=>$adminCtrl->deleteCost($u,(int)$p['id']),true));
$router->get('/admin/logs',$admin(fn($u)=>$adminCtrl->logs()));

try{$router->dispatch($method,$path);}catch(\Throwable $e){
    $status=$e instanceof ApiException?$e->status:($e instanceof SystemException?500:($e instanceof \RuntimeException?422:500));
    Logger::write('errors','api_exception',['endpoint'=>$path,'method'=>$method,'error'=>$e->getMessage(),'status'=>$status,'trace'=>env_bool('APP_DEBUG')?$e->getTraceAsString():null]);
    if($status===500)SecurityMiddleware::log('server_error',AuthMiddleware::user(false)['id']??null,['message'=>$e->getMessage()]);
    if($e instanceof ApiException){$payload=['error'=>$e->getMessage()];if($e->errorCode)$payload['error_code']=$e->errorCode;if($e->details)$payload=array_merge($payload,$e->details);Response::json($payload,$status);}
    Response::json(['error'=>$status===500?'Erro interno. Tente novamente.':$e->getMessage()],$status);
}
