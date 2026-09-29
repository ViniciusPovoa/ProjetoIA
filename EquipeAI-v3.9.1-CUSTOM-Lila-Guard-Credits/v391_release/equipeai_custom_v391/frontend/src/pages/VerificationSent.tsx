import {useEffect,useState} from 'react';
import {Link} from 'react-router-dom';
import {FiArrowLeft,FiCheckCircle,FiMail,FiRefreshCw} from 'react-icons/fi';
import {useAuth} from '../AuthContext';
import Brand from '../components/Brand';

export default function VerificationSent(){
  const{resendVerification}=useAuth();const[email,setEmail]=useState(()=>sessionStorage.getItem('pending_verification_email')||'');const[masked]=useState(()=>sessionStorage.getItem('pending_verification_masked')||'seu e-mail');const[sent]=useState(()=>sessionStorage.getItem('pending_verification_sent')!=='0');const[cooldown,setCooldown]=useState(0);const[msg,setMsg]=useState(sent?'Link de confirmação enviado.':'Sua conta foi criada, mas o envio ainda não foi confirmado pelo servidor de e-mail.');const[busy,setBusy]=useState(false);
  useEffect(()=>{if(cooldown<=0)return;const t=setInterval(()=>setCooldown(c=>Math.max(0,c-1)),1000);return()=>clearInterval(t)},[cooldown]);
  const resend=async()=>{if(!email||cooldown||busy)return;setBusy(true);try{setMsg(await resendVerification(email));setCooldown(60)}catch(x:any){setMsg(x.response?.data?.error||'Não foi possível reenviar agora.')}finally{setBusy(false)}};
  return <div className="verification-page"><header><Brand/></header><main className="verification-card"><div className="verification-icon"><FiMail/></div><span className="auth-step">02 / 03 · VERIFICAÇÃO</span><h1>Confira sua caixa de entrada</h1><p>Enviamos um link de confirmação para <strong>{masked}</strong>. Abra a mensagem e confirme o endereço para liberar o login.</p><div className="verification-status"><FiCheckCircle/><span>{msg}</span></div><div className="verification-help"><span>Não recebeu?</span>{!email?<div className="field"><label>E-mail do cadastro</label><input type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="voce@empresa.com.br"/></div>:null}<button className="btn-ghost" onClick={resend} disabled={!email||!!cooldown||busy}><FiRefreshCw/>{busy?'Reenviando…':cooldown?`Reenviar em ${cooldown}s`:'Reenviar e-mail'}</button></div><Link className="verification-back" to="/login"><FiArrowLeft/> Voltar para o login</Link></main></div>
}
