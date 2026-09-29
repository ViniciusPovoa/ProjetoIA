import {FormEvent,useMemo,useState} from 'react';
import {Link,useNavigate} from 'react-router-dom';
import {FiArrowRight,FiBriefcase,FiCheck,FiCheckCircle,FiEye,FiEyeOff,FiLock,FiMail,FiShield,FiUser} from 'react-icons/fi';
import {useAuth} from '../AuthContext';
import Brand from '../components/Brand';

type Errors=Partial<Record<'name'|'company'|'email'|'password'|'confirm'|'terms',string>>;
const nameOk=(v:string)=>v.trim().length>=3&&v.trim().length<=120&&/^[\p{L}][\p{L}\p{M} .'-]+$/u.test(v.trim());
const companyOk=(v:string)=>v.trim().length>=2&&v.trim().length<=150&&!/[<>]/.test(v);
const emailOk=(v:string)=>/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(v.trim())&&v.length<=190;
const passwordChecks=(v:string)=>[
  ['10 caracteres ou mais',v.length>=10],['uma letra maiúscula',/[A-Z]/.test(v)],['uma letra minúscula',/[a-z]/.test(v)],['um número',/\d/.test(v)],['um símbolo',/[^A-Za-z0-9]/.test(v)]
] as const;

export default function Register(){
  const{register}=useAuth();const nav=useNavigate();
  const[name,setName]=useState('');const[company,setCompany]=useState('');const[email,setEmail]=useState('');const[password,setPassword]=useState('');const[confirm,setConfirm]=useState('');const[agree,setAgree]=useState(false);
  const[showPassword,setShowPassword]=useState(false);const[busy,setBusy]=useState(false);const[error,setError]=useState('');const[errors,setErrors]=useState<Errors>({});const[touched,setTouched]=useState<Record<string,boolean>>({});
  const checks=useMemo(()=>passwordChecks(password),[password]);const strength=checks.filter(([,ok])=>ok).length;
  const validate=()=>{const e:Errors={};if(!nameOk(name))e.name='Informe seu nome completo.';if(!companyOk(company))e.company='Informe o nome da sua empresa.';if(!emailOk(email))e.email='Digite um e-mail válido.';if(strength<5)e.password='A senha ainda não atende aos requisitos.';if(confirm!==password||!confirm)e.confirm='As senhas precisam ser iguais.';if(!agree)e.terms='Aceite os termos para continuar.';setErrors(e);return Object.keys(e).length===0};
  const blur=(key:string)=>setTouched(x=>({...x,[key]:true}));
  const submit=async(e:FormEvent)=>{e.preventDefault();setError('');setTouched({name:true,company:true,email:true,password:true,confirm:true,terms:true});if(!validate())return;setBusy(true);try{const r=await register(name.trim(),email.trim().toLowerCase(),password,company.trim(),agree);sessionStorage.setItem('pending_verification_email',email.trim().toLowerCase());sessionStorage.setItem('pending_verification_masked',r.email);sessionStorage.setItem('pending_verification_sent',r.email_sent?'1':'0');nav('/verificacao-enviada')}catch(x:any){const serverFields=x.response?.data?.fields||{};setErrors(s=>({...s,name:serverFields.name||s.name,company:serverFields.company_name||s.company,email:serverFields.email||s.email,password:serverFields.password||s.password,terms:serverFields.terms_accepted||s.terms}));setError(x.response?.data?.error||'Não foi possível concluir o cadastro.')}finally{setBusy(false)}};
  const fieldClass=(key:keyof Errors)=>touched[key]&&errors[key]?'field invalid':'field';
  return <div className="auth-page auth-page-pro">
    <aside className="auth-side auth-side-pro">
      <Brand inverse/>
      <div className="auth-side-content"><span className="auth-index">01 / 03</span><h2>Crie seu acesso.<br/>O contexto vem depois.</h2><p>Seu workspace nasce com isolamento por empresa, créditos controlados e confirmação de e-mail antes do primeiro login.</p><div className="auth-proof-list"><span><FiShield/> Verificação obrigatória de e-mail</span><span><FiCheckCircle/> Dados separados por empresa</span><span><FiLock/> Senha protegida com hash seguro</span></div></div>
      <div className="auth-side-foot"><span className="system-dot"/> ambiente protegido <i/> sem cartão no plano inicial</div>
    </aside>
    <main className="auth-form-stage"><form className="auth-card auth-card-pro" onSubmit={submit} noValidate>
      <div className="auth-form-head"><span className="auth-step">CRIAR WORKSPACE</span><h1>Dados de acesso</h1><p>Preencha os dados abaixo. Enviaremos um link para confirmar seu e-mail.</p></div>
      {error&&<div className="alert-box error" role="alert">{error}</div>}
      <div className="form-two">
        <div className={fieldClass('name')}><label htmlFor="name">Nome completo</label><div className="input-shell"><FiUser/><input id="name" value={name} onChange={e=>setName(e.target.value)} onBlur={()=>blur('name')} maxLength={120} autoComplete="name" placeholder="Ex.: Vinicius Póvoa" aria-invalid={!!errors.name}/></div>{touched.name&&errors.name&&<small className="field-error">{errors.name}</small>}</div>
        <div className={fieldClass('company')}><label htmlFor="company">Empresa</label><div className="input-shell"><FiBriefcase/><input id="company" value={company} onChange={e=>setCompany(e.target.value)} onBlur={()=>blur('company')} maxLength={150} autoComplete="organization" placeholder="Nome comercial" aria-invalid={!!errors.company}/></div>{touched.company&&errors.company&&<small className="field-error">{errors.company}</small>}</div>
      </div>
      <div className={fieldClass('email')}><label htmlFor="email">E-mail</label><div className="input-shell"><FiMail/><input id="email" type="email" value={email} onChange={e=>setEmail(e.target.value)} onBlur={()=>blur('email')} maxLength={190} autoComplete="email" inputMode="email" placeholder="voce@empresa.com.br" aria-invalid={!!errors.email}/></div>{touched.email&&errors.email?<small className="field-error">{errors.email}</small>:<small className="field-help">Usaremos este endereço para confirmar a conta e recuperar o acesso.</small>}</div>
      <div className="form-two password-row">
        <div className={fieldClass('password')}><label htmlFor="password">Senha</label><div className="input-shell"><FiLock/><input id="password" type={showPassword?'text':'password'} value={password} onChange={e=>setPassword(e.target.value)} onBlur={()=>blur('password')} maxLength={128} autoComplete="new-password" aria-invalid={!!errors.password}/><button type="button" className="input-action" onClick={()=>setShowPassword(v=>!v)} aria-label={showPassword?'Ocultar senha':'Mostrar senha'}>{showPassword?<FiEyeOff/>:<FiEye/>}</button></div>{touched.password&&errors.password&&<small className="field-error">{errors.password}</small>}</div>
        <div className={fieldClass('confirm')}><label htmlFor="confirm">Confirmar senha</label><div className="input-shell"><FiLock/><input id="confirm" type={showPassword?'text':'password'} value={confirm} onChange={e=>setConfirm(e.target.value)} onBlur={()=>blur('confirm')} maxLength={128} autoComplete="new-password" aria-invalid={!!errors.confirm}/></div>{touched.confirm&&errors.confirm&&<small className="field-error">{errors.confirm}</small>}</div>
      </div>
      <div className="password-quality" aria-label="Requisitos da senha"><div className="password-meter"><span style={{width:`${strength*20}%`}}/></div><div className="password-rules">{checks.map(([label,ok])=><span className={ok?'ok':''} key={label}><FiCheck/> {label}</span>)}</div></div>
      <label className={`check legal-check ${touched.terms&&errors.terms?'invalid':''}`}><input type="checkbox" checked={agree} onChange={e=>setAgree(e.target.checked)} onBlur={()=>blur('terms')}/><span>Li e concordo com os <a href="/termos" target="_blank" rel="noreferrer">Termos de Uso</a> e a <a href="/privacidade" target="_blank" rel="noreferrer">Política de Privacidade</a>.</span></label>{touched.terms&&errors.terms&&<small className="field-error legal-error">{errors.terms}</small>}
      <button className="btn-primary-custom big w-100" disabled={busy}>{busy?'Criando acesso…':<>Continuar para verificação <FiArrowRight/></>}</button>
      <div className="auth-bottom">Já possui uma conta? <Link to="/login">Entrar</Link></div>
    </form></main>
  </div>
}
