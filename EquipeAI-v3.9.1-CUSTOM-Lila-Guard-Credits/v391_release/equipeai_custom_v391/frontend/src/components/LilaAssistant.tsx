import {FormEvent,useEffect,useMemo,useRef,useState} from 'react';
import {useLocation} from 'react-router-dom';
import {api} from '../api';
import {FiArrowUp,FiBriefcase,FiHelpCircle,FiMessageCircle,FiPlus,FiZap,FiX} from 'react-icons/fi';

type ChatMessage={id?:number;role:'user'|'assistant';content:string;created_at?:string;credits_used?:number};

const quickPrompts=[
  {icon:FiHelpCircle,label:'Como usar esta tela?',text:'Me explique o que posso fazer nesta tela e qual é o melhor próximo passo.'},
  {icon:FiBriefcase,label:'Me ajude com minha empresa',text:'Com base no contexto da minha empresa, o que você acha que eu deveria melhorar primeiro?'},
  {icon:FiMessageCircle,label:'Como criar uma proposta?',text:'Como eu crio uma proposta profissional em PDF e envio para o cliente?'},
];

function pageLabel(path:string){
  if(path==='/app')return'Visão geral';
  if(path.includes('/empresa'))return'Minha empresa';
  if(path.includes('/configuracoes'))return'Configurações';
  if(path.includes('/historico'))return'Histórico';
  if(path.includes('/arquivos'))return'Arquivos';
  if(path.includes('/creditos'))return'Uso e créditos';
  if(path.includes('/assinatura'))return'Assinatura';
  if(path.includes('/agentes'))return'Ferramentas';
  return'EquipeAI';
}

function MessageBody({content}:{content:string}){
  const blocks=content.split(/\n{2,}/).filter(Boolean);
  return <>{blocks.map((block,index)=>{
    const lines=block.split('\n').filter(line=>line.trim()!=='');
    const bullets=lines.length>0&&lines.every(line=>/^\s*(?:[-•]|\d+[.)])\s+/.test(line));
    if(bullets)return <ul key={index}>{lines.map((line,i)=><li key={i}>{line.replace(/^\s*(?:[-•]|\d+[.)])\s+/,'')}</li>)}</ul>;
    return <p key={index}>{block}</p>;
  })}</>;
}

