import {useEffect,useMemo,useState} from 'react';
import {api} from '../api';
import {launchWhatsAppEmbeddedSignup} from '../utils/metaWhatsapp';
import {FiCheck,FiInstagram,FiLink2,FiMessageCircle,FiShield} from 'react-icons/fi';

type InstagramStatus={configured:boolean;connected:boolean;account?:{username?:string;account_type?:string}|null};
type WhatsAppStatus={configured:boolean;connected:boolean;account?:{display_phone_number?:string;verified_name?:string;registration_status?:string}|null;sdk?:{app_id:string;config_id:string;graph_version:string;feature_type?:string;embedded_signup_version?:string}};

export default function MetaIntegrations(){
  const[instagram,setInstagram]=useState<InstagramStatus>({configured:false,connected:false});
  const[whatsapp,setWhatsapp]=useState<WhatsAppStatus>({configured:false,connected:false});
  const[busy,setBusy]=useState('');
  const[msg,setMsg]=useState('');
  const[error,setError]=useState('');
  const[pin,setPin]=useState('');
  const apiOrigin=useMemo(()=>{try{return new URL(String(api.defaults.baseURL||'/api'),window.location.origin).origin;}catch{return window.location.origin;}},[]);

  const load=async()=>{
    const [ig,wa]=await Promise.allSettled([api.get('/integrations/instagram'),api.get('/integrations/whatsapp')]);
    if(ig.status==='fulfilled')setInstagram(ig.value.data as InstagramStatus);
    if(wa.status==='fulfilled')setWhatsapp(wa.value.data as WhatsAppStatus);
  };
  useEffect(()=>{void load();},[]);
  useEffect(()=>{
    const handler=(event:MessageEvent)=>{if(event.origin!==apiOrigin)return;if(event.data?.type==='equipeai:instagram-oauth'){if(event.data?.ok){setMsg('Instagram conectado.');void load();}else setError(event.data?.message||'Não foi possível conectar o Instagram.');}};
    window.addEventListener('message',handler);return()=>window.removeEventListener('message',handler);
  },[apiOrigin]);

  const connectInstagram=async()=>{setBusy('instagram');setError('');setMsg('');try{const r=await api.post('/integrations/instagram/connect',{return_to:'/app/configuracoes'});const url=String(r.data.url||'');if(!url)throw new Error('URL de conexão ausente');const width=640,height=760,left=Math.max(0,(window.screen.width-width)/2),top=Math.max(0,(window.screen.height-height)/2);const popup=window.open(url,'equipeai-instagram',`width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);if(!popup)window.location.href=url;}catch(e:any){setError(e.response?.data?.error||e.message||'Não foi possível conectar o Instagram.');}finally{setBusy('');}};
  const disconnectInstagram=async()=>{if(!confirm('Desconectar o Instagram desta empresa?'))return;setBusy('instagram');try{await api.delete('/integrations/instagram');setInstagram(current=>({...current,connected:false,account:null}));}catch(e:any){setError(e.response?.data?.error||'Não foi possível desconectar o Instagram.');}finally{setBusy('');}};
  const connectWhatsapp=async()=>{if(!whatsapp.sdk)return;setBusy('whatsapp');setError('');setMsg('');try{const result=await launchWhatsAppEmbeddedSignup(whatsapp.sdk);const r=await api.post('/integrations/whatsapp/complete',result);setWhatsapp(r.data as WhatsAppStatus);setMsg('WhatsApp Business conectado à empresa.');}catch(e:any){setError(e.response?.data?.error||e.message||'Não foi possível conectar o WhatsApp.');}finally{setBusy('');}};
  const disconnectWhatsapp=async()=>{if(!confirm('Desconectar o WhatsApp desta empresa?'))return;setBusy('whatsapp');try{await api.delete('/integrations/whatsapp');setWhatsapp(current=>({...current,connected:false,account:null}));}catch(e:any){setError(e.response?.data?.error||'Não foi possível desconectar o WhatsApp.');}finally{setBusy('');}};
  const registerWhatsapp=async()=>{if(!/^\d{6}$/.test(pin))return setError('O PIN precisa ter 6 dígitos.');setBusy('whatsapp');setError('');try{const r=await api.post('/integrations/whatsapp/register',{pin});setWhatsapp(r.data as WhatsAppStatus);setPin('');setMsg('Número registrado na WhatsApp Cloud API.');}catch(e:any){setError(e.response?.data?.error||'Não foi possível registrar o número.');}finally{setBusy('');}};

  return <section className="panel meta-integrations-v38">
    <div className="settings-title"><FiLink2/><div><h3>Integrações Meta</h3><p>Conecte os canais da própria empresa. Tokens ficam protegidos no backend.</p></div></div>
    {msg&&<div className="alert-box success"><FiCheck/> {msg}</div>}{error&&<div className="alert-box error">{error}</div>}
    <div className="meta-integration-list-v38">
      <article className="meta-integration-row-v38">
        <span className="meta-integration-icon instagram"><FiInstagram/></span>
        <div><strong>Instagram</strong><small>{instagram.connected?`@${instagram.account?.username||'conta conectada'}${instagram.account?.account_type?` · ${instagram.account.account_type}`:''}`:'Publique imagens e legendas geradas pelo Marketing.'}</small></div>
        {instagram.connected?<div className="meta-integration-actions-v38"><b><i/> Conectado</b><button type="button" onClick={disconnectInstagram} disabled={busy==='instagram'}>Desconectar</button></div>:instagram.configured?<button type="button" className="meta-connect-btn-v38" onClick={connectInstagram} disabled={busy==='instagram'}><FiLink2/> {busy==='instagram'?'Abrindo…':'Conectar'}</button>:<em>Configuração pendente</em>}
      </article>
      <article className="meta-integration-row-v38">
        <span className="meta-integration-icon whatsapp"><FiMessageCircle/></span>
        <div><strong>WhatsApp Business</strong><small>{whatsapp.connected?(whatsapp.account?.display_phone_number||whatsapp.account?.verified_name||'Conta conectada'):'Envie propostas pelo número da própria empresa.'}</small></div>
        {whatsapp.connected?<div className="meta-integration-actions-v38"><b><i/> Conectado</b><button type="button" onClick={disconnectWhatsapp} disabled={busy==='whatsapp'}>Desconectar</button></div>:whatsapp.configured?<button type="button" className="meta-connect-btn-v38 whatsapp" onClick={connectWhatsapp} disabled={busy==='whatsapp'}><FiLink2/> {busy==='whatsapp'?'Abrindo…':'Conectar'}</button>:<em>Configuração pendente</em>}
      </article>
    </div>
    {whatsapp.connected&&whatsapp.account?.registration_status!=='registered'&&<div className="meta-register-wa-v38"><FiShield/><div><strong>Registro do número</strong><small>Se a Meta exigir a ativação da Cloud API, defina o PIN de 6 dígitos da verificação em duas etapas.</small><div><input inputMode="numeric" maxLength={6} value={pin} onChange={e=>setPin(e.target.value.replace(/\D/g,'').slice(0,6))} placeholder="PIN de 6 dígitos"/><button type="button" onClick={registerWhatsapp} disabled={busy==='whatsapp'||pin.length!==6}>Registrar</button></div></div></div>}
  </section>;
}
