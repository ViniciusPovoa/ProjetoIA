import {useEffect,useMemo,useRef,useState} from 'react';
import {api} from '../api';
import {launchWhatsAppEmbeddedSignup} from '../utils/metaWhatsapp';
import {FiCheck,FiCopy,FiDownload,FiEdit3,FiFileText,FiLink2,FiMail,FiMessageCircle,FiRefreshCw,FiSend,FiShield,FiX} from 'react-icons/fi';

type Template={slug:string;name:string;description:string;accent:string;background:string};
type Proposal={id:number;template:string;title:string;client_name:string;filename:string;public_url:string;download_url:string;expires_in_days:number};
type WhatsAppStatus={configured:boolean;connected:boolean;account?:{waba_id:string;phone_number_id:string;display_phone_number?:string;verified_name?:string;quality_rating?:string;registration_status?:string;last_send_at?:string}|null;sdk?:{app_id:string;config_id:string;graph_version:string;feature_type?:string;embedded_signup_version?:string}};
type Props={result:string;setResult:(value:string)=>void;runId:number|null;form:Record<string,unknown>;onRegenerate:()=>void;loading:boolean};

export default function ProposalStudio({result,setResult,runId,form,onRegenerate,loading}:Props){
  const[templates,setTemplates]=useState<Template[]>([]);
  const[selected,setSelected]=useState('executive');
  const[proposal,setProposal]=useState<Proposal|null>(null);
  const[generating,setGenerating]=useState(false);
  const[error,setError]=useState('');
  const[editing,setEditing]=useState(false);
  const[whatsappConfigured,setWhatsappConfigured]=useState(false);
  const[whatsappStatus,setWhatsappStatus]=useState<WhatsAppStatus>({configured:false,connected:false});
  const[whatsappConnecting,setWhatsappConnecting]=useState(false);
  const[registrationPin,setRegistrationPin]=useState('');
  const[channel,setChannel]=useState<'email'|'whatsapp'|null>(null);
  const[sending,setSending]=useState(false);
  const[sent,setSent]=useState('');
  const[email,setEmail]=useState('');
  const[emailSubject,setEmailSubject]=useState('');
  const[emailMessage,setEmailMessage]=useState('');
  const[phone,setPhone]=useState('');
  const[whatsappMessage,setWhatsappMessage]=useState('');
  const[copied,setCopied]=useState(false);
  const autoKey=useRef('');

  const clientName=String(form.client_name??'').trim();
  const service=String(form.service??'').trim();
  const value=String(form.value??'').trim();
  const deadline=String(form.deadline??'').trim();
  const title=useMemo(()=>service?`Proposta de ${service}`:'Proposta Comercial',[service]);

  useEffect(()=>{
    api.get('/proposals/templates').then(r=>setTemplates(r.data.templates||[])).catch(()=>setTemplates([]));
  },[]);

  const loadWhatsappStatus=async()=>{try{const r=await api.get('/integrations/whatsapp');const status=r.data as WhatsAppStatus;setWhatsappStatus(status);setWhatsappConfigured(Boolean(status.connected));}catch{setWhatsappStatus({configured:false,connected:false});setWhatsappConfigured(false);}};
  useEffect(()=>{void loadWhatsappStatus();},[]);

  useEffect(()=>{
    setEmailSubject(`Proposta comercial${service?` - ${service}`:''}`);
    setEmailMessage(`Olá${clientName?`, ${clientName}`:''}!\n\nSegue em anexo a proposta comercial para sua avaliação. Fico à disposição para esclarecer qualquer ponto e alinharmos os próximos passos.`);
    setWhatsappMessage(`Olá${clientName?`, ${clientName}`:''}! Segue a proposta comercial em PDF para sua avaliação. Se quiser, posso esclarecer qualquer ponto por aqui.`);
  },[clientName,service]);

  const generate=async(template=selected)=>{
    setGenerating(true);setError('');setSent('');
    try{
      const response=await api.post('/proposals',{
        template,title,client_name:clientName,service,value,deadline,content:result,run_id:runId||undefined,
      });
      setProposal(response.data.proposal as Proposal);
      setWhatsappConfigured(Boolean(response.data.whatsapp_configured)||whatsappStatus.connected);
    }catch(e:any){setError(e.response?.data?.error||'Não foi possível gerar o PDF da proposta.');}
    finally{setGenerating(false);}
  };

  useEffect(()=>{
    if(!result)return;
    const key=`${runId||'draft'}:${selected}:${result.length}:${result.slice(0,80)}`;
    if(autoKey.current===key)return;
    autoKey.current=key;
    void generate(selected);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  },[selected,runId]);

  const chooseTemplate=(slug:string)=>{setSelected(slug);setProposal(null);};
  const copyText=async()=>{await navigator.clipboard.writeText(result);setCopied(true);window.setTimeout(()=>setCopied(false),1300);};

  const connectWhatsapp=async()=>{
    if(!whatsappStatus.configured||!whatsappStatus.sdk)return;
    setWhatsappConnecting(true);setError('');setSent('');
    try{
      const signup=await launchWhatsAppEmbeddedSignup(whatsappStatus.sdk);
      const response=await api.post('/integrations/whatsapp/complete',signup);
      const status=response.data as WhatsAppStatus;setWhatsappStatus(status);setWhatsappConfigured(Boolean(status.connected));
      setSent(`WhatsApp conectado${status.account?.display_phone_number?` (${status.account.display_phone_number})`:''}. As propostas agora saem deste número.`);
    }catch(e:any){setError(e.response?.data?.error||e.message||'Não foi possível conectar o WhatsApp da empresa.');}
    finally{setWhatsappConnecting(false);}
  };
  const disconnectWhatsapp=async()=>{if(!confirm('Desconectar o WhatsApp desta empresa?'))return;setWhatsappConnecting(true);try{await api.delete('/integrations/whatsapp');setWhatsappStatus(current=>({...current,connected:false,account:null}));setWhatsappConfigured(false);}catch(e:any){setError(e.response?.data?.error||'Não foi possível desconectar o WhatsApp.');}finally{setWhatsappConnecting(false);}};
  const registerWhatsapp=async()=>{if(!/^\d{6}$/.test(registrationPin))return setError('O PIN precisa ter 6 dígitos.');setWhatsappConnecting(true);setError('');try{const r=await api.post('/integrations/whatsapp/register',{pin:registrationPin});setWhatsappStatus(r.data as WhatsAppStatus);setRegistrationPin('');setSent('Número registrado na Cloud API.');}catch(e:any){setError(e.response?.data?.error||'Não foi possível registrar o número.');}finally{setWhatsappConnecting(false);}};

  const sendEmail=async()=>{
    if(!proposal||!email.trim())return;setSending(true);setError('');
    try{await api.post(`/proposals/${proposal.id}/email`,{to:email.trim(),subject:emailSubject,message:emailMessage});setSent('Proposta enviada por e-mail com o PDF em anexo.');setChannel(null);}
    catch(e:any){setError(e.response?.data?.error||'Não foi possível enviar o e-mail.');}finally{setSending(false);}
  };
  const sendWhatsapp=async()=>{
    if(!proposal||!phone.trim())return;setSending(true);setError('');
    try{await api.post(`/proposals/${proposal.id}/whatsapp`,{phone:phone.trim(),caption:whatsappMessage});setSent('Proposta enviada pelo WhatsApp.');setChannel(null);}
    catch(e:any){setError(e.response?.data?.error||'Não foi possível enviar pelo WhatsApp.');}finally{setSending(false);}
  };

  return <div className="proposal-studio-v37">
    <header className="proposal-studio-head-v37">
      <div><span>PROPOSTA COMERCIAL</span><h1>{title}</h1><p>{clientName?`Preparada para ${clientName}`:'Proposta pronta para personalizar, exportar e enviar.'}</p></div>
      <div className="proposal-head-actions-v37"><button type="button" onClick={copyText}>{copied?<FiCheck/>:<FiCopy/>} Copiar texto</button><button type="button" onClick={()=>setEditing(v=>!v)}><FiEdit3/> {editing?'Fechar edição':'Editar conteúdo'}</button><button type="button" onClick={onRegenerate} disabled={loading}><FiRefreshCw/> Regenerar</button></div>
    </header>

    <div className="proposal-flow-v37"><span className="done"><b>1</b> Conteúdo gerado</span><i/><span className="active"><b>2</b> Escolher template</span><i/><span><b>3</b> Enviar proposta</span></div>

    {error&&<div className="alert-box error proposal-alert-v37">{error}</div>}
    {sent&&<div className="alert-box success proposal-alert-v37"><FiCheck/> {sent}</div>}

    <section className="proposal-template-section-v37">
      <div className="proposal-section-title-v37"><div><span>MODELOS DE PDF</span><h2>Escolha como a proposta será apresentada.</h2></div><small>A troca de template não altera o texto gerado pela IA.</small></div>
      <div className="proposal-template-grid-v37">
        {templates.map(template=><button type="button" key={template.slug} className={`proposal-template-card-v37 ${selected===template.slug?'active':''}`} onClick={()=>chooseTemplate(template.slug)}>
          <div className={`proposal-template-preview-v37 theme-${template.slug}`}><span/><strong>PROPOSTA</strong><b/><b/><em/></div>
          <div><strong>{template.name}</strong><p>{template.description}</p></div>{selected===template.slug&&<span className="template-check-v37"><FiCheck/></span>}
        </button>)}
      </div>
    </section>

    <section className="proposal-workspace-v37">
      <div className="proposal-content-v37">
        <div className="proposal-content-head-v37"><div><span>CONTEÚDO DA IA</span><h3>Texto da proposta</h3></div><button type="button" onClick={()=>setEditing(v=>!v)}><FiEdit3/> Editar</button></div>
        {editing?<><textarea value={result} onChange={e=>setResult(e.target.value)} rows={24}/><button type="button" className="btn-primary-custom" onClick={()=>{setEditing(false);autoKey.current='';void generate(selected);}}><FiRefreshCw/> Atualizar PDF</button></>:<div className="proposal-ai-copy-v37">{result}</div>}
        <div className="proposal-facts-v37">{service&&<span><small>Serviço</small><strong>{service}</strong></span>}{value&&<span><small>Investimento</small><strong>{value}</strong></span>}{deadline&&<span><small>Prazo</small><strong>{deadline}</strong></span>}</div>
      </div>

      <aside className="proposal-preview-panel-v37">
        <div className="proposal-preview-head-v37"><div><span>PDF GERADO</span><h3>Pré-visualização</h3></div>{proposal&&<span className="proposal-ready-v37"><FiCheck/> Pronto</span>}</div>
        <div className="proposal-pdf-frame-v37">
          {generating?<div className="proposal-pdf-loading-v37"><span className="spinner"/><strong>Montando PDF...</strong><p>Aplicando o template selecionado ao conteúdo.</p></div>:proposal?<iframe src={proposal.public_url} title="Pré-visualização da proposta"/>:<div className="proposal-pdf-empty-v37"><FiFileText/><strong>Preparando proposta</strong></div>}
        </div>
        <div className="proposal-delivery-actions-v37">
          {proposal&&<a href={proposal.download_url} className="proposal-download-v37"><FiDownload/> Baixar PDF</a>}
          <button type="button" onClick={()=>setChannel('email')} disabled={!proposal}><FiMail/> Enviar por e-mail</button>
          <button type="button" onClick={()=>setChannel('whatsapp')} disabled={!proposal||!whatsappConfigured}><FiMessageCircle/> Enviar pelo meu WhatsApp</button>
        </div>
        <div className={`proposal-whatsapp-account-v38 ${whatsappStatus.connected?'connected':''}`}>
          <div className="proposal-whatsapp-account-head-v38"><span><FiMessageCircle/></span><div><strong>WhatsApp Business da empresa</strong><small>{whatsappStatus.connected?(whatsappStatus.account?.display_phone_number||whatsappStatus.account?.verified_name||'Conta conectada'):'Cada empresa conecta o próprio número. A proposta não sai de um número central do EquipeAI.'}</small></div>{whatsappStatus.connected&&<b><i/> Conectado</b>}</div>
          {!whatsappStatus.configured?<p>O administrador ainda precisa configurar o Embedded Signup da Meta no backend.</p>
          :!whatsappStatus.connected?<button type="button" className="whatsapp-connect-own-v38" onClick={connectWhatsapp} disabled={whatsappConnecting}><FiLink2/> {whatsappConnecting?'Abrindo Meta…':'Conectar meu WhatsApp'}</button>
          :<div className="whatsapp-connected-actions-v38"><button type="button" onClick={disconnectWhatsapp} disabled={whatsappConnecting}>Desconectar</button></div>}
          {whatsappStatus.connected&&whatsappStatus.account?.registration_status!=='registered'&&<div className="whatsapp-register-v38"><span><FiShield/><small>Se a Meta solicitar o registro do número, defina o PIN de 6 dígitos da verificação em duas etapas.</small></span><div><input inputMode="numeric" maxLength={6} value={registrationPin} onChange={e=>setRegistrationPin(e.target.value.replace(/\D/g,'').slice(0,6))} placeholder="PIN de 6 dígitos"/><button type="button" onClick={registerWhatsapp} disabled={whatsappConnecting||registrationPin.length!==6}>Registrar número</button></div></div>}
        </div>
      </aside>
    </section>

    {channel&&proposal&&<div className="proposal-modal-layer-v37" role="dialog" aria-modal="true">
      <button type="button" className="proposal-modal-backdrop-v37" aria-label="Fechar" onClick={()=>!sending&&setChannel(null)}/>
      <section className="proposal-send-modal-v37">
        <header><div><span>{channel==='email'?<FiMail/>:<FiMessageCircle/>}</span><div><small>ENVIO DA PROPOSTA</small><h2>{channel==='email'?'Enviar por e-mail':'Enviar pelo WhatsApp da empresa'}</h2><p>{proposal.filename}</p></div></div><button type="button" onClick={()=>setChannel(null)} disabled={sending}><FiX/></button></header>
        {channel==='email'?<div className="proposal-send-fields-v37">
          <label>E-mail do cliente<input type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="cliente@empresa.com"/></label>
          <label>Assunto<input value={emailSubject} onChange={e=>setEmailSubject(e.target.value)}/></label>
          <label>Mensagem<textarea rows={7} value={emailMessage} onChange={e=>setEmailMessage(e.target.value)}/></label>
          <div className="proposal-attachment-v37"><FiFileText/><span><strong>{proposal.filename}</strong><small>PDF anexado automaticamente</small></span></div>
          <button type="button" className="proposal-send-final-v37" onClick={sendEmail} disabled={sending||!email.trim()}><FiSend/> {sending?'Enviando...':'Enviar proposta por e-mail'}</button>
        </div>:<div className="proposal-send-fields-v37">
          <label>WhatsApp do cliente<input value={phone} onChange={e=>setPhone(e.target.value)} placeholder="5534999999999"/><small>Use código do país + DDD + número.</small></label>
          <label>Mensagem<textarea rows={6} value={whatsappMessage} onChange={e=>setWhatsappMessage(e.target.value)}/></label>
          <div className="proposal-attachment-v37"><FiFileText/><span><strong>{proposal.filename}</strong><small>PDF enviado pelo número conectado: {whatsappStatus.account?.display_phone_number||'WhatsApp da empresa'}</small></span></div>
          <div className="proposal-window-note-v37"><FiShield/><p><strong>Regra do WhatsApp</strong><span>Mensagens livres funcionam dentro da janela de atendimento. Para iniciar uma conversa fora dela, configure um template aprovado com cabeçalho de documento.</span></p></div>
          <button type="button" className="proposal-send-final-v37 whatsapp" onClick={sendWhatsapp} disabled={sending||!phone.trim()}><FiSend/> {sending?'Enviando...':'Enviar proposta no WhatsApp'}</button>
        </div>}
      </section>
    </div>}
  </div>;
}
