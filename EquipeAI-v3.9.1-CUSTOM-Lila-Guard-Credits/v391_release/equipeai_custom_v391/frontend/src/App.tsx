import {lazy,Suspense} from 'react';
import {BrowserRouter,Routes,Route,Navigate} from 'react-router-dom';
import {AuthProvider,useAuth} from './AuthContext';
import AppLayout from './components/AppLayout';

const Landing=lazy(()=>import('./pages/Landing'));
const Login=lazy(()=>import('./pages/Login'));
const Register=lazy(()=>import('./pages/Register'));
const ForgotPassword=lazy(()=>import('./pages/ForgotPassword'));
const ResetPassword=lazy(()=>import('./pages/ResetPassword'));
const VerifyEmail=lazy(()=>import('./pages/VerifyEmail'));
const VerificationSent=lazy(()=>import('./pages/VerificationSent'));
const Legal=lazy(()=>import('./pages/Legal'));
const Dashboard=lazy(()=>import('./pages/Dashboard'));
const Agents=lazy(()=>import('./pages/Agents'));
const AgentRunner=lazy(()=>import('./pages/AgentRunner'));
const Company=lazy(()=>import('./pages/Company'));
const History=lazy(()=>import('./pages/History'));
const FilesPage=lazy(()=>import('./pages/FilesPage'));
const Credits=lazy(()=>import('./pages/Credits'));
const Subscription=lazy(()=>import('./pages/Subscription'));
const Settings=lazy(()=>import('./pages/Settings'));
const Admin=lazy(()=>import('./pages/Admin'));

function Protected(){
  const{user,loading}=useAuth();
  if(loading)return <div className="screen-center">Carregando…</div>;
  return user?<AppLayout/>:<Navigate to="/login" replace/>;
}

function Root(){
  return <Suspense fallback={<div className="screen-center">Carregando…</div>}>
    <Routes>
      <Route path="/" element={<Landing/>}/>
      <Route path="/login" element={<Login/>}/>
      <Route path="/cadastro" element={<Register/>}/>
      <Route path="/recuperar-senha" element={<ForgotPassword/>}/>
      <Route path="/redefinir-senha" element={<ResetPassword/>}/>
      <Route path="/verificar-email" element={<VerifyEmail/>}/>
      <Route path="/verificacao-enviada" element={<VerificationSent/>}/>
      <Route path="/termos" element={<Legal/>}/>
      <Route path="/privacidade" element={<Legal/>}/>
      <Route path="/app" element={<Protected/>}>
        <Route index element={<Dashboard/>}/>
        <Route path="agentes" element={<Agents/>}/>
        <Route path="agentes/:id" element={<AgentRunner/>}/>
        <Route path="empresa" element={<Company/>}/>
        <Route path="historico" element={<History/>}/>
        <Route path="arquivos" element={<FilesPage/>}/>
        <Route path="creditos" element={<Credits/>}/>
        <Route path="assinatura" element={<Subscription/>}/>
        <Route path="configuracoes" element={<Settings/>}/>
        <Route path="admin" element={<Admin/>}/>
      </Route>
      <Route path="*" element={<Navigate to="/"/>}/>
    </Routes>
  </Suspense>;
}

export default function App(){return <BrowserRouter><AuthProvider><Root/></AuthProvider></BrowserRouter>}