export default function LilaAssistant(){
  const location=useLocation();
  const[open,setOpen]=useState(false);
  const[loaded,setLoaded]=useState(false);
  const[conversationId,setConversationId]=useState<number|null>(null);
  const[messages,setMessages]=useState<ChatMessage[]>([]);
  const[input,setInput]=useState('');
  const[sending,setSending]=useState(false);
  const[error,setError]=useState('');
  const listRef=useRef<HTMLDivElement>(null);
  const textareaRef=useRef<HTMLTextAreaElement>(null);
  const currentPage=useMemo(()=>pageLabel(location.pathname),[location.pathname]);

  useEffect(()=>{
    if(!open||loaded)return;
    api.get('/assistant/lila').then(r=>{
      setConversationId(r.data?.conversation_id??null);
      setMessages((r.data?.messages||[]).map((m:any)=>({id:m.id,role:m.role,content:m.content,created_at:m.created_at})));
      setLoaded(true);
    }).catch(()=>{setError('Não consegui carregar a conversa anterior.');setLoaded(true)});
  },[open,loaded]);

  useEffect(()=>{
    if(!open)return;
    window.setTimeout(()=>{listRef.current?.scrollTo({top:listRef.current.scrollHeight,behavior:'smooth'});},40);
  },[open,messages,sending]);

  useEffect(()=>{if(open)window.setTimeout(()=>textareaRef.current?.focus(),100)},[open]);

  const send=async(textOverride?:string)=>{
    const message=(textOverride??input).trim();
    if(!message||sending)return;
    setError('');setInput('');setSending(true);
    setMessages(current=>[...current,{role:'user',content:message}]);
    try{
      const r=await api.post('/assistant/lila/chat',{message,conversation_id:conversationId,current_path:location.pathname});
      setConversationId(r.data.conversation_id);
      setMessages(current=>[...current,{role:'assistant',content:r.data.message.content,credits_used:r.data.credits_used??1}]);
      window.dispatchEvent(new CustomEvent('equipeai:credits-changed',{detail:{delta:-(r.data.credits_used??1)}}));
    }catch(e:any){
      setError(e.response?.data?.error||'A Lila não conseguiu responder agora.');
    }finally{setSending(false)}
  };

  const submit=(event:FormEvent)=>{event.preventDefault();send()};
  const newChat=async()=>{
    if(sending)return;
    setError('');
    try{
      const r=await api.post('/assistant/lila/new',{});
      setConversationId(r.data.conversation_id);
      setMessages([]);
      setInput('');
      textareaRef.current?.focus();
    }catch(e:any){setError(e.response?.data?.error||'Não foi possível iniciar uma nova conversa.')}
  };

  return <>
    <button className={`lila-fab ${open?'is-open':''}`} type="button" onClick={()=>setOpen(v=>!v)} aria-label={open?'Fechar Lila':'Abrir Lila'} aria-expanded={open}>
      {open?<FiX/>:<><span className="lila-fab-mark">L</span><span className="lila-fab-copy"><strong>Lila</strong><small>Posso ajudar</small></span></>}
    </button>

    {open&&<section className="lila-panel" role="dialog" aria-label="Lila, assistente do EquipeAI">
      <header className="lila-header">
        <div className="lila-identity"><span className="lila-avatar">L</span><div><div><strong>Lila</strong><span className="lila-online-dot"/></div><small>Assistente do seu workspace</small></div></div>
        <div className="lila-header-actions"><button type="button" onClick={newChat} title="Nova conversa"><FiPlus/></button><button type="button" onClick={()=>setOpen(false)} title="Fechar"><FiX/></button></div>
      </header>
      <div className="lila-context-strip"><FiZap/><span>Contexto da empresa</span><i/><span className="lila-credit-pill">1 crédito por resposta</span><i/><small>{currentPage}</small></div>

      <div className="lila-messages" ref={listRef} aria-live="polite">
        {!loaded?<div className="lila-loading"><span/><span/><span/></div>:messages.length===0?<div className="lila-welcome">
          <div className="lila-welcome-mark">L</div>
          <h3>Oi, eu sou a Lila.</h3>
          <p>Posso explicar o EquipeAI e ajudar com decisões ligadas à sua empresa. Assuntos fora desse escopo não são atendidos.</p><div className="lila-scope-note"><FiZap/><span>Cada pergunta respondida consome 1 crédito.</span></div>
          <div className="lila-quick-prompts">{quickPrompts.map(item=>{const Icon=item.icon;return <button key={item.label} type="button" onClick={()=>send(item.text)}><Icon/><span>{item.label}</span></button>})}</div>
        </div>:messages.map((message,index)=><div className={`lila-message ${message.role}`} key={`${message.id??index}-${index}`}>
          {message.role==='assistant'&&<span className="lila-message-avatar">L</span>}
          <div className="lila-message-bubble"><MessageBody content={message.content}/>{message.role==='assistant'&&message.credits_used&&<small className="lila-message-credit">-{message.credits_used} crédito</small>}</div>
        </div>)}
        {sending&&<div className="lila-message assistant"><span className="lila-message-avatar">L</span><div className="lila-typing"><i/><i/><i/></div></div>}
        {error&&<div className="lila-error">{error}</div>}
      </div>

      <form className="lila-composer" onSubmit={submit}>
        <textarea ref={textareaRef} rows={1} maxLength={1800} value={input} onChange={e=>setInput(e.target.value)} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send()}}} placeholder="Pergunte sobre o EquipeAI ou sua empresa…"/>
        <button type="submit" disabled={!input.trim()||sending} aria-label="Enviar mensagem"><FiArrowUp/></button>
        <small>Enter para enviar · Shift + Enter para quebrar linha</small>
      </form>
    </section>}
  </>;
}
